<?php

namespace App\Services;

use App\Enums\DistributionStatus;
use App\Enums\PayoutStatus;
use App\Models\InterestDistributionLedger;
use App\Models\Investor;
use App\Models\InvestorPayout;
use App\Models\InvestorPayoutItem;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvestorPayoutService
{
    public function __construct(
        private readonly JournalService $journal,
    ) {}

    /**
     * Generate investor payouts from unlinked distribution ledger entries only.
     * Entries are created from actual collected interest, never from scheduled interest.
     */
    public function generate(CarbonImmutable $month, ?User $actor = null): int
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        $entries = InterestDistributionLedger::query()
            ->forInvestors()
            ->whereBetween('period_month', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('investor_id')
            ->whereDoesntHave('investorPayoutItem')
            ->orderBy('period_month')
            ->orderBy('id')
            ->get()
            ->groupBy('investor_id');

        if ($entries->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($entries, $start, $actor): int {
            $generated = 0;

            foreach ($entries as $investorId => $investorEntries) {
                /** @var Investor $investor */
                $investor = Investor::query()->lockForUpdate()->find($investorId);

                if ($investor === null) {
                    continue;
                }

                $net = $investorEntries->reduce(
                    fn (string $carry, InterestDistributionLedger $entry): string => Money::add($carry, $entry->signedAmount()),
                    '0',
                );

                if (Money::compare($net, '0') <= 0) {
                    continue;
                }

                $gross = Money::round($net);

                $payout = InvestorPayout::query()
                    ->where('investor_id', $investorId)
                    ->whereDate('period_month', $start->toDateString())
                    ->where('status', PayoutStatus::Pending->value)
                    ->lockForUpdate()
                    ->first();

                if ($payout !== null) {
                    $gross = Money::round(Money::add((string) $payout->items()->sum('amount'), $net));
                }

                if ($payout === null) {
                    $batchNo = ((int) InvestorPayout::query()
                        ->where('investor_id', $investorId)
                        ->whereDate('period_month', $start->toDateString())
                        ->max('batch_no')) + 1;

                    $payout = new InvestorPayout([
                        'investor_id' => $investorId,
                        'period_month' => $start->toDateString(),
                        'batch_no' => $batchNo,
                        'status' => PayoutStatus::Pending,
                        'created_by' => $actor?->getKey() ?? auth()->id(),
                    ]);
                }

                foreach ($investorEntries as $entry) {
                    $payout->save();

                    InvestorPayoutItem::query()->create([
                        'investor_payout_id' => $payout->getKey(),
                        'distribution_ledger_id' => $entry->getKey(),
                        'loan_id' => $entry->loan_id,
                        'investor_id' => $investorId,
                        'amount' => $entry->signedAmount(),
                    ]);
                }

                $managementFee = Money::round(Money::div(Money::mul($gross, (string) config('finance.investor.management_fee_percent', 10)), '100'));
                $tds = Money::round(Money::div(Money::mul($gross, (string) config('finance.investor.tds_percent', 10)), '100'));
                $netAmount = Money::round(Money::sub($gross, $managementFee, $tds));

                $payout->fill([
                    'gross_interest' => $gross,
                    'management_fee' => $managementFee,
                    'tds_amount' => $tds,
                    'net_amount' => $netAmount,
                ])->save();

                AuditLogger::log('investor_payout.generated', $payout, [], [
                    'investor_id' => $investorId,
                    'period_month' => $start->toDateString(),
                    'gross' => $gross,
                    'net' => $netAmount,
                    'entries' => $investorEntries->count(),
                ]);

                $generated++;
            }

            return $generated;
        });
    }

    public function approve(InvestorPayout $payout, User $actor): InvestorPayout
    {
        return DB::transaction(function () use ($payout, $actor): InvestorPayout {
            /** @var InvestorPayout $locked */
            $locked = InvestorPayout::query()->lockForUpdate()->findOrFail($payout->getKey());

            if (! $locked->status->canBeReviewed()) {
                throw new InvalidArgumentException('Only pending payouts can be approved.');
            }

            $locked->forceFill([
                'status' => PayoutStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $actor->getKey(),
            ])->save();

            AuditLogger::log('investor_payout.approved', $locked, [], [
                'payout_code' => $locked->payout_code,
                'net_amount' => (string) $locked->net_amount,
            ]);

            return $locked->refresh();
        });
    }

    public function reject(InvestorPayout $payout, User $actor, string $reason): InvestorPayout
    {
        return DB::transaction(function () use ($payout, $actor, $reason): InvestorPayout {
            /** @var InvestorPayout $locked */
            $locked = InvestorPayout::query()->lockForUpdate()->findOrFail($payout->getKey());

            if (in_array($locked->status, [PayoutStatus::Paid, PayoutStatus::Rejected, PayoutStatus::Cancelled], true)) {
                throw new InvalidArgumentException('This payout can no longer be rejected.');
            }

            $locked->items()->delete();

            $locked->forceFill([
                'status' => PayoutStatus::Rejected,
                'rejected_at' => now(),
                'rejected_by' => $actor->getKey(),
                'rejection_reason' => $reason,
            ])->save();

            AuditLogger::log('investor_payout.rejected', $locked, [], [
                'payout_code' => $locked->payout_code,
                'reason' => $reason,
            ]);

            return $locked->refresh();
        });
    }

    public function process(InvestorPayout $payout, User $actor, string $reference, CarbonImmutable|string $paidAt, ?string $notes = null): InvestorPayout
    {
        return DB::transaction(function () use ($payout, $actor, $reference, $paidAt, $notes): InvestorPayout {
            /** @var InvestorPayout $locked */
            $locked = InvestorPayout::query()->lockForUpdate()->findOrFail($payout->getKey());

            if (! $locked->status->canBeProcessed()) {
                throw new InvalidArgumentException('Only approved payouts can be processed.');
            }

            if (Money::compare($locked->net_amount, '0') <= 0) {
                throw new InvalidArgumentException('The payout has no payable amount.');
            }

            $alreadyPosted = JournalEntry::query()
                ->where('reference_type', $locked->getMorphClass())
                ->where('reference_id', $locked->getKey())
                ->exists();

            if ($alreadyPosted) {
                throw new InvalidArgumentException('This payout has already been posted to the ledger.');
            }

            $date = $paidAt instanceof CarbonImmutable ? $paidAt : CarbonImmutable::parse($paidAt);

            $lines = [
                ['code' => '2200', 'debit' => (string) $locked->gross_interest, 'narration' => 'Investor payout '.$locked->payout_code],
            ];

            if (Money::compare($locked->tds_amount, '0') > 0) {
                $lines[] = ['code' => '2100', 'credit' => (string) $locked->tds_amount, 'narration' => 'TDS withheld '.$locked->payout_code];
            }

            if (Money::compare($locked->management_fee, '0') > 0) {
                $lines[] = ['code' => '4200', 'credit' => (string) $locked->management_fee, 'narration' => 'Management fee '.$locked->payout_code];
            }

            $lines[] = ['code' => '1000', 'credit' => (string) $locked->net_amount, 'narration' => 'Cash disbursed '.$locked->payout_code];

            $this->journal->post(
                $date,
                'Investor payout '.$locked->payout_code.' to '.$locked->investor->name,
                $lines,
                $locked,
                $actor->getKey(),
            );

            $locked->forceFill([
                'status' => PayoutStatus::Paid,
                'paid_at' => $date,
                'processed_at' => now(),
                'processed_by' => $actor->getKey(),
                'payment_reference' => $reference,
                'notes' => filled($notes) ? $notes : $locked->notes,
            ])->save();

            $locked->items()->with('ledger')->get()->each(function (InvestorPayoutItem $item): void {
                $item->ledger?->forceFill(['status' => DistributionStatus::Paid])->save();
            });

            AuditLogger::log('investor_payout.paid', $locked, [], [
                'payout_code' => $locked->payout_code,
                'reference' => $reference,
                'net_amount' => (string) $locked->net_amount,
            ]);

            return $locked->refresh();
        });
    }

    public function cancel(InvestorPayout $payout, ?User $actor = null): InvestorPayout
    {
        return DB::transaction(function () use ($payout, $actor): InvestorPayout {
            /** @var InvestorPayout $locked */
            $locked = InvestorPayout::query()->lockForUpdate()->findOrFail($payout->getKey());

            if (! $locked->status->canBeReviewed()) {
                throw new InvalidArgumentException('Only pending payouts can be cancelled.');
            }

            $locked->items()->delete();

            $locked->forceFill([
                'status' => PayoutStatus::Cancelled,
                'notes' => trim(($locked->notes ? $locked->notes.' | ' : '').'Cancelled by '.($actor?->name ?? 'system')),
            ])->save();

            AuditLogger::log('investor_payout.cancelled', $locked, [], [
                'payout_code' => $locked->payout_code,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * @return Collection<int, InvestorPayout>
     */
    public function pendingFor(Investor $investor): Collection
    {
        return $investor->payouts()->where('status', PayoutStatus::Pending)->orderBy('period_month')->get();
    }
}
