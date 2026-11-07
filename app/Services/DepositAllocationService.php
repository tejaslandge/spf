<?php

namespace App\Services;

use App\Enums\AllocationStatus;
use App\Enums\DepositStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\DepositStatusHistory;
use App\Models\InvestorAllocation;
use App\Models\InvestorDeposit;
use App\Models\User;
use App\Services\Notifications\StaffNotifier;
use App\Services\Settings\SettingsService;
use App\Support\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class DepositAllocationService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly StaffNotifier $notifier,
    ) {}

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

        if ($newStatus === DepositStatus::Cleared
            && $this->settings->getBool('deposit.verification_required', true)
            && ! $actor->isSuperAdmin()
            && $actor->role !== UserRole::Admin) {
            throw ValidationException::withMessages([
                'status' => 'Deposit verification requires a Super Admin or Admin to clear this deposit.',
            ]);
        }

        return DB::transaction(function () use ($deposit, $newStatus, $reason, $notes, $actor) {
            /** @var InvestorDeposit $locked */
            $locked = InvestorDeposit::query()->with('investor')->lockForUpdate()->findOrFail($deposit->getKey());

            if ($locked->status === DepositStatus::Rejected
                && $newStatus === DepositStatus::Cleared
                && ! $this->settings->getBool('deposit.allow_reclear_rejected', false)) {
                throw new InvalidArgumentException('A rejected deposit cannot be cleared again. Record a new deposit instead.');
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

            $headline = match ($newStatus) {
                DepositStatus::Cleared => __('Investor deposit cleared'),
                DepositStatus::Rejected => __('Investor deposit rejected'),
                default => __('Investor deposit set to pending'),
            };

            $this->notifier->notifyPermission(
                Permission::DepositsVerify,
                'deposit_updates',
                $headline,
                $locked->investor->name.' · '.Money::format((string) $locked->amount),
                route('super-admin.deposit-allocation.index'),
                $actor,
            );

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
