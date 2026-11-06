<?php

namespace App\Services;

use App\Enums\DailyTaskPriority;
use App\Enums\DailyTaskStatus;
use App\Enums\DailyTaskType;
use App\Enums\KycStatus;
use App\Enums\LoanStatus;
use App\Models\Borrower;
use App\Models\DailyTask;
use App\Models\EmiSchedule;
use App\Models\Investor;
use App\Models\InvestorPayout;
use App\Models\KycDocument;
use App\Models\Loan;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DailyTaskGenerator
{
    /**
     * Idempotently create the system-derived tasks for a date. Returns the number created.
     */
    public function generateForDate(Carbon $date, ?int $userId = null): int
    {
        return $this->generateEmiDueReminders($date, $userId)
            + $this->generateOverdueFollowUps($date, $userId)
            + $this->generateKycReviews($date, $userId)
            + $this->generatePendingPayouts($date, $userId)
            + $this->generatePendingLoanApprovals($date, $userId);
    }

    private function generateEmiDueReminders(Carbon $date, ?int $userId): int
    {
        $created = 0;

        EmiSchedule::query()
            ->with('loan.borrower')
            ->unsettled()
            ->whereDate('due_date', $date->toDateString())
            ->orderBy('id')
            ->each(function (EmiSchedule $schedule) use (&$created, $date, $userId): void {
                $loan = $schedule->loan;
                $borrower = $loan?->borrower;

                $created += $this->createTask([
                    'task_date' => $date->toDateString(),
                    'type' => DailyTaskType::EmiReminder,
                    'title' => sprintf('Send EMI reminder — %s (%s)', $borrower?->full_name ?? 'Unknown borrower', $loan?->loan_code ?? 'Loan'),
                    'description' => sprintf(
                        'Installment #%d of %s due %s. Mobile: %s',
                        $schedule->installment_no,
                        Money::format($schedule->remainingAmount()),
                        $schedule->due_date->format('d M Y'),
                        $borrower?->mobile ?? '—'
                    ),
                    'priority' => DailyTaskPriority::Normal,
                    'related_type' => $schedule->getMorphClass(),
                    'related_id' => $schedule->getKey(),
                    'auto_generated' => true,
                    'dedupe_key' => 'emi-reminder-'.$schedule->getKey(),
                    'created_by' => $userId,
                ]);
            });

        return $created;
    }

    private function generateOverdueFollowUps(Carbon $date, ?int $userId): int
    {
        $created = 0;

        Loan::query()
            ->with(['borrower', 'schedules' => fn ($query) => $query->overdue()])
            ->where('status', LoanStatus::Active)
            ->whereHas('schedules', fn ($query) => $query->overdue())
            ->orderBy('id')
            ->each(function (Loan $loan) use (&$created, $date, $userId): void {
                /** @var Collection<int, EmiSchedule> $overdue */
                $overdue = $loan->schedules;
                $overdueTotal = $overdue->sum(fn (EmiSchedule $schedule): float => (float) $schedule->remainingAmount());
                $borrower = $loan->borrower;

                $created += $this->createTask([
                    'task_date' => $date->toDateString(),
                    'type' => DailyTaskType::OverdueFollowUp,
                    'title' => sprintf('Overdue follow-up — %s (%s)', $borrower?->full_name ?? 'Unknown borrower', $loan->loan_code),
                    'description' => sprintf(
                        '%d overdue installment(s) totalling %s. Oldest due %s. Mobile: %s',
                        $overdue->count(),
                        Money::format((string) $overdueTotal),
                        $overdue->min('due_date')?->format('d M Y') ?? '—',
                        $borrower?->mobile ?? '—'
                    ),
                    'priority' => DailyTaskPriority::High,
                    'related_type' => $loan->getMorphClass(),
                    'related_id' => $loan->getKey(),
                    'auto_generated' => true,
                    'dedupe_key' => 'overdue-'.$loan->getKey(),
                    'created_by' => $userId,
                ]);
            });

        return $created;
    }

    private function generateKycReviews(Carbon $date, ?int $userId): int
    {
        $created = 0;

        KycDocument::query()
            ->with('documentable')
            ->where('status', KycStatus::Pending->value)
            ->orderBy('id')
            ->each(function (KycDocument $document) use (&$created, $date, $userId): void {
                $owner = $document->documentable;
                $ownerName = match (true) {
                    $owner instanceof Borrower => $owner->full_name,
                    $owner instanceof Investor => $owner->name,
                    default => 'Unknown',
                };

                $created += $this->createTask([
                    'task_date' => $date->toDateString(),
                    'type' => DailyTaskType::KycReview,
                    'title' => sprintf('Verify KYC — %s (%s)', $ownerName, $document->type->label()),
                    'description' => sprintf('Document #%s submitted by %s.', $document->number ?? $document->getKey(), $ownerName),
                    'priority' => DailyTaskPriority::Normal,
                    'related_type' => $document->getMorphClass(),
                    'related_id' => $document->getKey(),
                    'auto_generated' => true,
                    'dedupe_key' => 'kyc-'.$document->getKey(),
                    'created_by' => $userId,
                ]);
            });

        return $created;
    }

    private function generatePendingPayouts(Carbon $date, ?int $userId): int
    {
        $created = 0;

        InvestorPayout::query()
            ->with('investor')
            ->pending()
            ->orderBy('id')
            ->each(function (InvestorPayout $payout) use (&$created, $date, $userId): void {
                $created += $this->createTask([
                    'task_date' => $date->toDateString(),
                    'type' => DailyTaskType::PayoutDue,
                    'title' => sprintf('Process payout — %s (%s)', $payout->investor?->name ?? 'Unknown investor', $payout->payout_code),
                    'description' => sprintf(
                        'Period %s · Net %s',
                        $payout->period_month?->format('F Y') ?? '—',
                        Money::format($payout->net_amount)
                    ),
                    'priority' => DailyTaskPriority::Normal,
                    'related_type' => $payout->getMorphClass(),
                    'related_id' => $payout->getKey(),
                    'auto_generated' => true,
                    'dedupe_key' => 'payout-'.$payout->getKey(),
                    'created_by' => $userId,
                ]);
            });

        return $created;
    }

    private function generatePendingLoanApprovals(Carbon $date, ?int $userId): int
    {
        $created = 0;

        Loan::query()
            ->with('borrower')
            ->where('status', LoanStatus::Pending)
            ->orderBy('id')
            ->each(function (Loan $loan) use (&$created, $date, $userId): void {
                $created += $this->createTask([
                    'task_date' => $date->toDateString(),
                    'type' => DailyTaskType::LoanApproval,
                    'title' => sprintf('Review loan — %s (%s)', $loan->borrower?->full_name ?? 'Unknown borrower', $loan->loan_code),
                    'description' => sprintf(
                        'Principal %s · %d months',
                        Money::format($loan->principal_amount),
                        $loan->tenure_months
                    ),
                    'priority' => DailyTaskPriority::Normal,
                    'related_type' => $loan->getMorphClass(),
                    'related_id' => $loan->getKey(),
                    'auto_generated' => true,
                    'dedupe_key' => 'loan-approval-'.$loan->getKey(),
                    'created_by' => $userId,
                ]);
            });

        return $created;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTask(array $attributes): int
    {
        $existing = DailyTask::query()
            ->whereDate('task_date', $attributes['task_date'])
            ->where('dedupe_key', $attributes['dedupe_key'])
            ->first();

        if ($existing) {
            return 0;
        }

        DailyTask::query()->create([
            ...$attributes,
            'status' => DailyTaskStatus::Pending,
        ]);

        return 1;
    }
}
