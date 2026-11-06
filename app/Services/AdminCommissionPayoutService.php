<?php

namespace App\Services;

use App\Enums\DistributionStatus;
use App\Enums\PayoutStatus;
use App\Models\AdminCommissionPayout;
use App\Models\AdminCommissionPayoutItem;
use App\Models\InterestDistributionLedger;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdminCommissionPayoutService
{
    public function __construct(
        private readonly JournalService $journal,
    ) {}

    public function generate(CarbonImmutable $month, ?User $actor = null): int
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        $entries = InterestDistributionLedger::query()
            ->forAdmins()
            ->whereBetween('period_month', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('admin_id')
            ->whereDoesntHave('adminCommissionPayoutItem')
            ->orderBy('period_month')
            ->orderBy('id')
            ->get()
            ->groupBy('admin_id');

        if ($entries->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($entries, $start, $actor): int {
            $generated = 0;

            foreach ($entries as $userId => $adminEntries) {
                $user = User::query()->lockForUpdate()->find($userId);

                if ($user === null) {
                    continue;
                }

                $net = $adminEntries->reduce(
                    fn (string $carry, InterestDistributionLedger $entry): string => Money::add($carry, $entry->signedAmount()),
                    '0',
                );

                if (Money::compare($net, '0') <= 0) {
                    continue;
                }

                $payout = AdminCommissionPayout::query()
                    ->where('user_id', $userId)
                    ->whereDate('period_month', $start->toDateString())
                    ->where('status', PayoutStatus::Pending->value)
                    ->lockForUpdate()
                    ->first();

                if ($payout === null) {
                    $batchNo = ((int) AdminCommissionPayout::query()
                        ->where('user_id', $userId)
                        ->whereDate('period_month', $start->toDateString())
                        ->max('batch_no')) + 1;

                    $payout = new AdminCommissionPayout([
                        'user_id' => $userId,
                        'period_month' => $start->toDateString(),
                        'batch_no' => $batchNo,
                        'status' => PayoutStatus::Pending,
                        'created_by' => $actor?->getKey() ?? auth()->id(),
                    ]);
                    $payout->save();
                }

                foreach ($adminEntries as $entry) {
                    AdminCommissionPayoutItem::query()->create([
                        'admin_commission_payout_id' => $payout->getKey(),
                        'distribution_ledger_id' => $entry->getKey(),
                        'loan_id' => $entry->loan_id,
                        'user_id' => $userId,
                        'amount' => $entry->signedAmount(),
                    ]);
                }

                $total = Money::round((string) $payout->items()->sum('amount'));

                $payout->fill([
                    'gross_commission' => $total,
                    'net_amount' => $total,
                ])->save();

                AuditLogger::log('admin_commission.generated', $payout, [], [
                    'user_id' => $userId,
                    'period_month' => $start->toDateString(),
                    'amount' => $total,
                ]);

                $generated++;
            }

            return $generated;
        });
    }

    public function approve(AdminCommissionPayout $payout, User $actor): AdminCommissionPayout
    {
        return DB::transaction(function () use ($payout, $actor): AdminCommissionPayout {
            /** @var AdminCommissionPayout $locked */
            $locked = AdminCommissionPayout::query()->lockForUpdate()->findOrFail($payout->getKey());

            if (! $locked->status->canBeReviewed()) {
                throw new InvalidArgumentException('Only pending commission payouts can be approved.');
            }

            $locked->forceFill([
                'status' => PayoutStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $actor->getKey(),
            ])->save();

            AuditLogger::log('admin_commission.approved', $locked, [], ['payout_code' => $locked->payout_code]);

            return $locked->refresh();
        });
    }

    public function reject(AdminCommissionPayout $payout, User $actor, string $reason): AdminCommissionPayout
    {
        return DB::transaction(function () use ($payout, $actor, $reason): AdminCommissionPayout {
            /** @var AdminCommissionPayout $locked */
            $locked = AdminCommissionPayout::query()->lockForUpdate()->findOrFail($payout->getKey());

            if (in_array($locked->status, [PayoutStatus::Paid, PayoutStatus::Rejected, PayoutStatus::Cancelled], true)) {
                throw new InvalidArgumentException('This commission payout can no longer be rejected.');
            }

            $locked->items()->delete();

            $locked->forceFill([
                'status' => PayoutStatus::Rejected,
                'rejected_at' => now(),
                'rejected_by' => $actor->getKey(),
                'rejection_reason' => $reason,
            ])->save();

            AuditLogger::log('admin_commission.rejected', $locked, [], ['payout_code' => $locked->payout_code, 'reason' => $reason]);

            return $locked->refresh();
        });
    }

    public function process(AdminCommissionPayout $payout, User $actor, string $reference, CarbonImmutable|string $paidAt): AdminCommissionPayout
    {
        return DB::transaction(function () use ($payout, $actor, $reference, $paidAt): AdminCommissionPayout {
            /** @var AdminCommissionPayout $locked */
            $locked = AdminCommissionPayout::query()->lockForUpdate()->findOrFail($payout->getKey());

            if (! $locked->status->canBeProcessed()) {
                throw new InvalidArgumentException('Only approved commission payouts can be processed.');
            }

            $alreadyPosted = JournalEntry::query()
                ->where('reference_type', $locked->getMorphClass())
                ->where('reference_id', $locked->getKey())
                ->exists();

            if ($alreadyPosted) {
                throw new InvalidArgumentException('This commission payout has already been posted to the ledger.');
            }

            $date = $paidAt instanceof CarbonImmutable ? $paidAt : CarbonImmutable::parse($paidAt);

            $this->journal->post(
                $date,
                'Admin commission '.$locked->payout_code.' to '.$locked->user->name,
                [
                    ['code' => '2300', 'debit' => (string) $locked->net_amount, 'narration' => 'Admin commission '.$locked->payout_code],
                    ['code' => '1000', 'credit' => (string) $locked->net_amount, 'narration' => 'Cash disbursed '.$locked->payout_code],
                ],
                $locked,
                $actor->getKey(),
            );

            $locked->forceFill([
                'status' => PayoutStatus::Paid,
                'paid_at' => $date,
                'processed_by' => $actor->getKey(),
                'payment_reference' => $reference,
            ])->save();

            $locked->items()->with('ledger')->get()->each(function (AdminCommissionPayoutItem $item): void {
                $item->ledger?->forceFill(['status' => DistributionStatus::Paid])->save();
            });

            AuditLogger::log('admin_commission.paid', $locked, [], [
                'payout_code' => $locked->payout_code,
                'reference' => $reference,
            ]);

            return $locked->refresh();
        });
    }

    public function cancel(AdminCommissionPayout $payout, ?User $actor = null): AdminCommissionPayout
    {
        return DB::transaction(function () use ($payout, $actor): AdminCommissionPayout {
            /** @var AdminCommissionPayout $locked */
            $locked = AdminCommissionPayout::query()->lockForUpdate()->findOrFail($payout->getKey());

            if (! $locked->status->canBeReviewed()) {
                throw new InvalidArgumentException('Only pending commission payouts can be cancelled.');
            }

            $locked->items()->delete();
            $locked->forceFill([
                'status' => PayoutStatus::Cancelled,
                'notes' => trim(($locked->notes ? $locked->notes.' | ' : '').'Cancelled by '.($actor?->name ?? 'system')),
            ])->save();

            AuditLogger::log('admin_commission.cancelled', $locked, [], ['payout_code' => $locked->payout_code]);

            return $locked->refresh();
        });
    }
}
