<?php

use App\Enums\PayoutStatus;
use App\Models\Investor;
use App\Models\InvestorPayout;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\AccountingSeeder;
use Livewire\Livewire;

test('admins can view the reports page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Reports')
        ->assertSee('Export CSV');
});

test('admins can view the investor payouts page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('payouts.index'))
        ->assertOk()
        ->assertSee('Investor Interest Payouts');
});

test('guests are redirected away from reports and payouts', function () {
    $this->get(route('reports.index'))->assertRedirect(route('login'));
    $this->get(route('payouts.index'))->assertRedirect(route('login'));
});

test('the collections report can be exported as csv', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::reports.index')
        ->call('exportCsv')
        ->assertFileDownloaded();
});

test('generating payouts for a month without allocations is safe', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::payouts.index')
        ->set('month', now()->format('Y-m'))
        ->call('generateForMonth')
        ->assertOk();
});

test('a payout cannot be processed without a payment reference', function () {
    $this->actingAs(User::factory()->create());

    $payout = InvestorPayout::query()->create([
        'investor_id' => Investor::factory()->create()->id,
        'period_month' => now()->startOfMonth(),
        'gross_interest' => '1000.00',
        'management_fee' => '100.00',
        'tds_amount' => '100.00',
        'net_amount' => '800.00',
        'status' => PayoutStatus::Approved,
    ]);

    Livewire::test('pages::payouts.index')
        ->call('openProcessModal', $payout->id)
        ->set('payment_reference', '')
        ->call('process')
        ->assertHasErrors(['payment_reference' => 'required']);
});

test('processing a payout posts a balanced journal entry', function () {
    $this->seed(AccountingSeeder::class);
    $this->actingAs(User::factory()->create());

    $investor = Investor::factory()->create();

    $payout = InvestorPayout::query()->create([
        'investor_id' => $investor->id,
        'period_month' => now()->startOfMonth(),
        'gross_interest' => '1000.00',
        'management_fee' => '100.00',
        'tds_amount' => '100.00',
        'net_amount' => '800.00',
        'status' => PayoutStatus::Approved,
    ]);

    Livewire::test('pages::payouts.index')
        ->call('openProcessModal', $payout->id)
        ->set('payment_reference', 'UTR-123')
        ->call('process')
        ->assertOk();

    $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::Paid)
        ->and($payout->payment_reference)->toBe('UTR-123');

    $entry = JournalEntry::query()
        ->where('reference_type', $payout->getMorphClass())
        ->where('reference_id', $payout->id)
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->isBalanced())->toBeTrue()
        ->and(bccomp((string) $entry->lines()->sum('debit'), '1000', 2))->toBe(0)
        ->and(bccomp((string) $entry->lines()->sum('credit'), '1000', 2))->toBe(0);
});
