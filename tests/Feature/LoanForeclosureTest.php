<?php

use App\Enums\InterestMethod;
use App\Models\ForeclosureRequest;
use App\Models\Loan;
use App\Models\User;
use App\Services\ForeclosureService;
use App\Support\Money;
use Livewire\Livewire;

function foreclosureLoanWithAccruedInterest(): Loan
{
    return Loan::factory()->active()->create([
        'principal_amount' => '10000.00',
        'outstanding_principal' => '10000.00',
        'interest_method' => InterestMethod::Flat,
        'annual_interest_rate' => '12',
        'tenure_months' => 12,
        'disbursement_date' => now()->subMonths(3)->startOfMonth(),
        'first_emi_date' => now()->addMonth()->startOfMonth(),
        'total_interest' => '4800.00',
        'total_payable' => '14800.00',
        'emi_amount' => '1233.33',
        'processing_fee' => '0',
        'other_charges' => '0',
    ]);
}

test('super admins can open the foreclosure modal from an active loan', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $loan = Loan::factory()->active()->create();

    Livewire::test('pages::loans.show', ['loan' => $loan])
        ->assertSeeHtml('wire:click="openForeclosureModal"')
        ->call('openForeclosureModal')
        ->assertSet('showForeclosureModal', true)
        ->assertSee('Total foreclosure settlement');
});

test('submitting the foreclosure modal creates a pending request', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $loan = Loan::factory()->active()->create();

    Livewire::test('pages::loans.show', ['loan' => $loan])
        ->call('openForeclosureModal')
        ->set('foreclosure_effective_date', now()->toDateString())
        ->call('submitForeclosureRequest')
        ->assertHasNoErrors()
        ->assertSet('showForeclosureModal', false)
        ->assertRedirect(route('foreclosure.requests'));

    $request = ForeclosureRequest::query()->where('loan_id', $loan->id)->first();

    expect($request)->not->toBeNull()
        ->and($request->approval_status)->toBe('pending')
        ->and($request->payment_status)->toBe('unpaid')
        ->and($request->borrower_id)->toBe($loan->borrower_id)
        ->and((float) $request->total_settlement)->toBeGreaterThan(0);
});

test('a second foreclosure request cannot be submitted while one is pending', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $loan = Loan::factory()->active()->create();

    Livewire::test('pages::loans.show', ['loan' => $loan])
        ->call('openForeclosureModal')
        ->call('submitForeclosureRequest');

    Livewire::test('pages::loans.show', ['loan' => $loan])
        ->assertDontSeeHtml('wire:click="openForeclosureModal"');

    expect(ForeclosureRequest::query()->where('loan_id', $loan->id)->count())->toBe(1);
});

test('waiving accrued interest reduces the settlement to the outstanding principal', function () {
    $loan = foreclosureLoanWithAccruedInterest();
    $service = app(ForeclosureService::class);

    $base = $service->calculateForeclosureAmount($loan, $service->settingsInputs(now()->toDateString()));
    $waived = $service->calculateForeclosureAmount($loan, $service->settingsInputs(now()->toDateString(), true));

    expect(Money::compare($base->accrued_interest, '0'))->toBeGreaterThan(0)
        ->and($waived->waived_interest)->toBe($base->accrued_interest)
        ->and(Money::compare($waived->total_settlement, Money::sub($base->total_settlement, $base->accrued_interest)))->toBe(0)
        ->and(Money::compare($waived->total_settlement, $waived->outstanding_principal))->toBe(0);
});

test('submitting the foreclosure modal with the waiver persists the waived interest', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $loan = foreclosureLoanWithAccruedInterest();

    Livewire::test('pages::loans.show', ['loan' => $loan])
        ->call('openForeclosureModal')
        ->set('foreclosure_effective_date', now()->toDateString())
        ->call('toggleAccruedInterestWaiver')
        ->assertSet('waiveAccruedInterest', true)
        ->call('submitForeclosureRequest')
        ->assertHasNoErrors()
        ->assertSet('showForeclosureModal', false)
        ->assertRedirect(route('foreclosure.requests'));

    $request = ForeclosureRequest::query()->where('loan_id', $loan->id)->firstOrFail();

    expect(Money::compare($request->waived_interest, '0'))->toBeGreaterThan(0)
        ->and($request->waived_interest)->toBe($request->accrued_interest)
        ->and(Money::compare($request->total_settlement, $request->outstanding_principal))->toBe(0);
});

test('admins can open the foreclosure modal from an active loan', function () {
    $this->actingAs(User::factory()->create());

    $loan = Loan::factory()->active()->create();

    Livewire::test('pages::loans.show', ['loan' => $loan])
        ->assertSeeHtml('wire:click="openForeclosureModal"')
        ->call('openForeclosureModal')
        ->assertSet('showForeclosureModal', true)
        ->assertSee('Total foreclosure settlement');
});

test('admins can submit a foreclosure request', function () {
    $this->actingAs(User::factory()->create());

    $loan = Loan::factory()->active()->create();

    Livewire::test('pages::loans.show', ['loan' => $loan])
        ->call('openForeclosureModal')
        ->set('foreclosure_effective_date', now()->toDateString())
        ->call('submitForeclosureRequest')
        ->assertHasNoErrors()
        ->assertRedirect(route('foreclosure.requests'));

    expect(ForeclosureRequest::query()->where('loan_id', $loan->id)->where('approval_status', 'pending')->exists())->toBeTrue();
});
