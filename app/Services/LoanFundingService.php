<?php

namespace App\Services;

use App\Enums\AllocationStatus;
use App\Enums\CommitmentStatus;
use App\Enums\DepositStatus;
use App\Enums\InvestorStatus;
use App\Enums\LoanFundingStatus;
use App\Enums\LoanStatus;
use App\Models\Investor;
use App\Models\InvestorAllocation;
use App\Models\Loan;
use App\Models\LoanFundingCommitment;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoanFundingService
{
    /**
     * @return array{required: string, committed: string, remaining: string, percent: float, investors: int, status: LoanFundingStatus}
     */
    public function progress(Loan $loan): array
    {
        $committed = $this->activeCommitted($loan->getKey());
        $required = (string) $loan->principal_amount;
        $remaining = Money::sub($required, $committed);

        if (Money::compare($remaining, '0') < 0) {
            $remaining = '0';
        }

        $remaining = Money::round($remaining);

        $percent = Money::compare($required, '0') > 0
            ? min(100.0, round(((float) $committed / (float) $required) * 100, 1))
            : 0.0;

        return [
            'required' => Money::round($required),
            'committed' => Money::round($committed),
            'remaining' => $remaining,
            'percent' => $percent,
            'investors' => max(
                $loan->commitments()
                    ->whereIn('status', [CommitmentStatus::Confirmed->value, CommitmentStatus::Allocated->value])
                    ->count(),
                InvestorAllocation::query()
                    ->where('loan_id', $loan->getKey())
                    ->where('status', AllocationStatus::Active->value)
                    ->distinct('investor_id')
                    ->count('investor_id'),
            ),
            'status' => $loan->funding_status ?? LoanFundingStatus::Unfunded,
        ];
    }

    public function commit(Loan $loan, Investor $investor, string|int $amount, ?string $reference, ?string $notes, User $actor): LoanFundingCommitment
    {
        return DB::transaction(function () use ($loan, $investor, $amount, $reference, $notes, $actor): LoanFundingCommitment {
            /** @var Loan $locked */
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->getKey());

            $this->ensureFundingOpen($locked);

            if ($investor->status !== InvestorStatus::Active) {
                throw new InvalidArgumentException('Only active investors can commit capital.');
            }

            $amount = Money::round($amount);

            if (Money::compare($amount, '0') <= 0) {
                throw new InvalidArgumentException('The commitment amount must be greater than zero.');
            }

            /** @var LoanFundingCommitment|null $existing */
            $existing = $locked->commitments()
                ->where('investor_id', $investor->getKey())
                ->lockForUpdate()
                ->first();

            $existingConfirmed = $existing && $existing->status === CommitmentStatus::Confirmed
                ? (string) $existing->amount
                : '0';

            $committed = $this->activeCommitted($locked->getKey());
            $remaining = Money::sub((string) $locked->principal_amount, Money::sub($committed, $existingConfirmed));

            if (Money::compare($amount, $remaining) > 0) {
                throw new InvalidArgumentException(
                    'Commitment exceeds the remaining funding requirement of '.Money::format($remaining).'.'
                );
            }

            $commitment = LoanFundingCommitment::query()->updateOrCreate(
                ['loan_id' => $locked->getKey(), 'investor_id' => $investor->getKey()],
                [
                    'amount' => $amount,
                    'status' => CommitmentStatus::Confirmed,
                    'confirmed_at' => now(),
                    'withdrawn_at' => null,
                    'transaction_reference' => $reference,
                    'confirmed_by' => $actor->getKey(),
                    'notes' => $notes,
                ],
            );

            $this->refreshFundingStatus($locked, $actor);

            AuditLogger::log('funding.committed', $commitment, [], [
                'loan_id' => $locked->getKey(),
                'investor_id' => $investor->getKey(),
                'amount' => $amount,
            ]);

            return $commitment->refresh();
        });
    }

    public function withdraw(Loan $loan, Investor $investor, User $actor): void
    {
        DB::transaction(function () use ($loan, $investor, $actor): void {
            /** @var Loan $locked */
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->getKey());

            $this->ensureFundingOpen($locked);

            /** @var LoanFundingCommitment|null $commitment */
            $commitment = $locked->commitments()
                ->where('investor_id', $investor->getKey())
                ->lockForUpdate()
                ->first();

            if (! $commitment || $commitment->status !== CommitmentStatus::Confirmed) {
                throw new InvalidArgumentException('No confirmed commitment exists for this investor on this loan.');
            }

            $commitment->update([
                'status' => CommitmentStatus::Withdrawn,
                'withdrawn_at' => now(),
            ]);

            $this->refreshFundingStatus($locked, $actor);

            AuditLogger::log('funding.withdrawn', $commitment, [], [
                'loan_id' => $locked->getKey(),
                'investor_id' => $investor->getKey(),
                'amount' => (string) $commitment->amount,
            ]);
        });
    }

    /**
     * Available Capital = Cleared Deposits - Active Allocations - Other Valid Holds.
     * Pending or rejected deposits never count as available capital.
     */
    public function availableCapital(Investor $investor): string
    {
        $deposited = (string) $investor->deposits()
            ->where('status', DepositStatus::Cleared->value)
            ->sum('amount');

        $allocated = (string) $investor->allocations()
            ->where('status', AllocationStatus::Active->value)
            ->sum('principal_amount');

        return Money::round(Money::sub($deposited, $allocated));
    }

    /**
     * Per-investor capital verification report for a loan requirement.
     *
     * @return array{
     *     required: string,
     *     total_deposited: string,
     *     total_allocated: string,
     *     total_available: string,
     *     shortage: string,
     *     sufficient: bool,
     *     investors: array<int, array{id: int, name: string, code: string, deposited: string, allocated: string, available: string, status: string}>
     * }
     */
    public function capitalReport(Loan $loan): array
    {
        $required = Money::round((string) $loan->principal_amount);

        $investors = Investor::query()->where('status', InvestorStatus::Active->value)->orderBy('name')->get();

        $rows = $investors->map(function (Investor $investor): array {
            $deposited = Money::round((string) $investor->deposits()->where('status', DepositStatus::Cleared->value)->sum('amount'));
            $allocated = Money::round((string) $investor->allocations()->where('status', AllocationStatus::Active->value)->sum('principal_amount'));
            $available = Money::sub($deposited, $allocated);

            return [
                'id' => $investor->getKey(),
                'name' => $investor->name,
                'code' => $investor->investor_code,
                'deposited' => $deposited,
                'allocated' => $allocated,
                'available' => Money::compare($available, '0') > 0 ? $available : '0.00',
                'status' => $investor->status->value,
            ];
        })->values()->all();

        $totalAvailable = Money::round(array_reduce(
            $rows,
            fn (string $carry, array $row): string => Money::add($carry, $row['available']),
            '0',
        ));

        $shortage = Money::compare($required, $totalAvailable) > 0
            ? Money::round(Money::sub($required, $totalAvailable))
            : '0.00';

        return [
            'required' => $required,
            'total_deposited' => Money::round(array_reduce($rows, fn (string $carry, array $row): string => Money::add($carry, $row['deposited']), '0')),
            'total_allocated' => Money::round(array_reduce($rows, fn (string $carry, array $row): string => Money::add($carry, $row['allocated']), '0')),
            'total_available' => $totalAvailable,
            'shortage' => $shortage,
            'sufficient' => Money::compare($shortage, '0') <= 0,
            'investors' => $rows,
        ];
    }

    public function markCapitalVerified(Loan $loan, User $actor): Loan
    {
        return DB::transaction(function () use ($loan, $actor): Loan {
            /** @var Loan $locked */
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->getKey());

            if (! $locked->application_status->allowsProcessing()) {
                throw new InvalidArgumentException('Capital verification runs after the borrower accepts the loan offer.');
            }

            $locked->forceFill([
                'capital_verified_at' => now(),
                'capital_verified_by' => $actor->getKey(),
            ])->save();

            $report = $this->capitalReport($locked);

            AuditLogger::log('capital.verified', $locked, [], [
                'loan_id' => $locked->getKey(),
                'required' => $report['required'],
                'total_available' => $report['total_available'],
                'sufficient' => $report['sufficient'],
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Reserve investor capital against a loan. The total must match the approved
     * amount exactly and no investor may allocate more than their available capital.
     *
     * @param  array<int|string, string>  $lines  investor id => amount
     */
    public function allocateCapital(Loan $loan, array $lines, User $actor): void
    {
        $lines = collect($lines)
            ->map(fn ($amount, $id): array => ['investor_id' => (int) $id, 'amount' => filled($amount) ? Money::round($amount) : '0.00'])
            ->filter(fn (array $line): bool => Money::compare($line['amount'], '0') > 0)
            ->values()
            ->all();

        if ($lines === []) {
            throw new InvalidArgumentException('Enter at least one investor contribution.');
        }

        DB::transaction(function () use ($loan, $lines, $actor): void {
            /** @var Loan $locked */
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->getKey());

            if (! $locked->application_status->allowsProcessing()) {
                throw new InvalidArgumentException('The borrower must accept the loan offer before capital can be allocated.');
            }

            if ($locked->capital_verified_at === null) {
                throw new InvalidArgumentException('Run investor capital verification before allocating capital.');
            }

            $this->ensureFundingOpen($locked);

            if ($locked->allocations()->where('status', AllocationStatus::Active->value)->exists()) {
                throw new InvalidArgumentException('Capital has already been allocated for this loan.');
            }

            // Serialize concurrent allocations against the same investor (sorted to avoid deadlocks).
            $investorIds = collect($lines)->pluck('investor_id')->unique()->sort()->values();
            Investor::query()->whereIn('id', $investorIds)->lockForUpdate()->get();

            $total = '0.00';

            foreach ($lines as $line) {
                /** @var Investor|null $investor */
                $investor = Investor::query()->find($line['investor_id']);

                if (! $investor || $investor->status !== InvestorStatus::Active) {
                    throw new InvalidArgumentException('Every allocation must reference an active investor.');
                }

                $available = $this->availableCapital($investor);

                if (Money::compare($available, '0') < 0) {
                    $available = '0.00';
                }

                if (Money::compare($line['amount'], $available) > 0) {
                    throw new InvalidArgumentException(
                        $investor->name.' only has '.Money::format($available).' available to allocate.'
                    );
                }

                $total = Money::add($total, $line['amount']);
            }

            $required = Money::round((string) $locked->principal_amount);

            if (Money::compare($total, $required) !== 0) {
                throw new InvalidArgumentException(
                    'Allocated total '.Money::format($total).' must exactly match the approved amount '.Money::format($required).'.'
                );
            }

            foreach ($lines as $line) {
                $locked->allocations()->create([
                    'investor_id' => $line['investor_id'],
                    'principal_amount' => $line['amount'],
                    'allocation_date' => now()->toDateString(),
                    'status' => AllocationStatus::Active,
                    'notes' => 'Capital allocation for '.$locked->loan_code,
                ]);
            }

            $this->refreshFundingStatus($locked, $actor);

            AuditLogger::log('capital.allocated', $locked, [], [
                'loan_id' => $locked->getKey(),
                'total' => $total,
                'lines' => $lines,
            ]);
        });
    }

    private function ensureFundingOpen(Loan $loan): void
    {
        if ($loan->status !== LoanStatus::Pending) {
            throw new InvalidArgumentException('Funding is only open while the loan awaits disbursement.');
        }

        if ($loan->funding_locked_at !== null || $loan->funding_status === LoanFundingStatus::FullyFunded) {
            throw new InvalidArgumentException('This loan is fully funded. Funding is locked and can no longer change.');
        }
    }

    public function refreshFundingStatus(Loan $loan, ?User $actor = null): LoanFundingStatus
    {
        $committed = $this->activeCommitted($loan->getKey());

        $status = match (true) {
            Money::isZero($committed) => LoanFundingStatus::Unfunded,
            Money::compare($committed, (string) $loan->principal_amount) >= 0 => LoanFundingStatus::FullyFunded,
            default => LoanFundingStatus::PartiallyFunded,
        };

        $lockedNow = $status === LoanFundingStatus::FullyFunded && $loan->funding_locked_at === null;

        $loan->forceFill([
            'funding_status' => $status,
            'funding_locked_at' => $lockedNow ? now() : $loan->funding_locked_at,
        ])->save();

        if ($lockedNow) {
            $this->allocateOnFullFunding($loan);

            AuditLogger::log('funding.fully_funded', $loan, [
                'funding_status' => LoanFundingStatus::PartiallyFunded->value,
            ], [
                'funding_status' => LoanFundingStatus::FullyFunded->value,
                'committed' => Money::round($committed),
            ]);
        }

        return $status;
    }

    private function activeCommitted(int $loanId): string
    {
        $commitments = (string) LoanFundingCommitment::query()
            ->where('loan_id', $loanId)
            ->whereIn('status', [CommitmentStatus::Confirmed->value, CommitmentStatus::Allocated->value])
            ->sum('amount');

        // Loans disbursed before funding tracking existed typically carry manual allocations; treat them as committed capital.
        $allocations = (string) InvestorAllocation::query()
            ->where('loan_id', $loanId)
            ->where('status', AllocationStatus::Active->value)
            ->sum('principal_amount');

        return Money::compare($commitments, $allocations) >= 0 ? $commitments : $allocations;
    }

    private function allocateOnFullFunding(Loan $loan): void
    {
        $loan->commitments()->confirmed()->get()->each(function (LoanFundingCommitment $commitment) use ($loan): void {
            $loan->allocations()->create([
                'investor_id' => $commitment->investor_id,
                'principal_amount' => $commitment->amount,
                'allocation_date' => now()->toDateString(),
                'status' => AllocationStatus::Active,
                'notes' => 'Auto-generated from funding commitment #'.$commitment->getKey(),
            ]);

            $commitment->update(['status' => CommitmentStatus::Allocated]);
        });
    }
}
