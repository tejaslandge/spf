<?php

use App\Models\Investor;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Investors')] class extends Component
{
    use WithPagination;

    public string $search = '';

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Investor>
     */
    #[Computed]
    public function investors()
    {
        return Investor::query()
            ->search($this->search)
            ->withCount(['allocations' => fn ($query) => $query->where('status', 'active')])
            ->withSum(['allocations as active_capital_sum' => fn ($query) => $query->where('status', 'active')], 'principal_amount')
            ->latest()
            ->paginate(12);
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Capital') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Investors') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Investor capital, loan-wise exposure and monthly interest payouts.') }}</p>
        </div>

        <a href="{{ route('investors.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
            <flux:icon.plus class="size-4" />
            {{ __('Add investor') }}
        </a>
    </div>

    <div class="fc-card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-xs">
                <flux:icon.magnifying-glass class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search name, mobile or code…') }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pe-3 ps-9 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <p class="text-xs text-slate-400">{{ trans_choice(':count investor|:count investors', $this->investors->total(), ['count' => $this->investors->total()]) }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="fc-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('Investor') }}</th>
                        <th class="px-5 py-3">{{ __('Contact') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Allocated capital') }}</th>
                        <th class="px-5 py-3">{{ __('Allocations') }}</th>
                        <th class="px-5 py-3">{{ __('Status') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->investors as $investor)
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <flux:avatar :name="$investor->name" :initials="str($investor->name)->substr(0, 2)" size="sm" />
                                    <div class="min-w-0">
                                        <a href="{{ route('investors.show', $investor) }}" class="block truncate font-medium text-slate-800 hover:text-blue-600">{{ $investor->name }}</a>
                                        <p class="text-[11px] text-slate-400">{{ $investor->investor_code }} · {{ $investor->pan_number ?: '—' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-xs text-slate-600">{{ $investor->mobile }}</p>
                                <p class="text-[11px] text-slate-400">{{ $investor->email ?: '—' }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-end font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($investor->active_capital_sum) }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $investor->allocations_count }}</td>
                            <td class="px-5 py-3"><x-status-badge :status="$investor->status" /></td>
                            <td class="px-5 py-3 text-end">
                                <a href="{{ route('investors.show', $investor) }}" class="inline-flex rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"><flux:icon.eye class="size-4" /></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-14 text-center"><p class="text-sm font-medium text-slate-600">{{ __('No investors found') }}</p><p class="mt-1 text-xs text-slate-400">{{ __('Add an investor to start allocating capital.') }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->investors->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $this->investors->links() }}</div>
        @endif
    </div>
</div>
