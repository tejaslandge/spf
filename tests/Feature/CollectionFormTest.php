<?php

use App\Models\Loan;
use App\Models\User;
use Livewire\Livewire;

test('a collection cannot be dated in the future', function () {
    $this->actingAs(User::factory()->create());
    $loan = Loan::factory()->active()->create();

    Livewire::test('pages::collections.index')
        ->set('loan_id', (string) $loan->id)
        ->set('amount', '1000')
        ->set('payment_date', now()->addDay()->format('Y-m-d'))
        ->set('payment_method', 'cash')
        ->call('save')
        ->assertHasErrors(['payment_date' => 'before_or_equal']);
});

test('non-cash collections require a payment reference', function () {
    $this->actingAs(User::factory()->create());
    $loan = Loan::factory()->active()->create();

    Livewire::test('pages::collections.index')
        ->set('loan_id', (string) $loan->id)
        ->set('amount', '1000')
        ->set('payment_date', now()->format('Y-m-d'))
        ->set('payment_method', 'upi')
        ->set('reference', '')
        ->call('save')
        ->assertHasErrors(['reference' => 'required']);
});
