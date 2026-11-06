<?php

use App\Enums\DistributionBeneficiaryType;
use App\Enums\DistributionEntryType;
use App\Enums\DistributionStatus;
use App\Enums\PayoutStatus;
use App\Models\AdminCommissionProfile;
use App\Models\EmiSchedule;
use App\Models\InterestConfiguration;
use App\Models\InterestDistributionLedger;
use App\Models\Investor;
use App\Models\InvestorAllocation;
use App\Models\InvestorInterestRate;
use App\Models\InvestorPayout;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanAdminAssignment;
use App\Models\User;
use App\Services\AdminCommissionPayoutService;
use App\Services\DistributionReportService;
use App\Services\EmiCollectionService;
use App\Services\InterestDistributionService;
use App\Services\InvestorPayoutService;
use App\Support\Money;
use Database\Seeders\AccountingSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(AccountingSeeder::class);
});

/**
 * @return array{0: Loan, 1: EmiSchedule}
 */
function distributionLoan(string $interest = '1000.00', string $principal = '100000.00'): array
{
    $loan = Loan::factory()->active()->create([
        'principal_amount' => $principal,
        'outstanding_principal' => $principal,
        'outstanding_interest' => $interest,
        'annual_interest_rate' => '18',
        'first_emi_date' => now()->startOfMonth(),
    ]);

    $schedule = $loan->schedules()->create([
        'installment_no' => 1,
        'due_date' => now()->toDateString(),
        'principal_amount' => '0.00',
        'interest_amount' => $interest,
        'total_amount' => $interest,
        'outstanding_principal' => $principal,
        'status' => 'pending',
    ]);

    return [$loan, $schedule];
}

function activeDistributionConfiguration(): InterestConfiguration
{
    InterestConfiguration::query()->update(['is_active' => false]);

    return InterestConfiguration::query()->create([
        'name' => 'Test configuration',
        'effective_from' => now()->subDay()->toDateString(),
        'borrower_monthly_rate' => '1.500000',
        'default_investor_monthly_rate' => '1.500000',
        'investor_allocation_percent' => '60.0000',
        'admin_allocation_percent' => '10.0000',
        'company_allocation_percent' => '30.0000',
        'default_admin_commission_rate' => '10.0000',
        'is_active' => true,
    ]);
}

function allocate(Investor $investor, Loan $loan, string $principal): InvestorAllocation
{
    return InvestorAllocation::query()->create([
        'investor_id' => $investor->id,
        'loan_id' => $loan->id,
        'principal_amount' => $principal,
        'allocation_date' => now()->toDateString(),
        'status' => 'active',
    ]);
}

test('collected interest is split between investors admins and company without over-distribution', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    activeDistributionConfiguration();

    [$loan, $schedule] = distributionLoan();

    $admin = User::factory()->create();
    $loan->forceFill(['final_approved_by' => $admin->id, 'approved_by' => $admin->id])->save();

    AdminCommissionProfile::query()->create([
        'user_id' => $admin->id,
        'commission_rate' => '10.0000',
        'effective_from' => now()->subMonth()->toDateString(),
        'is_active' => true,
    ]);

    $first = Investor::factory()->create();
    $second = Investor::factory()->create();

    allocate($first, $loan, '60000.00');
    allocate($second, $loan, '40000.00');

    InvestorInterestRate::query()->create([
        'investor_id' => $first->id,
        'monthly_rate' => '1.500000',
        'effective_from' => now()->subMonth()->toDateString(),
        'is_active' => true,
    ]);
    InvestorInterestRate::query()->create([
        'investor_id' => $second->id,
        'monthly_rate' => '1.000000',
        'effective_from' => now()->subMonth()->toDateString(),
        'is_active' => true,
    ]);

    $payment = app(EmiCollectionService::class)->collect($loan, [
        'amount' => '1000.00',
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ], auth()->user());

    $entries = InterestDistributionLedger::query()->where('emi_payment_id', $payment->id)->get();

    $investorEntries = $entries->where('beneficiary_type', DistributionBeneficiaryType::Investor);
    $adminEntries = $entries->where('beneficiary_type', DistributionBeneficiaryType::Admin);
    $companyEntries = $entries->where('beneficiary_type', DistributionBeneficiaryType::Company);

    expect($investorEntries)->toHaveCount(2)
        ->and($adminEntries)->toHaveCount(1)
        ->and($companyEntries)->toHaveCount(1)
        ->and(Money::round((string) $investorEntries->sum('amount')))->toBe('600.00')
        ->and(Money::round((string) $adminEntries->sum('amount')))->toBe('100.00')
        ->and(Money::round((string) $companyEntries->sum('amount')))->toBe('300.00')
        ->and(Money::round((string) $entries->sum('amount')))->toBe('1000.00');

    expect((string) $first->fresh()->distributionEntries()->sum('amount'))->toBe('415.38')
        ->and((string) $second->fresh()->distributionEntries()->sum('amount'))->toBe('184.62');

    $journal = JournalEntry::query()
        ->where('description', 'Interest distribution '.$payment->receipt_no)
        ->first();

    expect($journal)->not->toBeNull()
        ->and($journal->isBalanced())->toBeTrue()
        ->and(Money::round((string) $journal->lines()->sum('debit')))->toBe('1000.00');
});

test('loan distribution snapshot is immutable after configuration changes', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $configuration = activeDistributionConfiguration();

    [$loan] = distributionLoan();
    $investor = Investor::factory()->create();
    allocate($investor, $loan, '100000.00');

    $snapshot = app(InterestDistributionService::class)->ensureSnapshot($loan, auth()->user());
    $originalRules = $snapshot->rules_snapshot;
    $originalRate = (string) $loan->investorDistributions()->first()->monthly_rate;

    $configuration->update([
        'investor_allocation_percent' => '50.0000',
        'admin_allocation_percent' => '20.0000',
        'company_allocation_percent' => '30.0000',
    ]);

    activeDistributionConfiguration();

    $again = app(InterestDistributionService::class)->ensureSnapshot($loan->refresh(), auth()->user());

    expect($again->id)->toBe($snapshot->id)
        ->and($again->rules_snapshot)->toBe($originalRules)
        ->and((string) $loan->investorDistributions()->first()->monthly_rate)->toBe($originalRate);
});

test('payments without an interest component do not create distribution entries', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    activeDistributionConfiguration();

    [$loan] = distributionLoan('0.00');
    $loan->schedules()->delete();
    $loan->schedules()->create([
        'installment_no' => 1,
        'due_date' => now()->toDateString(),
        'principal_amount' => '1000.00',
        'interest_amount' => '0.00',
        'total_amount' => '1000.00',
        'outstanding_principal' => '100000.00',
        'status' => 'pending',
    ]);
    allocate(Investor::factory()->create(), $loan, '100000.00');

    $payment = app(EmiCollectionService::class)->collect($loan, [
        'amount' => '1000.00',
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ], auth()->user());

    expect($payment->interest_component)->toBe('0.00')
        ->and(InterestDistributionLedger::query()->count())->toBe(0);
});

test('distributing the same payment twice does not duplicate ledger or journal entries', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    activeDistributionConfiguration();

    [$loan] = distributionLoan();
    allocate(Investor::factory()->create(), $loan, '100000.00');

    $payment = app(EmiCollectionService::class)->collect($loan, [
        'amount' => '1000.00',
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ], auth()->user());

    $firstCount = InterestDistributionLedger::query()->count();

    app(InterestDistributionService::class)->distributeForPayment($payment->refresh(), auth()->user());

    $distributionJournals = JournalEntry::query()
        ->where('description', 'Interest distribution '.$payment->receipt_no)
        ->count();

    expect(InterestDistributionLedger::query()->count())->toBe($firstCount)
        ->and($distributionJournals)->toBe(1);
});

test('reversing a payment posts compensating entries and restores the schedule', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    activeDistributionConfiguration();

    [$loan, $schedule] = distributionLoan();
    allocate(Investor::factory()->create(), $loan, '100000.00');

    $payment = app(EmiCollectionService::class)->collect($loan, [
        'amount' => '1000.00',
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ], auth()->user());

    $earnings = InterestDistributionLedger::query()->earnings()->get();

    app(EmiCollectionService::class)->reversePayment($payment->refresh(), auth()->user(), 'Bounced cheque');

    $payment->refresh();
    $schedule->refresh();

    $reversals = InterestDistributionLedger::query()
        ->where('emi_payment_id', $payment->id)
        ->reversals()
        ->get();

    $net = $earnings->reduce(
        fn (string $carry, InterestDistributionLedger $entry): string => Money::add($carry, $entry->signedAmount()),
        '0',
    ) + $reversals->sum(fn (InterestDistributionLedger $entry): string => $entry->signedAmount());

    $reversalJournal = JournalEntry::query()
        ->where('description', 'Distribution reversal '.$payment->receipt_no)
        ->first();

    expect($payment->is_reversed)->toBeTrue()
        ->and($reversals)->toHaveCount($earnings->count())
        ->and($reversals->every(fn (InterestDistributionLedger $entry): bool => $entry->entry_type === DistributionEntryType::Reversal && $entry->status === DistributionStatus::Reversed))->toBeTrue()
        ->and(Money::round((string) $net))->toBe('0.00')
        ->and((string) $schedule->paid_amount)->toBe('0.00')
        ->and($reversalJournal)->not->toBeNull()
        ->and($reversalJournal->isBalanced())->toBeTrue();

    expect(fn () => app(EmiCollectionService::class)->reversePayment($payment->refresh(), auth()->user(), 'Again'))
        ->toThrow(InvalidArgumentException::class);
});

test('a receipt linked to a payout cannot be reversed until the payout is released', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);
    activeDistributionConfiguration();

    [$loan, $schedule] = distributionLoan();
    $investor = Investor::factory()->create();
    allocate($investor, $loan, '100000.00');

    $payment = app(EmiCollectionService::class)->collect($loan, [
        'amount' => '1000.00',
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ], $superAdmin);

    app(InvestorPayoutService::class)->generate(now()->startOfMonth()->toImmutable(), $superAdmin);

    $payout = InvestorPayout::query()->where('investor_id', $investor->id)->firstOrFail();

    expect(fn () => app(EmiCollectionService::class)->reversePayment($payment->refresh(), $superAdmin, 'Bounced cheque'))
        ->toThrow(InvalidArgumentException::class, 'linked to an investor or admin payout');

    expect($payment->refresh()->is_reversed)->toBeFalse()
        ->and((string) $schedule->refresh()->paid_amount)->toBe('1000.00');

    app(InvestorPayoutService::class)->reject($payout->refresh(), $superAdmin, 'Receipt reversal');
    app(EmiCollectionService::class)->reversePayment($payment->refresh(), $superAdmin, 'Bounced cheque');

    expect($payment->refresh()->is_reversed)->toBeTrue();
});

test('investor payout workflow generates approves and processes from collected interest', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);
    activeDistributionConfiguration();

    [$loan] = distributionLoan();
    $investor = Investor::factory()->create();
    allocate($investor, $loan, '100000.00');

    $payment = app(EmiCollectionService::class)->collect($loan, [
        'amount' => '1000.00',
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ], $superAdmin);

    $entitlement = (string) InterestDistributionLedger::query()
        ->where('investor_id', $investor->id)
        ->forInvestors()
        ->sum('amount');

    $generated = app(InvestorPayoutService::class)->generate(now()->startOfMonth()->toImmutable(), $superAdmin);

    expect($generated)->toBe(1);

    $payout = InvestorPayout::query()->where('investor_id', $investor->id)->firstOrFail();

    expect($payout->status)->toBe(PayoutStatus::Pending)
        ->and((string) $payout->gross_interest)->toBe(Money::round($entitlement))
        ->and($payout->items()->count())->toBe(1);

    app(InvestorPayoutService::class)->approve($payout, $superAdmin);

    expect($payout->fresh()->status)->toBe(PayoutStatus::Approved);

    app(InvestorPayoutService::class)->process($payout->fresh(), $superAdmin, 'UTR-DIST-1', now()->toDateString());

    $payout->refresh();

    $journal = JournalEntry::query()
        ->where('reference_type', $payout->getMorphClass())
        ->where('reference_id', $payout->id)
        ->first();

    expect($payout->status)->toBe(PayoutStatus::Paid)
        ->and($payout->payment_reference)->toBe('UTR-DIST-1')
        ->and($journal)->not->toBeNull()
        ->and($journal->isBalanced())->toBeTrue()
        ->and(InterestDistributionLedger::query()->where('investor_id', $investor->id)->first()->status)->toBe(DistributionStatus::Paid);

    expect(app(InvestorPayoutService::class)->generate(now()->startOfMonth()->toImmutable(), $superAdmin))->toBe(0);

    expect(fn () => app(InvestorPayoutService::class)->process($payout->refresh(), $superAdmin, 'UTR-DIST-2', now()->toDateString()))
        ->toThrow(InvalidArgumentException::class);
});

test('admin commission workflow uses the responsible admin assignment', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);
    activeDistributionConfiguration();

    [$loan] = distributionLoan();
    allocate(Investor::factory()->create(), $loan, '100000.00');

    $admin = User::factory()->create();
    $loan->forceFill(['final_approved_by' => $admin->id, 'approved_by' => $admin->id])->save();

    AdminCommissionProfile::query()->create([
        'user_id' => $admin->id,
        'designation' => 'Relationship Manager',
        'commission_rate' => '10.0000',
        'effective_from' => now()->subMonth()->toDateString(),
        'is_active' => true,
    ]);

    app(EmiCollectionService::class)->collect($loan, [
        'amount' => '1000.00',
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ], $superAdmin);

    expect(LoanAdminAssignment::query()->where('loan_id', $loan->id)->where('user_id', $admin->id)->exists())->toBeTrue();

    $adminEntitlement = (string) InterestDistributionLedger::query()
        ->where('admin_id', $admin->id)
        ->forAdmins()
        ->sum('amount');

    expect(Money::round($adminEntitlement))->toBe('100.00');

    $generated = app(AdminCommissionPayoutService::class)->generate(now()->startOfMonth()->toImmutable(), $superAdmin);

    expect($generated)->toBe(1);

    $payout = $admin->commissionPayouts()->firstOrFail();

    app(AdminCommissionPayoutService::class)->approve($payout, $superAdmin);
    app(AdminCommissionPayoutService::class)->process($payout->fresh(), $superAdmin, 'UTR-ADMIN-1', now()->toDateString());

    expect($payout->fresh()->status)->toBe(PayoutStatus::Paid)
        ->and((string) $payout->fresh()->net_amount)->toBe('100.00');

    $journal = JournalEntry::query()
        ->where('reference_type', $payout->getMorphClass())
        ->where('reference_id', $payout->id)
        ->first();

    expect($journal)->not->toBeNull()->and($journal->isBalanced())->toBeTrue();
});

test('distribution report exposes company earnings and loan level totals', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    activeDistributionConfiguration();

    [$loan] = distributionLoan();
    allocate(Investor::factory()->create(), $loan, '100000.00');

    app(EmiCollectionService::class)->collect($loan, [
        'amount' => '1000.00',
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ], auth()->user());

    $report = app(DistributionReportService::class);
    $summary = $report->summary();
    $loans = $report->byLoan();

    expect($summary['collected_interest'])->toBe('1000.00')
        ->and($summary['investor_earned'])->toBe('600.00')
        ->and($summary['admin_earned'])->toBe('100.00')
        ->and($summary['company_earned'])->toBe('300.00')
        ->and($loans[0]['loan_code'])->toBe($loan->loan_code)
        ->and($loans[0]['company'])->toBe('300.00');
});

test('only a super admin can review process or configure distribution', function () {
    $admin = User::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    expect($superAdmin->can('create', InterestConfiguration::class))->toBeTrue()
        ->and($admin->can('create', InterestConfiguration::class))->toBeFalse();

    $this->actingAs($admin);

    Livewire::test('pages::distribution.settings')
        ->call('openConfigModal')
        ->assertForbidden();

    $payout = InvestorPayout::query()->create([
        'investor_id' => Investor::factory()->create()->id,
        'period_month' => now()->startOfMonth(),
        'gross_interest' => '100.00',
        'management_fee' => '10.00',
        'tds_amount' => '10.00',
        'net_amount' => '80.00',
        'status' => PayoutStatus::Pending,
    ]);

    expect($admin->can('approve', $payout))->toBeFalse()
        ->and($superAdmin->can('approve', $payout))->toBeTrue();
});

test('distribution configuration rejects allocations that do not total one hundred percent', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test('pages::distribution.settings')
        ->call('openConfigModal')
        ->set('name', 'Invalid split')
        ->set('effective_from', now()->toDateString())
        ->set('borrower_monthly_rate', '1.5')
        ->set('default_investor_monthly_rate', '1.5')
        ->set('investor_allocation_percent', '60')
        ->set('admin_allocation_percent', '10')
        ->set('company_allocation_percent', '20')
        ->call('saveConfiguration')
        ->assertHasErrors(['company_allocation_percent']);

    expect(InterestConfiguration::query()->where('name', 'Invalid split')->exists())->toBeFalse();
});

test('distribution pages render for authorised users', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    activeDistributionConfiguration();

    [$loan] = distributionLoan();

    $this->actingAs($superAdmin)
        ->get(route('distribution.settings'))
        ->assertOk()
        ->assertSee('Interest & Distribution Settings');

    $this->actingAs($superAdmin)
        ->get(route('admin-commissions.index'))
        ->assertOk()
        ->assertSee('Admin Commissions');

    $this->actingAs($superAdmin)
        ->get(route('company-earnings.index'))
        ->assertOk()
        ->assertSee('Company Earnings');

    $this->actingAs($superAdmin)
        ->get(route('payouts.history'))
        ->assertOk()
        ->assertSee('Payout History');

    $this->actingAs($superAdmin)
        ->get(route('loans.distribution', $loan))
        ->assertOk()
        ->assertSee('Loan Interest Distribution');
});

test('a super admin can reverse a receipt from the collections page', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);
    activeDistributionConfiguration();

    [$loan] = distributionLoan();
    allocate(Investor::factory()->create(), $loan, '100000.00');

    $payment = app(EmiCollectionService::class)->collect($loan, [
        'amount' => '1000.00',
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ], $superAdmin);

    Livewire::test('pages::collections.index')
        ->call('openReverseModal', $payment->id)
        ->set('reversal_reason', 'Bounced cheque')
        ->call('reverse')
        ->assertHasNoErrors();

    expect($payment->refresh()->is_reversed)->toBeTrue()
        ->and(InterestDistributionLedger::query()
            ->where('emi_payment_id', $payment->id)
            ->reversals()
            ->count())->toBe(3);
});

test('a regular admin cannot reverse a receipt from the collections page', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);
    activeDistributionConfiguration();

    [$loan] = distributionLoan();
    allocate(Investor::factory()->create(), $loan, '100000.00');

    $payment = app(EmiCollectionService::class)->collect($loan, [
        'amount' => '1000.00',
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ], $admin);

    Livewire::test('pages::collections.index')
        ->call('openReverseModal', $payment->id)
        ->assertForbidden();
});

test('legacy collections can be distributed after the loan snapshot is locked', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);
    activeDistributionConfiguration();

    [$loan] = distributionLoan();
    allocate(Investor::factory()->create(), $loan, '100000.00');

    $legacyPayment = $loan->payments()->create([
        'amount' => '1000.00',
        'principal_component' => '0.00',
        'interest_component' => '1000.00',
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ]);

    expect(InterestDistributionLedger::query()->where('emi_payment_id', $legacyPayment->id)->exists())->toBeFalse();

    Livewire::test('pages::loans.distribution', ['loan' => $loan])
        ->call('createSnapshot')
        ->call('distributeExisting')
        ->assertHasNoErrors();

    expect(InterestDistributionLedger::query()
        ->where('emi_payment_id', $legacyPayment->id)
        ->earnings()
        ->count())->toBe(3);
});
