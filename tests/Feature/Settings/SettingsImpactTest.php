<?php

use App\Enums\DepositStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Borrower;
use App\Models\Investor;
use App\Models\InvestorDeposit;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\StaffNotification;
use App\Services\DepositAllocationService;
use App\Services\LoanFundingService;
use App\Services\LoanPenaltyService;
use App\Services\LoanService;
use App\Services\Notifications\StaffNotifier;
use App\Services\Settings\SettingsService;
use App\Services\Settings\UserPreferenceService;
use App\Support\Regional;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function impactSuperAdmin(): User
{
    return User::factory()->superAdmin()->create();
}

test('loan creation enforces the configured amount and tenure limits', function () {
    $actor = impactSuperAdmin();

    app(SettingsService::class)->putMany([
        'loan.min_amount' => '5000',
        'loan.max_amount' => '20000',
    ], $actor, 'Tightened loan limits');

    $borrower = Borrower::factory()->create();

    $payload = [
        'borrower_id' => $borrower->id,
        'annual_interest_rate' => '12',
        'tenure_months' => 12,
        'interest_method' => 'reducing',
        'first_emi_date' => now()->addMonth()->toDateString(),
    ];

    expect(fn () => app(LoanService::class)->create([...$payload, 'principal_amount' => '1000'], $actor))
        ->toThrow(ValidationException::class);

    expect(fn () => app(LoanService::class)->create([...$payload, 'principal_amount' => '25000'], $actor))
        ->toThrow(ValidationException::class);

    $loan = app(LoanService::class)->create([...$payload, 'principal_amount' => '15000'], $actor);

    expect($loan->exists)->toBeTrue();
});

test('loans cannot use an interest method that has been disabled', function () {
    $actor = impactSuperAdmin();

    app(SettingsService::class)->put('loan.allowed_interest_methods', ['reducing'], $actor, 'Flat interest disabled');

    $borrower = Borrower::factory()->create();

    expect(fn () => app(LoanService::class)->create([
        'borrower_id' => $borrower->id,
        'principal_amount' => '10000',
        'annual_interest_rate' => '12',
        'tenure_months' => 12,
        'interest_method' => 'flat',
        'first_emi_date' => now()->addMonth()->toDateString(),
    ], $actor))->toThrow(ValidationException::class);
});

test('the late payment penalty follows the configured rules', function () {
    $actor = impactSuperAdmin();

    app(SettingsService::class)->putMany([
        'loan.penalty_enabled' => true,
        'loan.penalty_method' => 'per_day_percent',
        'loan.penalty_rate' => '1',
        'loan.penalty_grace_days' => 0,
        'loan.penalty_max_amount' => null,
    ], $actor, 'Penalty policy');

    $loan = Loan::factory()->create();

    $schedule = $loan->schedules()->create([
        'installment_no' => 1,
        'due_date' => now()->subDays(10)->toDateString(),
        'principal_amount' => '1000.00',
        'interest_amount' => '0.00',
        'total_amount' => '1000.00',
        'outstanding_principal' => '1000.00',
        'paid_amount' => '0.00',
        'status' => 'pending',
    ]);

    expect(app(LoanPenaltyService::class)->penaltyFor($schedule))->toBe('100.00');

    app(SettingsService::class)->put('loan.penalty_max_amount', '40', $actor, 'Cap the penalty');

    expect(app(LoanPenaltyService::class)->penaltyFor($schedule))->toBe('40.00');
});

test('deposit amounts are validated against the configured limits', function () {
    $actor = impactSuperAdmin();

    app(SettingsService::class)->put('deposit.min_amount', '1000', $actor, 'Minimum deposit');

    $investor = Investor::factory()->create();

    $this->actingAs($actor);

    Livewire::test('pages::investors.show', ['investor' => $investor])
        ->set('deposit_amount', '500')
        ->set('deposit_status', 'cleared')
        ->set('deposit_date', now()->toDateString())
        ->call('saveDeposit')
        ->assertHasErrors('deposit_amount');
});

test('deposits require verification before clearing when configured', function () {
    $superAdmin = impactSuperAdmin();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $investor = Investor::factory()->create();

    // Non super admins can only record pending deposits.
    $this->actingAs($admin);

    Livewire::test('pages::investors.show', ['investor' => $investor])
        ->set('deposit_amount', '50000')
        ->set('deposit_status', 'cleared')
        ->set('deposit_date', now()->toDateString())
        ->call('saveDeposit')
        ->assertHasNoErrors();

    $deposit = $investor->deposits()->latest('id')->first();
    expect($deposit->status)->toBe(DepositStatus::Pending);

    // A Super Admin records the clear after verification.
    $this->actingAs($superAdmin);

    Livewire::test('pages::investors.show', ['investor' => $investor])
        ->set('deposit_amount', '25000')
        ->set('deposit_status', 'cleared')
        ->set('deposit_date', now()->toDateString())
        ->call('saveDeposit')
        ->assertHasNoErrors();

    expect($investor->deposits()->latest('id')->first()->status)->toBe(DepositStatus::Cleared);
});

test('rejected deposits cannot be cleared again unless explicitly allowed', function () {
    $superAdmin = impactSuperAdmin();

    $investor = Investor::factory()->create();

    $deposit = InvestorDeposit::query()->create([
        'investor_id' => $investor->id,
        'amount' => '10000',
        'status' => DepositStatus::Rejected,
        'deposited_at' => now()->toDateString(),
        'created_by' => $superAdmin->id,
    ]);

    $service = app(DepositAllocationService::class);

    expect(fn () => $service->updateStatus($deposit, DepositStatus::Cleared, null, null, $superAdmin))
        ->toThrow(InvalidArgumentException::class);

    expect($deposit->refresh()->status)->toBe(DepositStatus::Rejected);

    app(SettingsService::class)->put('deposit.allow_reclear_rejected', true, $superAdmin, 'Allow corrections');

    $service->updateStatus($deposit, DepositStatus::Cleared, null, 'Correction approved', $superAdmin);

    expect($deposit->refresh()->status)->toBe(DepositStatus::Cleared);
});

test('verified kyc can be required before an investor funds a loan', function () {
    $actor = impactSuperAdmin();

    app(SettingsService::class)->put('investor.require_kyc_verified', true, $actor, 'KYC gate');

    $loan = Loan::factory()->create();
    $investor = Investor::factory()->create();

    expect(fn () => app(LoanFundingService::class)->commit($loan, $investor, '10000', null, null, $actor))
        ->toThrow(InvalidArgumentException::class);
});

test('the minimum allocation amount is enforced when allocating capital', function () {
    DB::table('accounts')->insertOrIgnore([
        ['code' => '1000', 'name' => 'Cash & Bank', 'type' => 'asset', 'is_system' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['code' => '1100', 'name' => 'Loans Receivable', 'type' => 'asset', 'is_system' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $actor = impactSuperAdmin();

    app(SettingsService::class)->put('deposit.min_allocation_amount', '25000', $actor, 'Minimum allocation line');

    $investor = Investor::factory()->create();

    $investor->deposits()->create([
        'amount' => '100000',
        'status' => DepositStatus::Cleared,
        'deposited_at' => now()->toDateString(),
        'created_by' => $actor->id,
    ]);

    $loan = Loan::factory()->create(['principal_amount' => '100000']);

    expect(fn () => app(LoanFundingService::class)->allocateCapital($loan, [$investor->id => '10000'], $actor))
        ->toThrow(InvalidArgumentException::class);
});

test('notification preferences suppress delivery for the affected user only', function () {
    Notification::fake();

    $superAdmin = impactSuperAdmin();
    $permitted = User::factory()->create([
        'role' => UserRole::Admin,
        'permissions' => [Permission::LoansView->value],
    ]);
    $unrelated = User::factory()->create(['role' => UserRole::Admin, 'permissions' => []]);

    $recipients = app(StaffNotifier::class)->notifyPermission(Permission::LoansView, 'loan_updates', 'Title', 'Body');

    expect($recipients)->toBe(2);

    Notification::assertSentTo($superAdmin, StaffNotification::class);
    Notification::assertSentTo($permitted, StaffNotification::class);
    Notification::assertNotSentTo($unrelated, StaffNotification::class);

    app(UserPreferenceService::class)->putMany($permitted, [
        'notifications.in_app_enabled' => false,
        'notifications.email_enabled' => false,
    ]);

    $notification = new StaffNotification('loan_updates', 'Title', 'Body');

    expect($notification->via($permitted))->toBe([])
        ->and($notification->via($superAdmin))->toContain('database');
});

test('work preferences persist and drive the dashboard redirect', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.preferences')
        ->set('dashboard', 'reports.index')
        ->set('rowsPerPage', '25')
        ->set('dateFilter', 'this_month')
        ->set('reportingPeriod', 'current_month')
        ->call('save')
        ->assertHasNoErrors();

    expect(UserPreferenceService::rowsPerPageFor($user->refresh(), 12))->toBe(25);

    $this->get(route('dashboard'))->assertRedirect(route('reports.index'));
});

test('the configured financial year drives reporting period boundaries', function () {
    $actor = impactSuperAdmin();

    app(SettingsService::class)->put('company.financial_year_start_month', 4, $actor, 'April to March financial year');

    $start = Regional::filterStartDate('this_financial_year', CarbonImmutable::parse('2026-01-15'));

    expect($start->month)->toBe(4)
        ->and($start->year)->toBe(2025);
});

test('the loan form defaults come from the loan settings', function () {
    $actor = impactSuperAdmin();

    app(SettingsService::class)->putMany([
        'loan.default_amount' => '75000',
        'loan.default_monthly_rate' => '2',
    ], $actor, 'New defaults');

    $this->actingAs($actor);

    Livewire::test('pages::loans.create')
        ->assertSet('principal_amount', '75000')
        ->assertSet('monthly_interest_rate', '2')
        ->assertSet('annual_interest_rate', '24');
});
