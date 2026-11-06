<?php

use App\Enums\PayoutStatus;
use App\Models\InvestorPayout;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Payout History')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->from = CarbonImmutable::today()->startOfYear()->toDateString();
        $this->to = CarbonImmutable::today()->toDateString();
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, InvestorPayout>
     */
    #[Computed]
    public function payouts()
    {
        return InvestorPayout::query()
            ->with(['investor', 'approvedBy', 'rejectedBy'])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->from !== '', fn ($query) => $query->whereDate('period_month', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('period_month', '<=', $this->to))
            ->when($this->search !== '', function ($query): void {
                $term = '%'.strtolower($this->search).'%';

                $query->where(fn ($query) => $query
                    ->whereRaw('LOWER(payout_code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(payment_reference) LIKE ?', [$term])
                    ->orWhereHas('investor', fn ($query) => $query
                        ->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(investor_code) LIKE ?', [$term])));
            })
            ->latest('period_month')
            ->latest('id')
            ->paginate(\App\Services\Settings\UserPreferenceService::rowsPerPageFor(auth()->user(), 20));
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function totals(): array
    {
        $query = InvestorPayout::query()
            ->when($this->from !== '', fn ($query) => $query->whereDate('period_month', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('period_month', '<=', $this->to));

        return [
            'gross' => Money::round((string) (clone $query)->sum('gross_interest')),
            'fee' => Money::round((string) (clone $query)->sum('management_fee')),
            'tds' => Money::round((string) (clone $query)->sum('tds_amount')),
            'net' => Money::round((string) (clone $query)->sum('net_amount')),
            'paid' => Money::round((string) (clone $query)->where('status', PayoutStatus::Paid->value)->sum('net_amount')),
            'count' => (string) (clone $query)->count(),
        ];
    }
};
?>

<div class="mx-auto max-w-[1500px] space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Distribution') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Payout History') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Immutable record of every investor interest payout, including gross, deductions and settlement references.') }}</p>
        </div>

        <a href="{{ route('payouts.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300">
            <flux:icon.arrow-left class="size-4" />
            {{ __('Back to payout dashboard') }}
        </a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat-card :label="__('Payouts')" :value="$this->totals['count']" :hint="__('In selected period')" icon="document-text" tone="navy" />
        <x-stat-card :label="__('Gross interest')" :value="\App\Support\Money::format($this->totals['gross'])" :hint="__('Earned by investors')" icon="chart-bar" tone="blue" />
        <x-stat-card :label="__('Fees & TDS')" :value="\App\Support\Money::format(\App\Support\Money::add($this->totals['fee'], $this->totals['tds']))" :hint="__('Withheld at settlement')" icon="scissors" tone="amber" />
        <x-stat-card :label="__('Net payable')" :value="\App\Support\Money::format($this->totals['net'])" :hint="__('Gross less deductions')" icon="banknotes" tone="purple" />
        <x-stat-card :label="__('Paid out')" :value="\App\Support\Money::format($this->totals['paid'])" :hint="__('Settled to investors')" icon="check-circle" tone="emerald" />
    </div>

    <div class="fc-card overflow-hidden">
        <div class="grid gap-3 border-b border-slate-100 p-4 lg:grid-cols-[1fr_auto_auto_auto]">
            <div class="relative">
                <flux:icon.magnifying-glass class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search payout, investor or reference…') }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pe-3 ps-9 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>

            <input type="date" wire:model.live="from" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none">

            <input type="date" wire:model.live="to" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none">

            <select wire:model.live="status" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (PayoutStatus::cases() as $case)
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
                        <th class="px-4 py-3">{{ __('Period') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Gross') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Fee') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('TDS') }}</th>
                        <th class="px-4 py-3 text-end">{{ __('Net') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3">{{ __('Settled') }}</th>
                        <th class="px-4 py-3">{{ __('Reference') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->payouts as $payout)
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800">{{ $payout->payout_code }}</p>
                                <p class="text-[11px] text-slate-400">{{ __('Batch #:batch', ['batch' => $payout->batch_no]) }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('investors.show', $payout->investor) }}" class="font-medium text-slate-800 hover:text-blue-600">{{ $payout->investor->name }}</a>
                                <p class="text-[11px] text-slate-400">{{ $payout->investor->investor_code }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $payout->period_month->format('M Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-slate-600">{{ \App\Support\Money::format($payout->gross_interest) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-slate-500">{{ \App\Support\Money::format($payout->management_fee) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-slate-500">{{ \App\Support\Money::format($payout->tds_amount) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-end font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($payout->net_amount) }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$payout->status" /></td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{{ $payout->paid_at?->format(\App\Support\Regional::dateFormat()) ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $payout->payment_reference ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-5 py-14 text-center"><p class="text-sm font-medium text-slate-600">{{ __('No payouts in this period') }}</p><p class="mt-1 text-xs text-slate-400">{{ __('Adjust the filters or generate payouts from the dashboard.') }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->payouts->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $this->payouts->links() }}</div>
        @endif
    </div>
</div>
