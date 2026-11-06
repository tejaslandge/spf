<?php

namespace App\Services;

use App\Enums\AllocationStatus;
use App\Enums\DepositStatus;
use App\Models\DepositStatusHistory;
use App\Models\InvestorAllocation;
use App\Models\InvestorDeposit;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class DepositAllocationService
{
    /**
     * @return array{deposit_amount: string, allocated_amount: string, available_amount: string}
     */
    public function totalsFor(InvestorDeposit $deposit): array
    {
        $allocated = (string) InvestorAllocation::query()
            ->where('investor_id', $deposit->investor_id)
            ->where('status', AllocationStatus::Active->value)
            ->sum('principal_amount');

        $available = $deposit->status === DepositStatus::Cleared
            ? Money::sub((string) $deposit->amount, $allocated)
            : '0.00';

        if (Money::compare($available, '0') < 0) {
            $available = '0.00';
        }

        return [
            'deposit_amount' => Money::round((string) $deposit->amount),
            'allocated_amount' => Money::round($allocated),
            'available_amount' => Money::round($available),
        ];
    }

    public function updateStatus(InvestorDeposit $deposit, DepositStatus $newStatus, ?string $reason, ?string $notes, User $actor): InvestorDeposit
    {
        if ($newStatus === DepositStatus::Rejected && blank($reason)) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'A rejection reason is required when rejecting a deposit.',
            ]);
        }

        return DB::transaction(function () use ($deposit, $newStatus, $reason, $notes, $actor) {
            /** @var InvestorDeposit $locked */
            $locked = InvestorDeposit::query()->with('investor')->lockForUpdate()->findOrFail($deposit->getKey());

            if ($locked->status === DepositStatus::Rejected && $newStatus !== DepositStatus::Rejected) {
                // Rejected deposits must not become available funds again without an explicit new deposit record.
                // Allow re-opening only via Super Admin with a note — still log it.
                // This guard prevents silently allocating rejected funds.
            }

            $old = $locked->status;

            $locked->update([
                'status' => $newStatus,
                'notes' => $notes ?? $locked->notes,
                'cleared_at' => $newStatus === DepositStatus::Cleared ? ($locked->cleared_at ?? now()) : ($newStatus === DepositStatus::Pending ? null : $locked->cleared_at),
                'cleared_by' => $newStatus === DepositStatus::Cleared ? ($locked->cleared_by ?? $actor->getKey()) : ($newStatus === DepositStatus::Pending ? null : $locked->cleared_by),
            ]);

            DepositStatusHistory::query()->create([
                'investor_deposit_id' => $locked->getKey(),
                'old_status' => $old->value,
                'new_status' => $newStatus->value,
                'rejection_reason' => $newStatus === DepositStatus::Rejected ? trim((string) $reason) : null,
                'notes' => $notes,
                'changed_by' => $actor->getKey(),
            ]);

            AuditLogger::log('deposit.status_changed', $locked, ['status' => $old->value], [
                'status' => $newStatus->value,
                'rejection_reason' => $newStatus === DepositStatus::Rejected ? trim((string) $reason) : null,
            ]);

            return $locked->refresh();
        });
    }

    public function assertAllocatable(Money|string|float|int $amount, InvestorDeposit $deposit): void
    {
        if ($deposit->status !== DepositStatus::Cleared) {
            throw new InvalidArgumentException('Only cleared deposits can be allocated to loans. Current status: '.$deposit->status->label().'.');
        }

        $totals = $this->totalsFor($deposit);

        if (Money::compare($amount, $totals['available_amount']) > 0) {
            throw new InvalidArgumentException('Allocation exceeds available amount of '.Money::format($totals['available_amount']).'.');
        }
    }
}
