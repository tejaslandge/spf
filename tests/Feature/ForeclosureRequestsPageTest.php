<?php

use App\Enums\LoanStatus;
use App\Models\ForeclosureRequest;
use App\Models\Loan;
use App\Models\User;
use Livewire\Livewire;

test('super admins can view the foreclosure requests page', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('foreclosure.requests'))
        ->assertOk()
        ->assertSee('Foreclosure requests');
});

test('admins can view the foreclosure requests page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('foreclosure.requests'))
        ->assertOk()
        ->assertSee('Foreclosure requests');
});

test('the foreclosure requests page lists existing requests', function () {
    $loan = Loan::factory()->active()->create();

    ForeclosureRequest::query()->create([
        'loan_id' => $loan->id,
        'borrower_id' => $loan->borrower_id,
        'outstanding_principal' => '10000.00',
        'accrued_interest' => '500.00',
        'other_outstanding_dues' => '0.00',
        'foreclosure_charges' => '250.00',
        'total_settlement' => '10750.00',
        'eligible_adjustments' => '0.00',
        'foreclosure_request_date' => now()->toDateString(),
        'effective_foreclosure_date' => now()->addDays(7)->toDateString(),
        'approval_status' => 'pending',
        'payment_status' => 'unpaid',
    ]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('foreclosure.requests'))
        ->assertOk()
        ->assertSee($loan->loan_code)
        ->assertSee('Pending');
});

test('foreclosure requests can be searched by loan code', function () {
    $loan = Loan::factory()->active()->create();

    ForeclosureRequest::query()->create([
        'loan_id' => $loan->id,
        'borrower_id' => $loan->borrower_id,
        'outstanding_principal' => '10000.00',
        'accrued_interest' => '500.00',
        'other_outstanding_dues' => '0.00',
        'foreclosure_charges' => '250.00',
        'total_settlement' => '10750.00',
        'eligible_adjustments' => '0.00',
        'foreclosure_request_date' => now()->toDateString(),
        'effective_foreclosure_date' => now()->addDays(7)->toDateString(),
        'approval_status' => 'pending',
        'payment_status' => 'unpaid',
    ]);

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test('pages::loans.foreclosure-requests')
        ->set('search', $loan->loan_code)
        ->assertSee($loan->loan_code)
        ->assertSee('Pending');
});

test('admins can approve a pending foreclosure request', function () {
    $this->actingAs(User::factory()->create());

    $loan = Loan::factory()->active()->create();

    $request = ForeclosureRequest::query()->create([
        'loan_id' => $loan->id,
        'borrower_id' => $loan->borrower_id,
        'outstanding_principal' => '10000.00',
        'accrued_interest' => '500.00',
        'other_outstanding_dues' => '0.00',
        'foreclosure_charges' => '250.00',
        'total_settlement' => '10750.00',
        'eligible_adjustments' => '0.00',
        'foreclosure_request_date' => now()->toDateString(),
        'effective_foreclosure_date' => now()->addDays(7)->toDateString(),
        'approval_status' => 'pending',
        'payment_status' => 'unpaid',
    ]);

    Livewire::test('pages::loans.foreclosure-requests')
        ->call('approve', $request->id)
        ->assertHasNoErrors();

    expect($request->refresh()->approval_status)->toBe('approved')
        ->and($loan->refresh()->status)->toBe(LoanStatus::Foreclosed);
});

test('approving a pending request forecloses the loan and records the settlement payment', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $loan = Loan::factory()->active()->create();

    $existingPayment = $loan->payments()->create([
        'amount' => '100.00',
        'principal_component' => '100.00',
        'interest_component' => '0.00',
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
    ]);

    $request = ForeclosureRequest::query()->create([
        'loan_id' => $loan->id,
        'borrower_id' => $loan->borrower_id,
        'outstanding_principal' => '10000.00',
        'accrued_interest' => '500.00',
        'other_outstanding_dues' => '0.00',
        'foreclosure_charges' => '250.00',
        'total_settlement' => '10750.00',
        'eligible_adjustments' => '0.00',
        'foreclosure_request_date' => now()->toDateString(),
        'effective_foreclosure_date' => now()->addDays(7)->toDateString(),
        'approval_status' => 'pending',
        'payment_status' => 'unpaid',
    ]);

    Livewire::test('pages::loans.foreclosure-requests')
        ->call('approve', $request->id)
        ->assertHasNoErrors();

    $request->refresh();
    $loan->refresh();

    $payment = $loan->payments()->where('reference', 'FORECLOSURE-'.$request->id)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->receipt_no)->not->toBe($existingPayment->receipt_no)
        ->and($payment->amount)->toBe('10750.00')
        ->and($request->approval_status)->toBe('approved')
        ->and($request->payment_status)->toBe('paid')
        ->and($loan->status)->toBe(LoanStatus::Foreclosed);
});

test('approving a waived-interest request records a payment without an interest component', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $loan = Loan::factory()->active()->create();

    $request = ForeclosureRequest::query()->create([
        'loan_id' => $loan->id,
        'borrower_id' => $loan->borrower_id,
        'outstanding_principal' => '10000.00',
        'accrued_interest' => '500.00',
        'other_outstanding_dues' => '0.00',
        'foreclosure_charges' => '0.00',
        'total_settlement' => '10000.00',
        'eligible_adjustments' => '0.00',
        'waived_interest' => '500.00',
        'foreclosure_request_date' => now()->toDateString(),
        'effective_foreclosure_date' => now()->toDateString(),
        'approval_status' => 'pending',
        'payment_status' => 'unpaid',
    ]);

    Livewire::test('pages::loans.foreclosure-requests')
        ->call('approve', $request->id)
        ->assertHasNoErrors();

    $request->refresh();
    $loan->refresh();

    $payment = $loan->payments()->where('reference', 'FORECLOSURE-'.$request->id)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->amount)->toBe('10000.00')
        ->and($payment->principal_component)->toBe('10000.00')
        ->and($payment->interest_component)->toBe('0.00')
        ->and($loan->status)->toBe(LoanStatus::Foreclosed);
});

test('the approve confirmation shows the settlement breakdown before approving', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $loan = Loan::factory()->active()->create();

    $request = ForeclosureRequest::query()->create([
        'loan_id' => $loan->id,
        'borrower_id' => $loan->borrower_id,
        'outstanding_principal' => '10000.00',
        'accrued_interest' => '500.00',
        'other_outstanding_dues' => '0.00',
        'foreclosure_charges' => '250.00',
        'total_settlement' => '10000.00',
        'eligible_adjustments' => '0.00',
        'waived_interest' => '500.00',
        'foreclosure_request_date' => now()->toDateString(),
        'effective_foreclosure_date' => now()->addDays(7)->toDateString(),
        'approval_status' => 'pending',
        'payment_status' => 'unpaid',
    ]);

    Livewire::test('pages::loans.foreclosure-requests')
        ->call('openApprove', $request->id)
        ->assertSet('approvingRequestId', $request->id)
        ->assertSee('Approve foreclosure request')
        ->assertSee('Settlement breakdown')
        ->assertSee('Accrued interest waived')
        ->assertSee('Approve & foreclose');

    expect($request->refresh()->approval_status)->toBe('pending');
});
