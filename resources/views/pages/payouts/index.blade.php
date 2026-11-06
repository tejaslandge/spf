<?php

use App\Enums\PayoutStatus;
use App\Models\InvestorPayout;
use App\Services\DistributionReportService;
use App\Services\InvestorPayoutService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Investor Payout Dashboard')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $month = '';

    public string $status = '';

    public bool $showProcessModal = false;

    public bool $showRejectModal = false;

    public ?int $payoutId = null;

    public string $payment_reference = '';

    public string $paid_at = '';

    public string $notes = '';

    public string $rejection_reason = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->paid_at = now()->format('Y-m-d');
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, InvestorPayout>
     */
    #[Computed]
    public function payouts()
    {
        return InvestorPayout::query()
            ->with(['investor', 'items.ledger.loanInvestorDistribution', 'items.ledger.payment', 'items.loan'])
            ->when($this->month !== '', fn ($query) => $query->whereBetween('period_month', [
                \Illuminate\Support\Carbon::parse($this->month.'-01')->startOfMonth(),
                \Illuminate\Support\Carbon::parse($this->month.'-01')->endOfMonth(),
            ]))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->search !== '', function ($query): void {
                $term = '%'.strtolower($this->search).'%';
                $query->where(fn ($query) => $query
                    ->whereRaw('LOWER(payout_code) LIKE ?', [$term])
                    ->orWhereHas('investor', fn ($query) => $query
                        ->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(investor_code) LIKE ?', [$term])));
            })
            ->latest('period_month')
            ->latest('id')
            ->paginate(\App\Services\Settings\UserPreferenceService::rowsPerPageFor(auth()->user(), 15));
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    #[Computed]
    public function previouslyPaid(): \Illuminate\Support\Collection
    {
        return InvestorPayout::query()
            ->select('investor_id', DB::raw('SUM(net_amount) as paid_total'))
            ->where('status', \App\Enums\PayoutStatus::Paid->value)
            ->groupBy('investor_id')
            ->pluck('paid_total', 'investor_id');
    }

    #[Computed]
    public function summary(): array
    {
        return app(DistributionReportService::class)->summary();
    }

    public function generateForMonth(): void
    {
        $this->validate(['month' => ['required', 'date_format:Y-m']]);

        $count = app(InvestorPayoutService::class)->generate(
            \Carbon\CarbonImmutable::parse($this->month.'-01'),
            auth()->user(),
        );

        unset($this->payouts, $this->summary);

        Flux::toast(variant: 'success', text: $count > 0
            ? __('Generated :count investor payout(s) from collected interest.', ['count' => $count])
            : __('No unlinked collected-interest entitlements found for this period.'));
    }

    public function approve(int $payoutId): void
    {
        $this->runAction($payoutId, fn (InvestorPayout $payout) => app(InvestorPayoutService::class)->approve($payout, auth()->user()), __('Payout approved.'));
    }

    public function cancel(int $payoutId): void
    {
        $this->runAction($payoutId, fn (InvestorPayout $payout) => app(InvestorPayoutService::class)->cancel($payout, auth()->user()), __('Payout cancelled and entitlements released.'));
    }

    public function openProcessModal(int $payoutId): void
    {
        $this->payoutId = $payoutId;
        $this->payment_reference = '';
        $this->paid_at = now()->format('Y-m-d');
        $this->notes = '';
        $this->showProcessModal = true;
    }

    public function process(): void
    {
        $validated = $this->validate([
            'payoutId' => ['required', 'exists:investor_payouts,id'],
            'payment_reference' => ['required', 'string', 'max:255'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payout = InvestorPayout::query()->findOrFail($validated['payoutId']);

        try {
            app(InvestorPayoutService::class)->process($payout, auth()->user(), $validated['payment_reference'], $validated['paid_at'], $validated['notes'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            $this->addError('payment_reference', $exception->getMessage());

            return;
        }

        $this->showProcessModal = false;
        unset($this->payouts, $this->summary, $this->previouslyPaid);
        Flux::toast(variant: 'success', text: __('Payout processed and posted to the ledger.'));
    }

    public function openRejectModal(int $payoutId): void
    {
        $this->payoutId = $payoutId;
        $this->rejection_reason = '';
        $this->showRejectModal = true;
    }

    public function reject(): void
    {
        $validated = $this->validate([
            'payoutId' => ['required', 'exists:investor_payouts,id'],
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $payout = InvestorPayout::query()->findOrFail($validated['payoutId']);

        try {
            app(InvestorPayoutService::class)->reject($payout, auth()->user(), $validated['rejection_reason']);
        } catch (\InvalidArgumentException $exception) {
            $this->addError('rejection_reason', $exception->getMessage());

            return;
        }

        $this->showRejectModal = false;
        unset($this->payouts, $this->summary);
        Flux::toast(variant: 'success', text: __('Payout rejected. Entitlements are available to regenerate.'));
    }

    private function runAction(int $payoutId, callable $action, string $message): void
    {
        $payout = InvestorPayout::query()->findOrFail($payoutId);

        try {
            $action($payout);
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->payouts, $this->summary, $this->previouslyPaid);
        Flux::toast(variant: 'success', text: $message);
    }
};
?>

<div class="mx-auto max-w-[1500px] space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Distribution') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Investor Interest Payouts') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Payouts are generated only from interest actually collected on verified EMI payments.') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('payouts.history') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300">
                <flux:icon.clock class="size-4" />
                {{ __('Payout history') }}
            </a>
            @if (auth()->user()->isSuperAdmin())
                <button type="button" wire:click="generateForMonth" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                    <flux:icon.arrow-path class="size-4" />
                    {{ __('Generate :month', ['month' => \Illuminate\Support\Carbon::parse($this->month.'-01')->format('M Y')]) }}
                </button>
            @endif
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat-card :label="__('Interest collected')" :value="\App\Support\Money::format($this->summary['collected_interest'])" :hint="__('Actual interest component')" icon="receipt-percent" tone="navy" />
        <x-stat-card :label="__('Investor earned')" :value="\App\Support\Money::format($this->summary['investor_earned'])" :hint="__('Lifetime net of reversals')" icon="chart-bar" tone="blue" />
        <x-stat-card :label="__('Pending payout')" :value="\App\Support\Money::format($this->summary['investor_pending'])" :hint="__('Awaiting review')" icon="clock" tone="amber" />
        <x-stat-card :label="__('Approved payout')" :value="\App\Support\Money::format($this->summary['investor_approved'])" :hint="__('Ready to process')" icon="check-circle" tone="purple" />
        <x-stat-card :label="__('Paid payout')" :value="\App\Support\Money::format($this->summary['investor_paid'])" :hint="__('Settled to investors')" icon="banknotes" tone="emerald" />
    </div>

    <div class="fc-card overflow-hidden">
        <div class="grid gap-3 border-b border-slate-100 p-4 md:grid-cols-[1fr_auto_auto]">
            <div class="relative">
                <flux:icon.magnifying-glass class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search payout code or investor…') }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pe-3 ps-9 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>

            <input type="month" wire:model.live="month" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none">

            <select wire:model.live="status" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (\App\Enums\PayoutStatus::cases() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="fc-table-head">
                    <tr>
                        <th class="px-4 py-3">{{ __('Payout') }}</th>
                        <th class="px-4 py-3">{{ __('Investor') }}</th>
                        <th class="px-4 py-3">{{ __('Loans') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Principal') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Rate') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Collected') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Earned') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Paid before') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Pending') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Approved') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Paid') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3">{{ __('Reference') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->payouts as $payout)
                        @php
                            $items = $payout->items;
                            $ledgers = $items->pluck('ledger')->filter();
                            $loanIds = $ledgers->pluck('loan_id')->unique();
                            $principal = $ledgers->reduce(fn ($carry, $ledger) => \App\Support\Money::add($carry, (string) ($ledger->loanInvestorDistribution?->principal_amount ?? 0)), '0');
                            $rates = $ledgers->pluck('applicable_rate')->filter()->unique()->map(fn ($rate) => rtrim(rtrim((string) $rate, '0'), '.').'%')->implode(', ');
                            $collected = $ledgers->pluck('payment')->filter()->unique('id')->reduce(fn ($carry, $payment) => \App\Support\Money::add($carry, (string) $payment->interest_component), '0');
                            $paidBefore = (string) ($this->previouslyPaid[$payout->investor_id] ?? '0');
                        @endphp
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800">{{ $payout->payout_code }}</p>
                                <p class="text-[11px] text-slate-400">{{ $payout->period_month->format('M Y') }} · #{{ $payout->batch_no }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('investors.show', $payout->investor) }}" class="font-medium text-slate-800 hover:text-blue-600">{{ $payout->investor->name }}</a>
                                <p class="text-[11px] text-slate-400">{{ $payout->investor->investor_code }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($ledgers->unique('loan_id') as $ledger)
                                        <a href="{{ route('loans.distribution', $ledger->loan_id) }}" class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 hover:bg-blue-50 hover:text-blue-700">{{ $ledger->loan?->loan_code ?? '#'.$ledger->loan_id }}</a>
                                    @endforeach
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-slate-600">{{ \App\Support\Money::format($principal) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end text-xs text-slate-500">{{ $rates ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-slate-600">{{ \App\Support\Money::format($collected) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($payout->gross_interest) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-slate-500">{{ \App\Support\Money::format($paidBefore) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-amber-700">{{ $payout->status === \App\Enums\PayoutStatus::Pending ? \App\Support\Money::format($payout->net_amount) : '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-violet-700">{{ $payout->status === \App\Enums\PayoutStatus::Approved ? \App\Support\Money::format($payout->net_amount) : '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-emerald-700">{{ $payout->status === \App\Enums\PayoutStatus::Paid ? \App\Support\Money::format($payout->net_amount) : '—' }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$payout->status" /></td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $payout->payment_reference ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if (auth()->user()->isSuperAdmin())
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        @if ($payout->status === \App\Enums\PayoutStatus::Pending)
                                            <button type="button" wire:click="approve({{ $payout->id }})" class="text-[11px] font-semibold text-violet-600 hover:text-violet-700">{{ __('Approve') }}</button>
                                            <button type="button" wire:click="openRejectModal({{ $payout->id }})" class="text-[11px] font-semibold text-rose-600 hover:text-rose-700">{{ __('Reject') }}</button>
                                        @elseif ($payout->status === \App\Enums\PayoutStatus::Approved)
                                            <button type="button" wire:click="openProcessModal({{ $payout->id }})" class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-700">{{ __('Process') }}</button>
                                            <button type="button" wire:click="openRejectModal({{ $payout->id }})" class="text-[11px] font-semibold text-rose-600 hover:text-rose-700">{{ __('Reject') }}</button>
                                        @elseif ($payout->status === \App\Enums\PayoutStatus::Paid)
                                            <span class="text-[11px] text-slate-400">{{ $payout->paid_at?->format(\App\Support\Regional::dateFormat()) }}</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-[11px] text-slate-400">{{ __('Super Admin review') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="14" class="px-5 py-14 text-center"><p class="text-sm font-medium text-slate-600">{{ __('No payouts found') }}</p><p class="mt-1 text-xs text-slate-400">{{ __('Generate payouts for a month with collected interest.') }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->payouts->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $this->payouts->links() }}</div>
        @endif
    </div>

    @if ($showProcessModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showProcessModal', false)"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <h3 class="text-base font-semibold">{{ __('Process approved payout') }}</h3>
                <p class="mt-0.5 text-xs text-slate-400">{{ __('Posts a payable settlement journal entry.') }}</p>
                <form wire:submit="process" class="mt-4 space-y-3">
                    <flux:input wire:model="payment_reference" :label="__('Payment transaction reference')" placeholder="UTR / transaction ID" required maxlength="255" />
                    @error('payment_reference') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    <flux:input wire:model="paid_at" :label="__('Paid on')" type="date" required max="{{ now()->format('Y-m-d') }}" />
                    @error('paid_at') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" maxlength="1000" />
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showProcessModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Process payout') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showRejectModal', false)"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <h3 class="text-base font-semibold">{{ __('Reject payout') }}</h3>
                <p class="mt-0.5 text-xs text-slate-400">{{ __('Linked entitlements become available for regeneration.') }}</p>
                <form wire:submit="reject" class="mt-4 space-y-3">
                    <flux:textarea wire:model="rejection_reason" :label="__('Reason')" rows="3" required maxlength="1000" />
                    @error('rejection_reason') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showRejectModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="danger" class="!rounded-xl">{{ __('Reject payout') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
