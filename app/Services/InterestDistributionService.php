<?php

namespace App\Services;

use App\Enums\AllocationStatus;
use App\Enums\DistributionBeneficiaryType;
use App\Enums\DistributionEntryType;
use App\Enums\DistributionStatus;
use App\Enums\UserRole;
use App\Models\AdminCommissionProfile;
use App\Models\EmiPayment;
use App\Models\InterestConfiguration;
use App\Models\InterestDistributionLedger;
use App\Models\Investor;
use App\Models\InvestorAllocation;
use App\Models\InvestorInterestRate;
use App\Models\Loan;
use App\Models\LoanAdminAssignment;
use App\Models\LoanDistributionConfig;
use App\Models\LoanInvestorDistribution;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InterestDistributionService
{
    public function __construct(
        private readonly JournalService $journal,
    ) {}

    /**
     * Build and persist the immutable rule snapshot for a loan. Existing snapshots
     * are returned unchanged so future configuration edits cannot rewrite history.
     */
    public function ensureSnapshot(Loan $loan, ?User $actor = null): LoanDistributionConfig
    {
        return DB::transaction(function () use ($loan, $actor): LoanDistributionConfig {
            /** @var Loan $locked */
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->getKey());

            $existing = LoanDistributionConfig::query()
                ->where('loan_id', $locked->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $date = $locked->disbursement_date?->toImmutable() ?? CarbonImmutable::today();

            $configuration = InterestConfiguration::activeFor($date) ?? InterestConfiguration::activeFor();

            if ($configuration === null) {
                throw new InvalidArgumentException('No active interest configuration exists. Create one in Interest & Distribution Settings first.');
            }

            $totalPercent = $configuration->totalAllocationPercent();

            if (Money::compare($totalPercent, '100') !== 0) {
                throw new InvalidArgumentException('The active interest configuration must allocate exactly 100%. Current total: '.$totalPercent.'%.');
            }

            $loanMonthlyRate = $this->loanMonthlyRate($locked);

            $allocations = $locked->allocations()
                ->with('investor')
                ->where('status', AllocationStatus::Active->value)
                ->lockForUpdate()
                ->get();

            $allocatedPrincipal = $allocations->reduce(
                fn (string $carry, InvestorAllocation $allocation): string => Money::add($carry, (string) $allocation->principal_amount),
                '0',
            );

            if (Money::compare($allocatedPrincipal, (string) $locked->principal_amount) > 0) {
                throw new InvalidArgumentException('Investor exposure exceeds the loan principal. Reduce allocations before distributing interest.');
            }

            $investors = [];
            $totalWeightedPrincipal = Money::isZero($allocatedPrincipal) ? '1' : $allocatedPrincipal;

            /** @var array<int, array{investor_id: int, investor_allocation_id: int|null, principal_amount: string, monthly_rate: string, share_percent: string}> $investorRows */
            $investorRows = [];

            foreach ($allocations as $allocation) {
                /** @var Investor $investor */
                $investor = $allocation->investor;
                $rate = InvestorInterestRate::activeFor($investor, $date)?->monthly_rate
                    ?? $configuration->default_investor_monthly_rate
                    ?? $loanMonthlyRate;

                $share = Money::round(Money::div(Money::mul((string) $allocation->principal_amount, '100'), $totalWeightedPrincipal), 6);

                $investorRows[] = [
                    'investor_id' => $investor->getKey(),
                    'investor_allocation_id' => $allocation->getKey(),
                    'principal_amount' => Money::round((string) $allocation->principal_amount),
                    'monthly_rate' => Money::round($rate, 6),
                    'share_percent' => $share,
                ];

                $investors[] = [
                    'investor_id' => $investor->getKey(),
                    'investor_code' => $investor->investor_code,
                    'investor_name' => $investor->name,
                    'allocation_id' => $allocation->getKey(),
                    'principal_amount' => Money::round((string) $allocation->principal_amount),
                    'monthly_rate' => Money::round($rate, 6),
                ];
            }

            $admins = $this->responsibleAdmins($locked, $configuration);

            $snapshot = [
                'captured_at' => CarbonImmutable::now()->toIso8601String(),
                'configuration_id' => $configuration->getKey(),
                'configuration_name' => $configuration->name,
                'effective_from' => $configuration->effective_from->toDateString(),
                'loan_monthly_rate' => $loanMonthlyRate,
                'borrower_annual_rate' => (string) $locked->annual_interest_rate,
                'percentages' => [
                    'investor' => (string) $configuration->investor_allocation_percent,
                    'admin' => (string) $configuration->admin_allocation_percent,
                    'company' => (string) $configuration->company_allocation_percent,
                ],
                'allocated_principal' => Money::round($allocatedPrincipal),
                'loan_principal' => Money::round((string) $locked->principal_amount),
                'investors' => $investors,
                'admins' => $admins,
            ];

            $config = LoanDistributionConfig::query()->create([
                'loan_id' => $locked->getKey(),
                'interest_configuration_id' => $configuration->getKey(),
                'borrower_monthly_rate' => $loanMonthlyRate,
                'default_investor_monthly_rate' => $configuration->default_investor_monthly_rate,
                'investor_allocation_percent' => $configuration->investor_allocation_percent,
                'admin_allocation_percent' => $configuration->admin_allocation_percent,
                'company_allocation_percent' => $configuration->company_allocation_percent,
                'rules_snapshot' => $snapshot,
                'locked_at' => now(),
                'created_by' => $actor?->getKey() ?? auth()->id(),
            ]);

            foreach ($investorRows as $row) {
                LoanInvestorDistribution::query()->create($row + ['loan_id' => $locked->getKey()]);
            }

            foreach ($admins as $admin) {
                LoanAdminAssignment::query()->create([
                    'loan_id' => $locked->getKey(),
                    'user_id' => $admin['user_id'],
                    'commission_rate' => $admin['commission_rate'],
                    'is_primary' => $admin['is_primary'],
                    'assigned_at' => now(),
                ]);
            }

            AuditLogger::log('distribution.snapshot', $config, [], [
                'loan_id' => $locked->getKey(),
                'configuration_id' => $configuration->getKey(),
                'investor_count' => count($investorRows),
                'admin_count' => count($admins),
                'percentages' => $snapshot['percentages'],
            ]);

            return $config->refresh();
        });
    }

    /**
     * Create separate immutable ledger entries for every beneficiary using only
     * the interest component of the EMI payment. Idempotent per payment/beneficiary.
     *
     * @return Collection<int, InterestDistributionLedger>
     */
    public function distributeForPayment(EmiPayment $payment, ?User $actor = null): Collection
    {
        return DB::transaction(function () use ($payment, $actor): Collection {
            /** @var EmiPayment $locked */
            $locked = EmiPayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->is_reversed) {
                throw new InvalidArgumentException('Distribution cannot be generated for a reversed payment.');
            }

            $interest = Money::round((string) $locked->interest_component);

            if (Money::compare($interest, '0') <= 0) {
                return collect();
            }

            $loan = Loan::query()->findOrFail($locked->loan_id);
            $config = $this->ensureSnapshot($loan, $actor);

            $periodMonth = CarbonImmutable::parse($locked->payment_date)->startOfMonth();

            $existing = InterestDistributionLedger::query()
                ->where('emi_payment_id', $locked->getKey())
                ->lockForUpdate()
                ->get();

            if ($existing->isNotEmpty()) {
                return $existing->load('investor', 'admin', 'loan');
            }

            $created = collect();

            $created = $created->merge($this->distributeInvestorShare($config, $locked, $interest, $periodMonth, $actor));
            $created = $created->merge($this->distributeAdminShare($config, $locked, $interest, $periodMonth, $actor));

            $allocated = $created->reduce(fn (string $carry, InterestDistributionLedger $entry): string => Money::add($carry, (string) $entry->amount), '0');
            $companyAmount = Money::round(Money::sub($interest, $allocated));

            if (Money::compare($companyAmount, '0') < 0) {
                throw new InvalidArgumentException('Distribution exceeds the collected interest component. Check the loan snapshot percentages and rates.');
            }

            if (Money::compare($companyAmount, '0') > 0) {
                $created->push($this->recordEntry([
                    'entitlement_key' => $this->entitlementKey($locked, DistributionBeneficiaryType::Company),
                    'emi_payment_id' => $locked->getKey(),
                    'loan_id' => $locked->getKey(),
                    'beneficiary_type' => DistributionBeneficiaryType::Company,
                    'entry_type' => DistributionEntryType::Earning,
                    'amount' => $companyAmount,
                    'collected_interest' => $interest,
                    'applicable_rate' => $config->company_allocation_percent,
                    'share_percent' => $config->company_allocation_percent,
                    'period_month' => $periodMonth->toDateString(),
                    'status' => DistributionStatus::Earned,
                    'reference' => $locked->receipt_no,
                    'metadata' => [
                        'configured_company_percent' => (string) $config->company_allocation_percent,
                        'residual_from_investor_pool' => Money::sub($interest, (string) $config->company_allocation_percent),
                    ],
                    'created_by' => $actor?->getKey() ?? auth()->id(),
                ]));
            }

            $this->postDistributionJournal($locked, $created, $actor);

            AuditLogger::log('distribution.posted', $locked, [], [
                'emi_payment_id' => $locked->getKey(),
                'loan_id' => $locked->getKey(),
                'interest' => $interest,
                'entries' => $created->count(),
                'investor_total' => $created->where('beneficiary_type', DistributionBeneficiaryType::Investor)->sum('amount'),
                'admin_total' => $created->where('beneficiary_type', DistributionBeneficiaryType::Admin)->sum('amount'),
                'company_total' => $created->where('beneficiary_type', DistributionBeneficiaryType::Company)->sum('amount'),
            ]);

            return $created->each(fn (InterestDistributionLedger $entry) => $entry->load('investor', 'admin', 'loan'));
        });
    }

    /**
     * @return array<int, array{user_id: int, name: string, designation: string|null, commission_rate: string, is_primary: bool}>
     */
    private function responsibleAdmins(Loan $loan, InterestConfiguration $configuration): array
    {
        $candidates = collect([$loan->final_approved_by, $loan->approved_by])
            ->filter()
            ->unique()
            ->values();

        $users = $candidates->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $candidates)->whereIn('role', [UserRole::Admin->value, UserRole::SuperAdmin->value])->get();

        if ($users->isEmpty()) {
            $fallback = User::query()->where('role', UserRole::Admin->value)->orderBy('id')->first()
                ?? User::query()->where('role', UserRole::SuperAdmin->value)->orderBy('id')->first();

            $users = $fallback ? collect([$fallback]) : collect();
        }

        return $users->values()->map(function (User $user, int $index) use ($configuration): array {
            $profile = AdminCommissionProfile::activeFor($user);

            return [
                'user_id' => $user->getKey(),
                'name' => $user->name,
                'designation' => $profile?->designation,
                'commission_rate' => Money::round(
                    $profile?->commission_rate
                        ?? $configuration->default_admin_commission_rate
                        ?? $configuration->admin_allocation_percent,
                    4,
                ),
                'is_primary' => $index === 0,
            ];
        })->all();
    }

    /**
     * @return Collection<int, InterestDistributionLedger>
     */
    private function distributeInvestorShare(
        LoanDistributionConfig $config,
        EmiPayment $payment,
        string $interest,
        CarbonImmutable $periodMonth,
        ?User $actor,
    ): Collection {
        $rows = LoanInvestorDistribution::query()
            ->where('loan_id', $config->loan_id)
            ->with('investor')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $pool = Money::round(Money::div(Money::mul($interest, (string) $config->investor_allocation_percent), '100'));

        if (Money::compare($pool, '0') <= 0) {
            return collect();
        }

        $targets = $rows->map(function (LoanInvestorDistribution $row): array {
            $principal = (string) $row->principal_amount;
            $rate = (string) $row->monthly_rate;
            $target = Money::div(Money::mul($principal, $rate), '100');

            return ['row' => $row, 'target' => $target];
        });

        $totalTarget = $targets->reduce(fn (string $carry, array $target): string => Money::add($carry, $target['target']), '0');

        if (Money::compare($totalTarget, '0') <= 0) {
            return collect();
        }

        $scale = Money::compare($totalTarget, $pool) > 0
            ? Money::div($pool, $totalTarget)
            : '1';

        $entries = collect();
        $remaining = $pool;

        foreach ($targets as $index => $target) {
            /** @var LoanInvestorDistribution $row */
            $row = $target['row'];
            $isLast = $index === $targets->count() - 1;

            $amount = $isLast && Money::compare($totalTarget, $pool) > 0
                ? Money::round($remaining)
                : Money::round(Money::mul($target['target'], $scale));

            if (Money::compare($amount, '0') <= 0) {
                continue;
            }

            $remaining = Money::sub($remaining, $amount);

            $entries->push($this->recordEntry([
                'entitlement_key' => $this->entitlementKey($payment, DistributionBeneficiaryType::Investor, $row->investor_id),
                'emi_payment_id' => $payment->getKey(),
                'loan_id' => $payment->loan_id,
                'beneficiary_type' => DistributionBeneficiaryType::Investor,
                'entry_type' => DistributionEntryType::Earning,
                'investor_id' => $row->investor_id,
                'investor_allocation_id' => $row->investor_allocation_id,
                'loan_investor_distribution_id' => $row->getKey(),
                'amount' => $amount,
                'collected_interest' => $interest,
                'applicable_rate' => $row->monthly_rate,
                'share_percent' => $row->share_percent,
                'period_month' => $periodMonth->toDateString(),
                'status' => DistributionStatus::Earned,
                'reference' => $payment->receipt_no,
                'metadata' => [
                    'investor_pool' => $pool,
                    'scaled' => Money::compare($totalTarget, $pool) > 0,
                    'target_amount' => Money::round($target['target']),
                ],
                'created_by' => $actor?->getKey() ?? auth()->id(),
            ]));
        }

        return $entries;
    }

    /**
     * @return Collection<int, InterestDistributionLedger>
     */
    private function distributeAdminShare(
        LoanDistributionConfig $config,
        EmiPayment $payment,
        string $interest,
        CarbonImmutable $periodMonth,
        ?User $actor,
    ): Collection {
        $assignments = LoanAdminAssignment::query()
            ->where('loan_id', $config->loan_id)
            ->with('user')
            ->get();

        if ($assignments->isEmpty()) {
            return collect();
        }

        $pool = Money::round(Money::div(Money::mul($interest, (string) $config->admin_allocation_percent), '100'));

        if (Money::compare($pool, '0') <= 0) {
            return collect();
        }

        $totalRate = $assignments->reduce(fn (string $carry, LoanAdminAssignment $assignment): string => Money::add($carry, (string) $assignment->commission_rate), '0');

        if (Money::compare($totalRate, '0') <= 0) {
            $totalRate = (string) $assignments->count();
            $weights = $assignments->map(fn () => '1');
        } else {
            $weights = $assignments->map(fn (LoanAdminAssignment $assignment): string => (string) $assignment->commission_rate);
        }

        $entries = collect();
        $remaining = $pool;
        $weights = $weights->values();

        foreach ($assignments->values() as $index => $assignment) {
            $isLast = $index === $assignments->count() - 1;
            $weight = $weights[$index];

            $amount = $isLast
                ? Money::round($remaining)
                : Money::round(Money::div(Money::mul($pool, $weight), $totalRate));

            if (Money::compare($amount, '0') <= 0) {
                continue;
            }

            $remaining = Money::sub($remaining, $amount);

            $entries->push($this->recordEntry([
                'entitlement_key' => $this->entitlementKey($payment, DistributionBeneficiaryType::Admin, $assignment->user_id),
                'emi_payment_id' => $payment->getKey(),
                'loan_id' => $payment->loan_id,
                'beneficiary_type' => DistributionBeneficiaryType::Admin,
                'entry_type' => DistributionEntryType::Earning,
                'admin_id' => $assignment->user_id,
                'amount' => $amount,
                'collected_interest' => $interest,
                'applicable_rate' => $assignment->commission_rate,
                'share_percent' => $assignment->commission_rate,
                'period_month' => $periodMonth->toDateString(),
                'status' => DistributionStatus::Earned,
                'reference' => $payment->receipt_no,
                'metadata' => [
                    'admin_pool' => $pool,
                    'assigned_admin' => $assignment->user?->name,
                ],
                'created_by' => $actor?->getKey() ?? auth()->id(),
            ]));
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordEntry(array $attributes): InterestDistributionLedger
    {
        return InterestDistributionLedger::query()->create($attributes);
    }

    /**
     * @param  Collection<int, InterestDistributionLedger>  $entries
     */
    private function postDistributionJournal(EmiPayment $payment, Collection $entries, ?User $actor): void
    {
        $investorTotal = (string) $entries->where('beneficiary_type', DistributionBeneficiaryType::Investor)->sum('amount');
        $adminTotal = (string) $entries->where('beneficiary_type', DistributionBeneficiaryType::Admin)->sum('amount');
        $companyTotal = (string) $entries->where('beneficiary_type', DistributionBeneficiaryType::Company)->sum('amount');
        $interest = (string) $payment->interest_component;

        $lines = [
            ['code' => '4000', 'debit' => $interest, 'narration' => 'Interest distributed '.$payment->receipt_no],
        ];

        if (Money::compare($investorTotal, '0') > 0) {
            $lines[] = ['code' => '2200', 'credit' => $investorTotal, 'narration' => 'Investor interest payable '.$payment->receipt_no];
        }

        if (Money::compare($adminTotal, '0') > 0) {
            $lines[] = ['code' => '2300', 'credit' => $adminTotal, 'narration' => 'Admin commission payable '.$payment->receipt_no];
        }

        if (Money::compare($companyTotal, '0') > 0) {
            $lines[] = ['code' => '4300', 'credit' => $companyTotal, 'narration' => 'Company interest share '.$payment->receipt_no];
        }

        $this->journal->post(
            CarbonImmutable::parse($payment->payment_date),
            'Interest distribution '.$payment->receipt_no,
            $lines,
            $payment,
            $actor?->getKey() ?? auth()->id(),
        );
    }

    /**
     * Reverse every distribution entry for a payment. The original rows are never
     * edited; compensating entries and a reversing journal are posted instead.
     */
    public function reverseForPayment(EmiPayment $payment, User $actor, string $reason): int
    {
        return DB::transaction(function () use ($payment, $actor, $reason): int {
            /** @var EmiPayment $locked */
            $locked = EmiPayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            $originals = InterestDistributionLedger::query()
                ->where('emi_payment_id', $locked->getKey())
                ->earnings()
                ->with(['reversal', 'investorPayoutItem', 'adminCommissionPayoutItem'])
                ->lockForUpdate()
                ->get();

            $linkedToPayout = $originals->contains(
                fn (InterestDistributionLedger $entry): bool => $entry->investorPayoutItem()->exists()
                    || $entry->adminCommissionPayoutItem()->exists(),
            );

            if ($linkedToPayout) {
                throw new InvalidArgumentException('This receipt is linked to an investor or admin payout. Reject or cancel that payout before reversing the receipt.');
            }

            $created = 0;
            $totals = [
                DistributionBeneficiaryType::Investor->value => '0',
                DistributionBeneficiaryType::Admin->value => '0',
                DistributionBeneficiaryType::Company->value => '0',
            ];

            foreach ($originals as $original) {
                if ($original->reversal()->exists()) {
                    continue;
                }

                InterestDistributionLedger::query()->create([
                    'entitlement_key' => 'payment:'.$locked->getKey().':reversal:'.$original->getKey(),
                    'emi_payment_id' => $locked->getKey(),
                    'loan_id' => $locked->loan_id,
                    'beneficiary_type' => $original->beneficiary_type,
                    'entry_type' => DistributionEntryType::Reversal,
                    'investor_id' => $original->investor_id,
                    'admin_id' => $original->admin_id,
                    'investor_allocation_id' => $original->investor_allocation_id,
                    'loan_investor_distribution_id' => $original->loan_investor_distribution_id,
                    'amount' => $original->amount,
                    'collected_interest' => $original->collected_interest,
                    'applicable_rate' => $original->applicable_rate,
                    'share_percent' => $original->share_percent,
                    'period_month' => $original->period_month,
                    'status' => DistributionStatus::Reversed,
                    'reversal_of_id' => $original->getKey(),
                    'reference' => $locked->receipt_no,
                    'metadata' => ['reason' => $reason],
                    'created_by' => $actor->getKey(),
                ]);

                $totals[$original->beneficiary_type->value] = Money::add($totals[$original->beneficiary_type->value], (string) $original->amount);
                $created++;
            }

            if ($created === 0) {
                return 0;
            }

            $lines = [];

            if (Money::compare($totals[DistributionBeneficiaryType::Investor->value], '0') > 0) {
                $lines[] = ['code' => '2200', 'debit' => $totals[DistributionBeneficiaryType::Investor->value], 'narration' => 'Reverse investor payable '.$locked->receipt_no];
            }

            if (Money::compare($totals[DistributionBeneficiaryType::Admin->value], '0') > 0) {
                $lines[] = ['code' => '2300', 'debit' => $totals[DistributionBeneficiaryType::Admin->value], 'narration' => 'Reverse admin commission '.$locked->receipt_no];
            }

            if (Money::compare($totals[DistributionBeneficiaryType::Company->value], '0') > 0) {
                $lines[] = ['code' => '4300', 'debit' => $totals[DistributionBeneficiaryType::Company->value], 'narration' => 'Reverse company share '.$locked->receipt_no];
            }

            $lines[] = ['code' => '4000', 'credit' => (string) $locked->interest_component, 'narration' => 'Reverse distributed interest '.$locked->receipt_no];

            $this->journal->post(
                CarbonImmutable::today(),
                'Distribution reversal '.$locked->receipt_no,
                $lines,
                $locked,
                $actor->getKey(),
            );

            AuditLogger::log('distribution.reversed', $locked, [], [
                'emi_payment_id' => $locked->getKey(),
                'reason' => $reason,
                'entries' => $created,
                'totals' => $totals,
            ]);

            return $created;
        });
    }

    private function entitlementKey(EmiPayment $payment, DistributionBeneficiaryType $type, ?int $beneficiaryId = null): string
    {
        return 'payment:'.$payment->getKey().':'.$type->value.($beneficiaryId !== null ? ':'.$beneficiaryId : '');
    }

    private function loanMonthlyRate(Loan $loan): string
    {
        return Money::round(Money::div((string) $loan->annual_interest_rate, '12'), 6);
    }
}
