<?php

use App\Enums\ApplicationStatus;
use App\Enums\CommitmentStatus;
use App\Enums\LoanFundingStatus;
use App\Enums\LoanStatus;
use App\Models\Investor;
use App\Models\InvestorAllocation;
use App\Models\Loan;
use App\Models\LoanFundingCommitment;
use App\Models\User;
use App\Services\LoanFundingService;
use App\Services\LoanService;
use Database\Seeders\AccountingSeeder;
use Livewire\Livewire;

function pendingFundingLoan(string $principal = '100000'): Loan
{
    return Loan::factory()->create([
        'principal_amount' => $principal,
        'outstanding_principal' => $principal,
    ]);
}

test('a partial commitment moves the loan to partially funded', function () {
    $actor = User::factory()->create();
    $loan = pendingFundingLoan();
    $investor = Investor::factory()->create();

    $service = app(LoanFundingService::class);
    $service->commit($loan, $investor, 30000, 'UTR-001', null, $actor);

    expect($loan->fresh()->funding_status)->toBe(LoanFundingStatus::PartiallyFunded)
        ->and($loan->commitments()->sole()->status)->toBe(CommitmentStatus::Confirmed);

    $progress = $service->progress($loan->fresh());

    expect($progress['committed'])->toBe('30000.00')
        ->and($progress['remaining'])->toBe('70000.00')
        ->and($progress['percent'])->toBe(30.0)
        ->and($progress['investors'])->toBe(1);
});

test('reaching the approved amount locks funding and generates allocations', function () {
    $actor = User::factory()->superAdmin()->create();
    $loan = pendingFundingLoan();
    [$investorA, $investorB, $investorC] = [Investor::factory()->create(), Investor::factory()->create(), Investor::factory()->create()];

    $service = app(LoanFundingService::class);
    $service->commit($loan, $investorA, 30000, null, null, $actor);
    $service->commit($loan, $investorB, 40000, null, null, $actor);
    $service->commit($loan, $investorC, 30000, null, null, $actor);

    $loan->refresh();

    expect($loan->funding_status)->toBe(LoanFundingStatus::FullyFunded)
        ->and($loan->funding_locked_at)->not->toBeNull()
        ->and(InvestorAllocation::query()->where('loan_id', $loan->id)->sum('principal_amount'))->toEqual('100000.00')
        ->and($loan->commitments()->where('status', CommitmentStatus::Allocated)->count())->toBe(3);

    $progress = $service->progress($loan);

    expect($progress['remaining'])->toBe('0.00')
        ->and($progress['percent'])->toBe(100.0)
        ->and($progress['investors'])->toBe(3);
});

test('a commitment cannot exceed the remaining requirement', function () {
    $actor = User::factory()->create();
    $loan = pendingFundingLoan();
    $service = app(LoanFundingService::class);

    $service->commit($loan, Investor::factory()->create(), 60000, null, null, $actor);

    expect(fn () => $service->commit($loan, Investor::factory()->create(), 50000, null, null, $actor))
        ->toThrow(InvalidArgumentException::class);
});

test('re-committing by the same investor replaces rather than duplicates', function () {
    $actor = User::factory()->create();
    $loan = pendingFundingLoan();
    $investor = Investor::factory()->create();
    $service = app(LoanFundingService::class);

    $service->commit($loan, $investor, 30000, null, null, $actor);
    $service->commit($loan, $investor, 50000, 'UTR-002', null, $actor);

    expect(LoanFundingCommitment::query()->where('loan_id', $loan->id)->count())->toBe(1)
        ->and((string) $loan->commitments()->sole()->amount)->toBe('50000.00')
        ->and($service->progress($loan->fresh())['percent'])->toBe(50.0);
});

test('withdrawing a commitment recalculates the funding requirement', function () {
    $actor = User::factory()->create();
    $loan = pendingFundingLoan();
    $investorA = Investor::factory()->create();
    $investorB = Investor::factory()->create();
    $service = app(LoanFundingService::class);

    $service->commit($loan, $investorA, 40000, null, null, $actor);
    $service->commit($loan, $investorB, 20000, null, null, $actor);

    $service->withdraw($loan, $investorA, $actor);

    expect($service->progress($loan->fresh())['committed'])->toBe('20000.00');

    $service->withdraw($loan, $investorB, $actor);

    expect($loan->fresh()->funding_status)->toBe(LoanFundingStatus::Unfunded);
});

test('commitments are locked once the loan is fully funded', function () {
    $actor = User::factory()->create();
    $loan = pendingFundingLoan();
    $investorA = Investor::factory()->create();
    $service = app(LoanFundingService::class);

    $service->commit($loan, $investorA, 100000, null, null, $actor);

    expect(fn () => $service->withdraw($loan, $investorA, $actor))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->commit($loan, Investor::factory()->create(), 1, null, null, $actor))->toThrow(InvalidArgumentException::class);
});

test('a loan cannot be disbursed before full funding', function () {
    $actor = User::factory()->create();
    $loan = pendingFundingLoan();
    $service = app(LoanFundingService::class);

    $service->commit($loan, Investor::factory()->create(), 50000, null, null, $actor);

    expect(fn () => app(LoanService::class)->disburse($loan, $actor))
        ->toThrow(InvalidArgumentException::class, 'not fully funded');

    expect($loan->fresh()->status)->toBe(LoanStatus::Pending);
});

test('a fully funded loan can be disbursed', function () {
    $this->seed(AccountingSeeder::class);

    $actor = User::factory()->create();
    $loan = pendingFundingLoan();
    $service = app(LoanFundingService::class);

    // Satisfy the processing gates introduced with the guided workflow.
    $loan->forceFill([
        'application_status' => ApplicationStatus::Accepted,
        'capital_verified_at' => now(),
    ])->save();

    $service->commit($loan, Investor::factory()->create(), 100000, null, null, $actor);

    app(LoanService::class)->approveForDisbursement($loan->fresh(), $actor);

    $disbursed = app(LoanService::class)->disburse($loan, $actor, null, 'UTR-777');

    expect($disbursed->status)->toBe(LoanStatus::Active)
        ->and($disbursed->funding_status)->toBe(LoanFundingStatus::FullyFunded)
        ->and($disbursed->disbursement_date)->not->toBeNull()
        ->and($disbursed->disbursement_reference)->toBe('UTR-777');
});

test('inactive investors cannot commit capital', function () {
    $actor = User::factory()->create();
    $loan = pendingFundingLoan();
    $investor = Investor::factory()->create(['status' => 'inactive']);

    expect(fn () => app(LoanFundingService::class)->commit($loan, $investor, 1000, null, null, $actor))
        ->toThrow(InvalidArgumentException::class, 'Only active investors');
});

test('commitments cannot be recorded against a disbursed loan', function () {
    $actor = User::factory()->create();
    $loan = Loan::factory()->active()->create();

    expect(fn () => app(LoanFundingService::class)->commit($loan, Investor::factory()->create(), 1000, null, null, $actor))
        ->toThrow(InvalidArgumentException::class, 'awaits disbursement');
});

test('the loan page gates funding until the offer is accepted', function () {
    $this->actingAs(User::factory()->create());
    $loan = pendingFundingLoan();

    Livewire::test('pages::loans.show', ['loan' => $loan])
        ->assertDontSee('Record commitment')
        ->assertSee('Funding opens after the borrower accepts the loan offer.');
});

test('the loan page guides the admin through the processing tracker', function () {
    $this->actingAs(User::factory()->create());
    $loan = pendingFundingLoan();

    Livewire::test('pages::loans.show', ['loan' => $loan])
        ->assertSee('Processing tracker')
        ->assertSee('Current step')
        ->assertSee('Review application');
});
