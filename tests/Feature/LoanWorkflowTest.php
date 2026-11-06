<?php

use App\Enums\ApplicationStatus;
use App\Enums\DepositStatus;
use App\Enums\LoanFundingStatus;
use App\Enums\LoanStatus;
use App\Models\Investor;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanFundingService;
use App\Services\LoanOfferService;
use App\Services\LoanService;
use App\Support\LoanWorkflow;
use Carbon\CarbonImmutable;
use Database\Seeders\AccountingSeeder;
use Livewire\Livewire;

function applicationLoan(string $principal = '100000'): Loan
{
    return Loan::factory()->create([
        'principal_amount' => $principal,
        'outstanding_principal' => $principal,
    ]);
}

function depositTo(Investor $investor, string|int $amount, DepositStatus $status = DepositStatus::Cleared): void
{
    $investor->deposits()->create([
        'amount' => $amount,
        'status' => $status,
        'deposited_at' => now()->toDateString(),
        'created_by' => auth()->id() ?? User::factory()->create()->id,
    ]);
}

test('the comparison calculates flat and reducing side by side', function () {
    $comparison = app(LoanOfferService::class)->compare(100000, 12, 12, CarbonImmutable::today());

    expect(count($comparison['flat']->installments))->toBe(12)
        ->and(count($comparison['reducing']->installments))->toBe(12)
        ->and(100000 + 1200)->toEqual(101200)
        ->and((float) $comparison['reducing']->totalInterest)->toBeLessThan((float) $comparison['flat']->totalInterest);
});

test('an admin can generate an offer and the loan moves to offer generated', function () {
    $this->actingAs(User::factory()->create());
    $loan = applicationLoan();

    $offer = app(LoanOfferService::class)->generate($loan, [
        'principal_amount' => '100000',
        'annual_interest_rate' => '13.5',
        'interest_method' => 'flat',
        'tenure_months' => 10,
        'processing_fee' => '1500',
        'other_charges' => '250',
        'notes' => 'First offer',
    ], auth()->user());

    expect($offer->interest_method->value)->toBe('flat')
        ->and((string) $offer->processing_fee)->toBe('1500.00')
        ->and($offer->comparison)->toHaveKeys(['flat', 'reducing'])
        ->and($offer->accepted_at)->toBeNull()
        ->and($loan->fresh()->application_status)->toBe(ApplicationStatus::OfferGenerated);
});

test('accepting an offer locks the terms, updates the loan and rebuilds the schedule', function () {
    $this->actingAs(User::factory()->superAdmin()->create());
    $loan = applicationLoan('100000');

    $offer = app(LoanOfferService::class)->generate($loan, [
        'principal_amount' => '100000',
        'annual_interest_rate' => '10',
        'interest_method' => 'flat',
        'tenure_months' => 10,
        'processing_fee' => '1000',
        'other_charges' => '0',
    ], auth()->user());

    $accepted = app(LoanOfferService::class)->accept($offer->refresh(), auth()->user());

    expect($accepted->application_status)->toBe(ApplicationStatus::Accepted)
        ->and((string) $accepted->annual_interest_rate)->toBe('10.0000')
        ->and($accepted->tenure_months)->toBe(10)
        ->and($accepted->schedules()->count())->toBe(10)
        ->and($offer->refresh()->accepted_at)->not->toBeNull();

    // A second offer cannot be generated after acceptance.
    expect(fn () => app(LoanOfferService::class)->generate($accepted, [
        'principal_amount' => '100000',
        'annual_interest_rate' => '14',
        'interest_method' => 'reducing',
        'tenure_months' => 12,
    ], auth()->user()))->toThrow(InvalidArgumentException::class);
});

test('a rejected application cannot progress', function () {
    $this->actingAs(User::factory()->create());
    $loan = applicationLoan();

    $rejected = app(LoanOfferService::class)->rejectApplication($loan, 'Insufficient income proof', auth()->user());

    expect($rejected->application_status)->toBe(ApplicationStatus::Rejected)
        ->and($rejected->notes)->toContain('Insufficient income proof')
        ->and(fn () => app(LoanOfferService::class)->generate($rejected->fresh(), [
            'principal_amount' => '100000',
            'annual_interest_rate' => '12',
            'interest_method' => 'flat',
            'tenure_months' => 10,
        ], auth()->user()))->toThrow(InvalidArgumentException::class);
});

test('available capital only counts cleared deposits net of active allocations', function () {
    $investor = Investor::factory()->create();
    $otherLoan = Loan::factory()->create(['principal_amount' => '40000']);

    depositTo($investor, 60000);
    depositTo($investor, 50000, DepositStatus::Pending);
    $investor->allocations()->create([
        'loan_id' => $otherLoan->id,
        'principal_amount' => '10000.00',
        'allocation_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $service = app(LoanFundingService::class);

    expect($service->availableCapital($investor))->toBe('50000.00');

    $loan = applicationLoan('100000');
    $report = $service->capitalReport($loan);

    expect($report['total_available'])->toBe('50000.00')
        ->and($report['required'])->toBe('100000.00')
        ->and($report['sufficient'])->toBeFalse()
        ->and($report['shortage'])->toBe('50000.00');
});

test('capital verification requires an accepted application and stamps the loan', function () {
    $this->actingAs(User::factory()->create());
    $loan = applicationLoan();
    $actor = auth()->user();
    $service = app(LoanFundingService::class);

    // Not accepted yet — cannot be verified.
    expect(fn () => $service->markCapitalVerified($loan, $actor))->toThrow(InvalidArgumentException::class);

    $offerLoan = applicationLoan();
    acceptOfferFor($offerLoan);

    $verified = $service->markCapitalVerified($offerLoan->fresh(), $actor);

    expect($verified->capital_verified_at)->not->toBeNull();
});

function acceptOfferFor(Loan $loan): Loan
{
    $offer = app(LoanOfferService::class)->generate($loan, [
        'principal_amount' => (string) $loan->principal_amount,
        'annual_interest_rate' => '12',
        'interest_method' => 'reducing',
        'tenure_months' => 12,
    ], User::factory()->create());

    return app(LoanOfferService::class)->accept($offer->refresh(), User::factory()->create());
}

test('capital allocation reserves the exact amount and locks funding', function () {
    $this->seed(AccountingSeeder::class);
    $this->actingAs(User::factory()->create());

    $loan = applicationLoan();
    acceptOfferFor($loan);

    $loan = $loan->fresh();
    $service = app(LoanFundingService::class);
    $actor = auth()->user();

    // Cannot allocate before verification.
    expect(fn () => $service->allocateCapital($loan, [999999 => '100000'], $actor))->toThrow(InvalidArgumentException::class);

    $service->markCapitalVerified($loan, $actor);

    $investorA = Investor::factory()->create();
    $investorB = Investor::factory()->create();
    depositTo($investorA, 60000);
    depositTo($investorB, 50000);
    $loan = $loan->fresh();

    // Investor A only has 60,000 available.
    expect(fn () => $service->allocateCapital($loan, [$investorA->id => '70000'], $actor))
        ->toThrow(InvalidArgumentException::class, 'only has');

    // Total must match exactly.
    expect(fn () => $service->allocateCapital($loan, [$investorA->id => '60000', $investorB->id => '30000'], $actor))
        ->toThrow(InvalidArgumentException::class, 'exactly match');

    $service->allocateCapital($loan, [$investorA->id => '60000', $investorB->id => '40000'], $actor);

    $loan->refresh();

    expect($loan->funding_status)->toBe(LoanFundingStatus::FullyFunded)
        ->and($loan->funding_locked_at)->not->toBeNull()
        ->and($loan->allocations()->where('status', 'active')->count())->toBe(2)
        // Double allocation is rejected (funding is locked once the loan is fully funded).
        ->and(fn () => $service->allocateCapital($loan->fresh(), [$investorA->id => '60000'], $actor))
        ->toThrow(InvalidArgumentException::class, 'Funding is locked');
});

test('disbursement requires verification, full funding and final approval', function () {
    $this->seed(AccountingSeeder::class);
    $this->actingAs(User::factory()->create());

    $loan = applicationLoan();
    acceptOfferFor($loan);

    $loan = $loan->fresh();
    $service = app(LoanFundingService::class);
    $actor = auth()->user();

    $service->markCapitalVerified($loan, $actor);

    $investor = Investor::factory()->create();
    depositTo($investor, 100000);

    $service->allocateCapital($loan->fresh(), [$investor->id => '100000'], $actor);
    $loan = $loan->fresh();

    // Funded but not finally approved yet.
    expect(fn () => app(LoanService::class)->disburse($loan, $actor))->toThrow(InvalidArgumentException::class, 'Final disbursement approval');

    $disbursed = app(LoanService::class)->disburse(
        app(LoanService::class)->approveForDisbursement($loan, $actor),
        $actor,
        null,
        'UTR-100',
    );

    expect($disbursed->status)->toBe(LoanStatus::Active)
        ->and($disbursed->disbursement_reference)->toBe('UTR-100');
});

test('the workflow tracker reflects every processing stage', function () {
    $this->seed(AccountingSeeder::class);
    $this->actingAs(User::factory()->create());

    $loan = applicationLoan();
    $actor = auth()->user();

    // 1. Application submitted — current step is the application.
    $steps = collect(LoanWorkflow::steps($loan->fresh()));
    $current = LoanWorkflow::current(LoanWorkflow::steps($loan->fresh()));

    expect($current['key'])->toBe('application')
        ->and(LoanWorkflow::summary(LoanWorkflow::steps($loan->fresh()))['done'])->toBe(0);

    acceptOfferFor($loan);

    // After acceptance the offer and acceptance steps are done, verification is current.
    $current = LoanWorkflow::current(LoanWorkflow::steps($loan->fresh()));

    expect($current['key'])->toBe('capital_verification');

    $loan = $loan->fresh();
    $service = app(LoanFundingService::class);
    $service->markCapitalVerified($loan, $actor);

    $current = LoanWorkflow::current(LoanWorkflow::steps($loan->fresh()));

    expect($current['key'])->toBe('capital_allocation');

    $investor = Investor::factory()->create();
    depositTo($investor, 100000);
    $service->allocateCapital($loan->fresh(), [$investor->id => '100000'], $actor);

    $current = LoanWorkflow::current(LoanWorkflow::steps($loan->fresh()));

    expect($current['key'])->toBe('final_approval');

    $loan = app(LoanService::class)->approveForDisbursement($loan->fresh(), $actor);
    $current = LoanWorkflow::current(LoanWorkflow::steps($loan));

    expect($current['key'])->toBe('disbursement');

    $loan = app(LoanService::class)->disburse($loan, $actor, null, 'UTR-200');
    $current = LoanWorkflow::current(LoanWorkflow::steps($loan));

    expect($current['key'])->toBe('emi_collection');
});

test('the loan calculator page generates an offer and records acceptance', function () {
    $this->actingAs(User::factory()->create());
    $loan = applicationLoan();
    $investor = Investor::factory()->create();

    Livewire::test('pages::loans.calculator', ['loan' => $loan])
        ->set('principal_amount', '100000')
        ->set('annual_interest_rate', '12')
        ->set('interest_method', 'flat')
        ->set('tenure_months', '10')
        ->set('processing_fee', '2000')
        ->call('generateOffer')
        ->assertHasNoErrors();

    $offer = $loan->offers()->latest('id')->first();

    expect($offer)->not->toBeNull()
        ->and($loan->fresh()->application_status)->toBe(ApplicationStatus::OfferGenerated);

    Livewire::test('pages::loans.calculator', ['loan' => $loan->fresh()])
        ->call('confirmAcceptance', $offer->id)
        ->assertHasNoErrors();

    expect($loan->fresh()->application_status)->toBe(ApplicationStatus::Accepted)
        ->and($loan->fresh()->schedules()->count())->toBe(10);
});

test('the capital pages verify and allocate for the loan', function () {
    $this->seed(AccountingSeeder::class);
    $this->actingAs(User::factory()->create());

    $loan = applicationLoan();
    acceptOfferFor($loan);

    $investor = Investor::factory()->create();
    depositTo($investor, 100000);

    Livewire::test('pages::loans.capital-verification', ['loan' => $loan->fresh()])
        ->call('confirmVerified')
        ->assertHasNoErrors();

    expect($loan->fresh()->capital_verified_at)->not->toBeNull();

    Livewire::test('pages::loans.capital-allocation', ['loan' => $loan->fresh()])
        ->set('allocations.'.$investor->id, '100000')
        ->call('allocate')
        ->assertHasNoErrors();

    $loan->refresh();

    expect($loan->funding_status)->toBe(LoanFundingStatus::FullyFunded);
});
