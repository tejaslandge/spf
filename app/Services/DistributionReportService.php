<?php

namespace App\Services;

use App\Enums\DistributionBeneficiaryType;
use App\Enums\DistributionEntryType;
use App\Enums\PayoutStatus;
use App\Models\AdminCommissionPayout;
use App\Models\EmiPayment;
use App\Models\InterestDistributionLedger;
use App\Models\InvestorPayout;
use App\Models\Loan;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DistributionReportService
{
    /**
     * @return array<string, string|int>
     */
    public function summary(): array
    {
        $earnings = InterestDistributionLedger::query()->earnings();
        $reversals = InterestDistributionLedger::query()->reversals();

        $investorEarned = $this->netFor(clone $earnings, DistributionBeneficiaryType::Investor, clone $reversals);
        $adminEarned = $this->netFor(clone $earnings, DistributionBeneficiaryType::Admin, clone $reversals);
        $companyEarned = $this->netFor(clone $earnings, DistributionBeneficiaryType::Company, clone $reversals);

        return [
            'collected_interest' => Money::round((string) EmiPayment::query()
                ->whereIn('id', (clone $earnings)->select('emi_payment_id'))
                ->sum('interest_component')),
            'reversed_interest' => Money::round((string) EmiPayment::query()
                ->whereIn('id', (clone $reversals)->select('emi_payment_id'))
                ->sum('interest_component')),
            'investor_earned' => $investorEarned,
            'admin_earned' => $adminEarned,
            'company_earned' => $companyEarned,
            'investor_pending' => $this->pendingFor(InvestorPayout::query()),
            'investor_approved' => $this->totalFor(InvestorPayout::query(), PayoutStatus::Approved),
            'investor_paid' => $this->totalFor(InvestorPayout::query(), PayoutStatus::Paid),
            'admin_pending' => $this->pendingFor(AdminCommissionPayout::query()),
            'admin_approved' => $this->totalFor(AdminCommissionPayout::query(), PayoutStatus::Approved),
            'admin_paid' => $this->totalFor(AdminCommissionPayout::query(), PayoutStatus::Paid),
            'distribution_entries' => InterestDistributionLedger::query()->count(),
            'reversal_entries' => InterestDistributionLedger::query()->reversals()->count(),
        ];
    }

    /**
     * @return array<int, array{month: string, collected: string, investor: string, admin: string, company: string, reversed: string}>
     */
    public function monthly(int $months = 12): array
    {
        $start = CarbonImmutable::today()->startOfMonth()->subMonths($months - 1);

        return collect(range(0, $months - 1))
            ->map(function (int $offset) use ($start): array {
                $month = $start->addMonthsNoOverflow($offset);
                $from = $month->startOfMonth()->toDateString();
                $to = $month->endOfMonth()->toDateString();

                $base = InterestDistributionLedger::query()
                    ->whereBetween('period_month', [$from, $to]);

                return [
                    'month' => $month->format('M Y'),
                    'collected' => Money::round((string) EmiPayment::query()
                        ->whereBetween('payment_date', [$from, $to])
                        ->whereHas('distributions', fn (Builder $query) => $query->earnings())
                        ->sum('interest_component')),
                    'investor' => Money::round((string) (clone $base)->forInvestors()->earnings()->sum('amount')),
                    'admin' => Money::round((string) (clone $base)->forAdmins()->earnings()->sum('amount')),
                    'company' => Money::round((string) (clone $base)->forCompany()->earnings()->sum('amount')),
                    'reversed' => Money::round((string) EmiPayment::query()
                        ->whereBetween('payment_date', [$from, $to])
                        ->whereHas('distributions', fn (Builder $query) => $query->reversals())
                        ->sum('interest_component')),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{loan_id: int, loan_code: string, borrower: string, collected: string, investor: string, admin: string, company: string, reversed: string}>
     */
    public function byLoan(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $entries = InterestDistributionLedger::query()
            ->when($from, fn (Builder $query) => $query->whereDate('period_month', '>=', $from->toDateString()))
            ->when($to, fn (Builder $query) => $query->whereDate('period_month', '<=', $to->toDateString()))
            ->get(['loan_id', 'emi_payment_id', 'entry_type', 'beneficiary_type', 'amount']);

        $payments = EmiPayment::query()
            ->whereIn('id', $entries->pluck('emi_payment_id')->unique())
            ->get(['id', 'loan_id', 'interest_component'])
            ->keyBy('id');

        $rows = $entries->groupBy('loan_id');
        $loans = Loan::query()->with('borrower')->whereIn('id', $rows->keys())->get();

        return $loans->map(function (Loan $loan) use ($rows, $payments): array {
            /** @var Collection<int, InterestDistributionLedger> $loanEntries */
            $loanEntries = $rows->get($loan->getKey(), collect());

            $collectedPaymentIds = $loanEntries
                ->filter(fn (InterestDistributionLedger $entry): bool => $entry->entry_type === DistributionEntryType::Earning)
                ->pluck('emi_payment_id')
                ->unique();

            $reversedPaymentIds = $loanEntries
                ->filter(fn (InterestDistributionLedger $entry): bool => $entry->entry_type === DistributionEntryType::Reversal)
                ->pluck('emi_payment_id')
                ->unique();

            $sumPayments = fn (Collection $ids): string => $ids->reduce(
                fn (string $carry, int $id): string => Money::add($carry, (string) ($payments->get($id)?->interest_component ?? 0)),
                '0',
            );

            $amountFor = fn (DistributionBeneficiaryType $type): string => Money::round($loanEntries
                ->where('entry_type', DistributionEntryType::Earning)
                ->where('beneficiary_type', $type)
                ->sum('amount'));

            return [
                'loan_id' => $loan->getKey(),
                'loan_code' => $loan->loan_code,
                'borrower' => $loan->borrower?->full_name ?? '—',
                'collected' => Money::round($sumPayments($collectedPaymentIds)),
                'investor' => $amountFor(DistributionBeneficiaryType::Investor),
                'admin' => $amountFor(DistributionBeneficiaryType::Admin),
                'company' => $amountFor(DistributionBeneficiaryType::Company),
                'reversed' => Money::round($sumPayments($reversedPaymentIds)),
            ];
        })->values()->all();
    }

    private function netFor(Builder $earnings, DistributionBeneficiaryType $type, Builder $reversals): string
    {
        return Money::round(Money::sub(
            (string) $earnings->where('beneficiary_type', $type->value)->sum('amount'),
            (string) $reversals->where('beneficiary_type', $type->value)->sum('amount'),
        ));
    }

    private function totalFor(Builder $query, PayoutStatus $status): string
    {
        return Money::round((string) $query->where('status', $status->value)->sum('net_amount'));
    }

    private function pendingFor(Builder $query): string
    {
        return $this->totalFor($query, PayoutStatus::Pending);
    }
}
