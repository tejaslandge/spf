<?php

namespace App\Services;

use App\Enums\EmiStatus;
use App\Models\EmiSchedule;
use App\Models\Loan;
use Illuminate\Support\Facades\DB;

class LoanEmiSettlementService
{
    /**
     * Mark remaining unsettled EMI installments as Paid without creating payment transactions.
     *
     * Preserves already paid/waived installments and their payment details. Only unsettled
     * schedules (pending, partial, overdue) are updated. This is used when a loan is closed
     * or foreclosed so the EMI schedule reflects settlement without artificial cash receipts.
     *
     * @return int Number of schedules updated
     */
    public function settleRemainingUnpaidAsPaid(Loan $loan, string $settlementType): int
    {
        return DB::transaction(function () use ($loan, $settlementType): int {
            $schedules = EmiSchedule::query()
                ->where('loan_id', $loan->getKey())
                ->unsettled()
                ->lockForUpdate()
                ->get();

            $updated = 0;

            foreach ($schedules as $schedule) {
                $schedule->forceFill([
                    'status' => EmiStatus::Paid,
                    'settlement_type' => $settlementType,
                    'paid_at' => $schedule->paid_at ?? now(),
                ])->save();

                $updated++;
            }

            return $updated;
        });
    }
}
