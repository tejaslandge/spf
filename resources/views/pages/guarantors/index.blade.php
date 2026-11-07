<?php

use App\Models\Guarantor;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Guarantors')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Guarantor>
     */
    #[Computed]
    public function guarantors()
    {
        return Guarantor::query()
            ->search($this->search)
            ->withCount('loans')
            ->latest()
            ->paginate(\App\Services\Settings\UserPreferenceService::rowsPerPageFor(auth()->user(), 12));
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Portfolio') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Guarantors') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Registered guarantors available for borrower and loan applications.') }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        {{ __('Guarantor profile details cannot be edited from this page. To update guarantor information, please navigate to the Borrower page and edit the corresponding borrower\'s profile.') }}
    </div>

    <div class="fc-card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-xs">
                <flux:icon.magnifying-glass class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search name or mobile…') }}"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pe-3 ps-9 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                >
            </div>

            <p class="text-xs text-slate-400">{{ trans_choice(':count guarantor|:count guarantors', $this->guarantors->total(), ['count' => $this->guarantors->total()]) }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="fc-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('Guarantor') }}</th>
                        <th class="px-5 py-3">{{ __('Mobile') }}</th>
                        <th class="px-5 py-3">{{ __('Location') }}</th>
                        <th class="px-5 py-3">{{ __('Loans') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->guarantors as $guarantor)
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <flux:avatar :name="$guarantor->full_name" :initials="str($guarantor->full_name)->substr(0, 2)" size="sm" />
                                    <div class="min-w-0">
                                        <a href="{{ route('guarantors.show', $guarantor) }}" class="block truncate font-medium text-slate-800 hover:text-blue-600">{{ $guarantor->full_name }}</a>
                                        <p class="truncate text-[11px] text-slate-400">{{ $guarantor->occupation ?: __('Guarantor') }}@if ($guarantor->borrower_id) · {{ __('Borrower profile') }}@endif</p>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $guarantor->mobile_number }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ collect([$guarantor->city, $guarantor->state])->filter()->implode(', ') ?: '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $guarantor->loans_count }}</td>
                            <td class="px-5 py-3 text-end">
                                <a href="{{ route('guarantors.show', $guarantor) }}" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" title="{{ __('View') }}">
                                    <flux:icon.eye class="size-4" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-14 text-center">
                                <p class="text-sm font-medium text-slate-600">{{ __('No guarantors found') }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ __('Add a guarantor from a borrower or during loan creation.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->guarantors->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">
                {{ $this->guarantors->links() }}
            </div>
        @endif
    </div>
</div>
