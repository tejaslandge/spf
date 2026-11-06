<?php

use App\Enums\DailyTaskStatus;
use App\Enums\DailyTaskType;
use App\Enums\EmiStatus;
use App\Enums\KycDocumentType;
use App\Enums\KycStatus;
use App\Enums\LoanStatus;
use App\Enums\MessageStatus;
use App\Enums\PayoutStatus;
use App\Jobs\SendMessage;
use App\Models\Borrower;
use App\Models\DailyTask;
use App\Models\EmiSchedule;
use App\Models\Investor;
use App\Models\InvestorPayout;
use App\Models\Loan;
use App\Models\MessageLog;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function pendingEmiSchedule(Loan $loan, string $dueDate, int $installment = 1): EmiSchedule
{
    return EmiSchedule::query()->create([
        'loan_id' => $loan->id,
        'installment_no' => $installment,
        'due_date' => $dueDate,
        'principal_amount' => 1000,
        'interest_amount' => 100,
        'total_amount' => 1100,
        'outstanding_principal' => 1000,
        'status' => EmiStatus::Pending,
    ]);
}

test('guests are redirected to login', function () {
    $this->get(route('operations.index'))->assertRedirect(route('login'));
});

test('admins can view the daily operations page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Daily Operations');
});

test('super admins can view the daily operations page', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('operations.index'))
        ->assertOk();
});

test('generating tasks picks up system items and is idempotent', function () {
    $this->actingAs(User::factory()->create());

    $dueToday = Loan::factory()->active()->create();
    pendingEmiSchedule($dueToday, now()->format('Y-m-d'), 1);

    $overdue = Loan::factory()->active()->create();
    pendingEmiSchedule($overdue, now()->subDays(10)->format('Y-m-d'), 1);

    $pendingLoan = Loan::factory()->create(['status' => LoanStatus::Pending]);

    $borrower = Borrower::factory()->create();
    $kyc = $borrower->kycDocuments()->create([
        'type' => KycDocumentType::Aadhaar,
        'number' => '1234-5678-9012',
        'file_path' => 'kyc/aadhaar.pdf',
        'original_name' => 'aadhaar.pdf',
        'status' => KycStatus::Pending,
    ]);

    $investor = Investor::factory()->create();
    $payout = InvestorPayout::query()->create([
        'investor_id' => $investor->id,
        'period_month' => now()->startOfMonth()->format('Y-m-d'),
        'gross_interest' => 5000,
        'net_amount' => 4500,
        'status' => PayoutStatus::Pending,
    ]);

    Livewire::test('pages::operations.index')->call('generateTasks');

    expect(DailyTask::query()->count())->toBe(5)
        ->and(DailyTask::query()->ofType(DailyTaskType::EmiReminder)->sole()->related->is($dueToday->schedules()->sole()))->toBeTrue()
        ->and(DailyTask::query()->ofType(DailyTaskType::OverdueFollowUp)->sole()->related->is($overdue))->toBeTrue()
        ->and(DailyTask::query()->ofType(DailyTaskType::LoanApproval)->sole()->related->is($pendingLoan))->toBeTrue()
        ->and(DailyTask::query()->ofType(DailyTaskType::KycReview)->sole()->related->is($kyc))->toBeTrue()
        ->and(DailyTask::query()->ofType(DailyTaskType::PayoutDue)->sole()->related->is($payout))->toBeTrue();

    Livewire::test('pages::operations.index')->call('generateTasks');

    expect(DailyTask::query()->count())->toBe(5);
});

test('a task can be completed and reopened', function () {
    $this->actingAs(User::factory()->create());

    $task = DailyTask::query()->create([
        'task_date' => now()->format('Y-m-d'),
        'type' => DailyTaskType::Manual,
        'title' => 'Call borrower',
        'status' => DailyTaskStatus::Pending,
        'priority' => 'normal',
    ]);

    Livewire::test('pages::operations.index')
        ->call('toggleTask', $task->id);

    expect($task->fresh()->status)->toBe(DailyTaskStatus::Done)
        ->and($task->fresh()->completed_at)->not->toBeNull()
        ->and($task->fresh()->completed_by)->not->toBeNull();

    Livewire::test('pages::operations.index')->call('toggleTask', $task->id);

    expect($task->fresh()->status)->toBe(DailyTaskStatus::Pending)
        ->and($task->fresh()->completed_at)->toBeNull();
});

test('manual tasks can be added and generated tasks cannot be deleted', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::operations.index')
        ->set('title', 'Visit borrower market')
        ->set('description', 'Check shop inventory')
        ->set('priority', 'high')
        ->set('task_date', now()->format('Y-m-d'))
        ->call('saveTask')
        ->assertHasNoErrors();

    $manual = DailyTask::query()->where('title', 'Visit borrower market')->sole();

    expect($manual->auto_generated)->toBeFalse()
        ->and($manual->type)->toBe(DailyTaskType::Manual);

    $generated = DailyTask::query()->create([
        'task_date' => now()->format('Y-m-d'),
        'type' => DailyTaskType::EmiReminder,
        'title' => 'Send EMI reminder',
        'status' => DailyTaskStatus::Pending,
        'priority' => 'normal',
        'auto_generated' => true,
        'dedupe_key' => 'emi-reminder-999',
    ]);

    Livewire::test('pages::operations.index')->call('deleteTask', $generated->id);

    expect(DailyTask::query()->whereKey($generated->id)->exists())->toBeTrue();

    Livewire::test('pages::operations.index')->call('deleteTask', $manual->id);

    expect(DailyTask::query()->whereKey($manual->id)->exists())->toBeFalse();
});

test('sending a reminder queues the message and marks the task done', function () {
    Queue::fake([SendMessage::class]);

    $this->actingAs(User::factory()->create());

    $loan = Loan::factory()->active()->create();
    $schedule = pendingEmiSchedule($loan, now()->format('Y-m-d'), 1);

    $task = DailyTask::query()->create([
        'task_date' => now()->format('Y-m-d'),
        'type' => DailyTaskType::EmiReminder,
        'title' => 'Send EMI reminder',
        'status' => DailyTaskStatus::Pending,
        'priority' => 'normal',
        'related_type' => $schedule->getMorphClass(),
        'related_id' => $schedule->id,
        'auto_generated' => true,
        'dedupe_key' => 'emi-reminder-'.$schedule->id,
    ]);

    Livewire::test('pages::operations.index')
        ->call('openReminder', $task->id)
        ->set('channel', 'whatsapp')
        ->call('sendReminder')
        ->assertHasNoErrors();

    Queue::assertPushed(SendMessage::class, 1);

    expect($task->fresh()->status)->toBe(DailyTaskStatus::Done)
        ->and(MessageLog::query()->sole()->status)->toBe(MessageStatus::Queued)
        ->and(MessageLog::query()->sole()->related->is($schedule))->toBeTrue();
});

test('bulk send queues reminders and skips borrowers without a mobile', function () {
    Queue::fake([SendMessage::class]);

    $this->actingAs(User::factory()->create());

    $withMobile = Loan::factory()->active()->create();
    $scheduleA = pendingEmiSchedule($withMobile, now()->format('Y-m-d'), 1);

    $withoutMobile = Loan::factory()->active()->create();
    $withoutMobile->borrower->update(['mobile' => '']);
    $scheduleB = pendingEmiSchedule($withoutMobile, now()->format('Y-m-d'), 1);

    foreach ([$scheduleA, $scheduleB] as $schedule) {
        DailyTask::query()->create([
            'task_date' => now()->format('Y-m-d'),
            'type' => DailyTaskType::EmiReminder,
            'title' => 'Send EMI reminder',
            'status' => DailyTaskStatus::Pending,
            'priority' => 'normal',
            'related_type' => $schedule->getMorphClass(),
            'related_id' => $schedule->id,
            'auto_generated' => true,
            'dedupe_key' => 'emi-reminder-'.$schedule->id,
        ]);
    }

    Livewire::test('pages::operations.index')->call('sendAllReminders');

    Queue::assertPushed(SendMessage::class, 1);

    expect(DailyTask::query()->where('dedupe_key', 'emi-reminder-'.$scheduleA->id)->sole()->status)->toBe(DailyTaskStatus::Done)
        ->and(DailyTask::query()->where('dedupe_key', 'emi-reminder-'.$scheduleB->id)->sole()->status)->toBe(DailyTaskStatus::Pending);
});

test('the generate command creates tasks for the given date', function () {
    $this->actingAs(User::factory()->create());

    $date = now()->addMonth()->toDateString();

    $loan = Loan::factory()->active()->create();
    pendingEmiSchedule($loan, $date, 1);

    $this->artisan('operations:generate-tasks', ['date' => $date])
        ->assertSuccessful();

    expect(DailyTask::query()->whereDate('task_date', $date)->count())->toBe(1);
});
