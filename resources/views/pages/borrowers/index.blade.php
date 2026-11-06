<?php

use App\Models\Borrower;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Borrowers')] class extends Component
{
    public string $search = '';

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Borrower>
     */
    #[Computed]
    public function borrowers()
    {
        return Borrower::query()
            ->search($this->search)
            ->withCount('loans')
            ->withSum('loans as outstanding_principal_sum', 'outstanding_principal')
            ->latest()
            ->paginate(12);
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Portfolio') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Borrowers') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('KYC, contact details and loan exposure for every borrower.') }}</p>
        </div>

        <a href="{{ route('borrowers.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
            <flux:icon.plus class="size-4" />
            {{ __('Add borrower') }}
        </a>
    </div>

    <div class="fc-card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-xs">
                <flux:icon.magnifying-glass class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search name, mobile or code…') }}"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pe-3 ps-9 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                >
            </div>

            <p class="text-xs text-slate-400">{{ trans_choice(':count borrower|:count borrowers', $this->borrowers->total(), ['count' => $this->borrowers->total()]) }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="fc-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('Borrower') }}</th>
                        <th class="px-5 py-3">{{ __('Mobile') }}</th>
                        <th class="px-5 py-3">{{ __('Loans') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Outstanding') }}</th>
                        <th class="px-5 py-3">{{ __('Status') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->borrowers as $borrower)
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <flux:avatar :name="$borrower->full_name" :initials="str($borrower->full_name)->substr(0, 2)" size="sm" />
                                    <div class="min-w-0">
                                        <a href="{{ route('borrowers.show', $borrower) }}" class="block truncate font-medium text-slate-800 hover:text-blue-600">{{ $borrower->full_name }}</a>
                                        <p class="text-[11px] text-slate-400">{{ $borrower->borrower_code }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $borrower->mobile }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $borrower->loans_count }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($borrower->outstanding_principal_sum) }}</td>
                            <td class="px-5 py-3"><x-status-badge :status="$borrower->status" /></td>
                            <td class="px-5 py-3 text-end">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('borrowers.show', $borrower) }}" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" title="{{ __('View') }}">
                                        <flux:icon.eye class="size-4" />
                                    </a>
                                    <a href="{{ route('borrowers.edit', $borrower) }}" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" title="{{ __('Edit') }}">
                                        <flux:icon.pencil-square class="size-4" />
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-14 text-center">
                                <p class="text-sm font-medium text-slate-600">{{ __('No borrowers found') }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ __('Add your first borrower to start issuing loans.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->borrowers->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">
                {{ $this->borrowers->links() }}
            </div>
        @endif
    </div>
</div>
