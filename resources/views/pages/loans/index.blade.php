<?php

use App\Enums\InterestMethod;
use App\Enums\LoanStatus;
use App\Models\Loan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Loans')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $method = '';

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Loan>
     */
    #[Computed]
    public function loans()
    {
        return Loan::query()
            ->with('borrower')
            ->search($this->search)
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->method !== '', fn ($query) => $query->where('interest_method', $this->method))
            ->withCount([
                'schedules',
                'schedules as paid_schedules_count' => fn ($query) => $query->whereIn('status', ['paid', 'waived']),
            ])
            ->withMin(['schedules as next_due_date' => fn ($query) => $query->whereNotIn('status', ['paid', 'waived'])], 'due_date')
            ->latest()
            ->paginate(12);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function summary(): array
    {
        return [
            'active_principal' => (string) Loan::query()->where('status', \App\Enums\LoanStatus::Active)->sum('outstanding_principal'),
            'pending_count' => (string) Loan::query()->where('status', \App\Enums\LoanStatus::Pending)->count(),
            'interest' => (string) Loan::query()->where('status', \App\Enums\LoanStatus::Active)->sum('outstanding_interest'),
        ];
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Portfolio') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Loans') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Flat and reducing-balance loans with complete EMI schedules.') }}</p>
        </div>

        <a href="{{ route('loans.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
            <flux:icon.plus class="size-4" />
            {{ __('New loan') }}
        </a>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="__('Outstanding principal')" :value="\App\Support\Money::format($this->summary['active_principal'])" :hint="__('Active loans')" icon="banknotes" tone="blue" />
        <x-stat-card :label="__('Outstanding interest')" :value="\App\Support\Money::format($this->summary['interest'])" :hint="__('Unearned on active loans')" icon="chart-bar" tone="emerald" />
        <x-stat-card :label="__('Pending approvals')" :value="$this->summary['pending_count']" :hint="__('Awaiting disbursement')" icon="clock" tone="amber" />
    </div>

    <div class="fc-card overflow-hidden">
        <div class="grid gap-3 border-b border-slate-100 p-4 md:grid-cols-[1fr_auto_auto]">
            <div class="relative">
                <flux:icon.magnifying-glass class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search loan code, borrower or mobile…') }}"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pe-3 ps-9 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                >
            </div>

            <select wire:model.live="status" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (\App\Enums\LoanStatus::options() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>

            <select wire:model.live="method" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none">
                <option value="">{{ __('All methods') }}</option>
                @foreach (\App\Enums\InterestMethod::options() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="fc-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('Loan') }}</th>
                        <th class="px-5 py-3">{{ __('Borrower') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Principal') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('EMI') }}</th>
                        <th class="px-5 py-3">{{ __('Next due') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Outstanding') }}</th>
                        <th class="px-5 py-3">{{ __('Status') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->loans as $loan)
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="px-5 py-3">
                                <a href="{{ route('loans.show', $loan) }}" class="font-medium text-blue-600 hover:text-blue-700">{{ $loan->loan_code }}</a>
                                <p class="text-[11px] text-slate-400">{{ $loan->interest_method->label() }} · {{ $loan->tenure_months }}m</p>
                            </td>
                            <td class="px-5 py-3">
                                <a href="{{ route('borrowers.show', $loan->borrower) }}" class="font-medium text-slate-800 hover:text-blue-600">{{ $loan->borrower->full_name }}</a>
                                <p class="text-[11px] text-slate-400">{{ $loan->borrower->mobile }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-slate-700">{{ \App\Support\Money::format($loan->principal_amount) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-slate-700">{{ \App\Support\Money::format($loan->emi_amount) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $loan->next_due_date ? \Illuminate\Support\Carbon::parse($loan->next_due_date)->format('d M Y') : '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($loan->outstanding_principal) }}</td>
                            <td class="px-5 py-3"><x-status-badge :status="$loan->status" /> <x-status-badge :status="$loan->application_status" class="mt-0.5 !px-1.5 !py-0.5 !text-[9px]" /></td>
                            <td class="px-5 py-3 text-end">
                                <a href="{{ route('loans.show', $loan) }}" class="inline-flex rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                                    <flux:icon.eye class="size-4" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-14 text-center">
                                <p class="text-sm font-medium text-slate-600">{{ __('No loans found') }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ __('Create a loan to generate an EMI schedule.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->loans->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $this->loans->links() }}</div>
        @endif
    </div>
</div>
