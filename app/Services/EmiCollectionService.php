<?php

namespace App\Services;

use App\Enums\EmiStatus;
use App\Enums\LoanStatus;
use App\Models\EmiPayment;
use App\Models\EmiPaymentAllocation;
use App\Models\EmiSchedule;
use App\Models\Loan;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EmiCollectionService
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly InterestDistributionService $distribution,
    ) {}

    /**
     * @param  array{amount: string, payment_date?: string, payment_method?: string, reference?: string|null, notes?: string|null, penalty_amount?: string}  $data
     */
    public function collect(Loan $loan, array $data, User $actor): EmiPayment
    {
        if ($loan->status !== LoanStatus::Active) {
            throw new InvalidArgumentException('EMIs can only be collected against active loans.');
        }

        $amount = Money::round($data['amount']);

        if (Money::compare($amount, '0') <= 0) {
            throw new InvalidArgumentException('The payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($loan, $data, $actor, $amount): EmiPayment {
            $loan = Loan::query()->whereKey($loan->getKey())->lockForUpdate()->firstOrFail();

            $schedules = $loan->schedules()
                ->unsettled()
                ->orderBy('installment_no')
                ->lockForUpdate()
                ->get();

            $totalDue = $schedules->reduce(
                fn (string $carry, EmiSchedule $schedule) => Money::add(
                    $carry,
                    Money::sub($schedule->total_amount, $schedule->paid_amount),
                ),
                '0',
            );

            if (Money::compare($amount, $totalDue) > 0) {
                throw new InvalidArgumentException('The payment exceeds the outstanding EMI balance.');
            }

            $remaining = $amount;
            $principalPaid = '0';
            $interestPaid = '0';
            $firstScheduleId = null;
            /** @var array<int, array{schedule_id: int, amount: string, principal: string, interest: string}> $allocationRows */
            $allocationRows = [];

            foreach ($schedules as $schedule) {
                if (Money::isZero($remaining)) {
                    break;
                }

                $interestRemaining = Money::sub($schedule->interest_amount, $schedule->paid_interest);
                $principalRemaining = Money::sub($schedule->principal_amount, $schedule->paid_principal);
                $scheduleRemaining = Money::add($interestRemaining, $principalRemaining);

                $applied = Money::compare($remaining, $scheduleRemaining) >= 0
                    ? $scheduleRemaining
                    : $remaining;

                $appliedInterest = Money::compare($applied, $interestRemaining) >= 0
                    ? $interestRemaining
                    : $applied;

                $appliedPrincipal = Money::sub($applied, $appliedInterest);

                $schedule->paid_interest = Money::add($schedule->paid_interest, $appliedInterest);
                $schedule->paid_principal = Money::add($schedule->paid_principal, $appliedPrincipal);
                $schedule->paid_amount = Money::add($schedule->paid_amount, $applied);

                if (Money::compare($schedule->paid_amount, $schedule->total_amount) >= 0) {
                    $schedule->status = EmiStatus::Paid;
                    $schedule->paid_at = now();
                } else {
                    $schedule->status = EmiStatus::Partial;
                }

                $schedule->save();

                $firstScheduleId ??= $schedule->id;
                $allocationRows[] = [
                    'schedule_id' => $schedule->id,
                    'amount' => $applied,
                    'principal' => $appliedPrincipal,
                    'interest' => $appliedInterest,
                ];
                $remaining = Money::sub($remaining, $applied);
                $principalPaid = Money::add($principalPaid, $appliedPrincipal);
                $interestPaid = Money::add($interestPaid, $appliedInterest);
            }

            $payment = $loan->payments()->create([
                'emi_schedule_id' => $firstScheduleId,
                'amount' => $amount,
                'principal_component' => $principalPaid,
                'interest_component' => $interestPaid,
                'penalty_amount' => Money::round($data['penalty_amount'] ?? 0),
                'payment_date' => $data['payment_date'] ?? today(),
                'payment_method' => $data['payment_method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'collected_by' => $actor->id,
            ]);

            foreach ($allocationRows as $row) {
                EmiPaymentAllocation::query()->create([
                    'emi_payment_id' => $payment->getKey(),
                    'emi_schedule_id' => $row['schedule_id'],
                    'amount' => $row['amount'],
                    'principal_amount' => $row['principal'],
                    'interest_amount' => $row['interest'],
                ]);
            }

            $loan->outstanding_principal = Money::sub($loan->outstanding_principal, $principalPaid);
            $loan->outstanding_interest = Money::sub($loan->outstanding_interest, $interestPaid);

            if ($loan->isFullyPaid()) {
                $loan->status = LoanStatus::Closed;
                $loan->closed_at = now();
            }

            $loan->save();

            $lines = [
                ['code' => '1000', 'debit' => $amount, 'narration' => 'EMI received '.$payment->receipt_no],
                ['code' => '1100', 'credit' => $principalPaid, 'narration' => 'Principal repayment '.$loan->loan_code],
                ['code' => '4000', 'credit' => $interestPaid, 'narration' => 'Interest income '.$loan->loan_code],
            ];

            $penalty = Money::round($data['penalty_amount'] ?? 0);

            if (! Money::isZero($penalty)) {
                $lines[] = ['code' => '4100', 'credit' => $penalty, 'narration' => 'Penalty '.$loan->loan_code];
            }

            $this->journal->post(
                $data['payment_date'] ?? today(),
                'EMI collection '.$payment->receipt_no,
                $lines,
                $payment,
                $actor->id,
            );

            $this->distribution->distributeForPayment($payment->refresh(), $actor);

            return $payment->load('schedule', 'loan');
        });
    }

    /**
     * Reverse an EMI receipt without deleting history. Schedules, the loan balance,
     * the journal and every distribution entitlement receive compensating entries.
     */
    public function reversePayment(EmiPayment $payment, User $actor, string $reason): EmiPayment
    {
        return DB::transaction(function () use ($payment, $actor, $reason): EmiPayment {
            /** @var EmiPayment $locked */
            $locked = EmiPayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->is_reversed) {
                throw new InvalidArgumentException('This payment has already been reversed.');
            }

            /** @var Loan $loan */
            $loan = Loan::query()->lockForUpdate()->findOrFail($locked->loan_id);

            $allocations = $locked->allocations()->lockForUpdate()->get();

            if ($allocations->isEmpty()) {
                throw new InvalidArgumentException('This legacy payment has no schedule allocation detail and cannot be reversed automatically.');
            }

            foreach ($allocations as $allocation) {
                /** @var EmiSchedule $schedule */
                $schedule = EmiSchedule::query()->lockForUpdate()->findOrFail($allocation->emi_schedule_id);

                $schedule->paid_amount = $this->subtractFloor($schedule->paid_amount, $allocation->amount);
                $schedule->paid_principal = $this->subtractFloor($schedule->paid_principal, $allocation->principal_amount);
                $schedule->paid_interest = $this->subtractFloor($schedule->paid_interest, $allocation->interest_amount);

                $schedule->status = Money::isZero($schedule->paid_amount)
                    ? EmiStatus::Pending
                    : EmiStatus::Partial;
                $schedule->paid_at = null;
                $schedule->save();
            }

            $loan->outstanding_principal = Money::add($loan->outstanding_principal, (string) $locked->principal_component);
            $loan->outstanding_interest = Money::add($loan->outstanding_interest, (string) $locked->interest_component);

            if ($loan->status === LoanStatus::Closed) {
                $loan->status = LoanStatus::Active;
                $loan->closed_at = null;
            }

            $loan->save();

            $this->distribution->reverseForPayment($locked, $actor, $reason);

            $lines = [
                ['code' => '1000', 'credit' => (string) $locked->amount, 'narration' => 'Reverse receipt '.$locked->receipt_no],
                ['code' => '1100', 'debit' => (string) $locked->principal_component, 'narration' => 'Reverse principal '.$loan->loan_code],
                ['code' => '4000', 'debit' => (string) $locked->interest_component, 'narration' => 'Reverse interest '.$loan->loan_code],
            ];

            if (Money::compare($locked->penalty_amount, '0') > 0) {
                $lines[] = ['code' => '4100', 'debit' => (string) $locked->penalty_amount, 'narration' => 'Reverse penalty '.$loan->loan_code];
            }

            $this->journal->post(
                CarbonImmutable::today(),
                'EMI reversal '.$locked->receipt_no,
                $lines,
                $locked,
                $actor->getKey(),
            );

            $locked->forceFill([
                'is_reversed' => true,
                'reversed_at' => now(),
                'reversed_by' => $actor->getKey(),
                'reversal_reason' => $reason,
            ])->save();

            AuditLogger::log('emi.reversed', $locked, [], [
                'receipt_no' => $locked->receipt_no,
                'reason' => $reason,
                'amount' => (string) $locked->amount,
            ]);

            return $locked->refresh();
        });
    }

    private function subtractFloor(string|int|float $value, string|int|float $subtract): string
    {
        $result = Money::sub($value, $subtract);

        return Money::compare($result, '0') < 0 ? '0.00' : Money::round($result);
    }
}
