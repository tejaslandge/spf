<?php

use App\Enums\PaymentMethod;
use App\Models\EmiPayment;
use App\Models\Loan;
use App\Services\EmiCollectionService;
use App\Services\LoanPenaltyService;
use App\Services\Settings\UserPreferenceService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('EMI Collections')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $month = '';

    public bool $showModal = false;

    public bool $showReverseModal = false;

    public ?int $reversePaymentId = null;

    public string $reversal_reason = '';

    public string $loan_id = '';

    public string $amount = '';

    public string $payment_date = '';

    public string $payment_method = 'cash';

    public string $reference = '';

    public string $notes = '';

    public string $penalty_amount = '';

    public function mount(): void
    {
        $this->payment_date = now()->format('Y-m-d');
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, EmiPayment>
     */
    #[Computed]
    public function payments()
    {
        return EmiPayment::query()
            ->with(['loan.borrower', 'collectedBy', 'distributions'])
            ->when($this->search !== '', function ($query): void {
                $term = '%'.strtolower($this->search).'%';

                $query->where(fn ($query) => $query
                    ->whereRaw('LOWER(receipt_no) LIKE ?', [$term])
                    ->orWhereHas('loan', fn ($query) => $query
                        ->whereRaw('LOWER(loan_code) LIKE ?', [$term])
                        ->orWhereHas('borrower', fn ($query) => $query
                            ->whereRaw('LOWER(full_name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(mobile) LIKE ?', [$term]))));
            })
            ->when($this->month !== '', fn ($query) => $query->whereBetween('payment_date', [
                \Illuminate\Support\Carbon::parse($this->month.'-01')->startOfMonth(),
                \Illuminate\Support\Carbon::parse($this->month.'-01')->endOfMonth(),
            ]))
            ->latest('payment_date')
            ->latest('id')
            ->paginate(UserPreferenceService::rowsPerPageFor(auth()->user(), 15));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Loan>
     */
    #[Computed]
    public function activeLoans()
    {
        return Loan::query()
            ->with('borrower')
            ->where('status', 'active')
            ->orderBy('loan_code')
            ->get();
    }

    #[Computed]
    public function monthTotal(): string
    {
        return (string) EmiPayment::query()
            ->where('is_reversed', false)
            ->whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');
    }

    #[Computed]
    public function receiptsThisMonth(): int
    {
        return EmiPayment::query()
            ->where('is_reversed', false)
            ->whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    public function openModal(): void
    {
        $this->reset(['loan_id', 'amount', 'reference', 'notes', 'penalty_amount']);
        $this->payment_date = now()->format('Y-m-d');
        $this->payment_method = 'cash';
        $this->showModal = true;
    }

    public function updatedLoanId(): void
    {
        $loan = Loan::query()->find($this->loan_id);
        $next = $loan?->schedules()->unsettled()->orderBy('installment_no')->first();

        $this->amount = $next ? Money::sub($next->total_amount, $next->paid_amount) : '';
        $this->penalty_amount = $next ? app(LoanPenaltyService::class)->penaltyFor($next) : '';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'loan_id' => ['required', Rule::exists('loans', 'id')->where('status', 'active')],
            'amount' => ['required', 'numeric', 'min:1', 'max:100000000'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => [Rule::requiredIf($this->payment_method !== 'cash'), 'nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ]);

        $loan = Loan::query()->findOrFail($validated['loan_id']);

        try {
            app(EmiCollectionService::class)->collect($loan, [
                ...$validated,
                'penalty_amount' => $validated['penalty_amount'] ?: 0,
            ], auth()->user());
        } catch (\InvalidArgumentException $exception) {
            $this->addError('amount', $exception->getMessage());

            return;
        }

        $this->showModal = false;
        Flux::toast(variant: 'success', text: __('EMI payment recorded.'));
        $this->resetPage();
    }

    public function openReverseModal(int $paymentId): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $this->reversePaymentId = $paymentId;
        $this->reversal_reason = '';
        $this->showReverseModal = true;
    }

    public function reverse(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $validated = $this->validate([
            'reversePaymentId' => ['required', 'exists:emi_payments,id'],
            'reversal_reason' => ['required', 'string', 'max:1000'],
        ]);

        $payment = EmiPayment::query()->findOrFail($validated['reversePaymentId']);

        try {
            app(EmiCollectionService::class)->reversePayment($payment, auth()->user(), $validated['reversal_reason']);
        } catch (\InvalidArgumentException $exception) {
            $this->addError('reversal_reason', $exception->getMessage());

            return;
        }

        $this->showReverseModal = false;
        unset($this->payments, $this->monthTotal, $this->receiptsThisMonth);
        Flux::toast(variant: 'success', text: __('Receipt reversed with compensating ledger entries.'));
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Receivables') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('EMI Collections') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Record repayments, issue receipts and track daily collections.') }}</p>
        </div>

        <button type="button" wire:click="openModal" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
            <flux:icon.plus class="size-4" />
            {{ __('Record collection') }}
        </button>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="__('Collected this month')" :value="\App\Support\Money::format($this->monthTotal)" :hint="now()->format('F Y')" icon="arrow-down-tray" tone="emerald" />
        <x-stat-card :label="__('Receipts this month')" :value="(string) $this->receiptsThisMonth" :hint="__('Payments posted')" icon="receipt-percent" tone="blue" />
        <x-stat-card :label="__('Active loans')" :value="(string) $this->activeLoans->count()" :hint="__('Eligible for collection')" icon="banknotes" tone="navy" />
    </div>

    <div class="fc-card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-sm">
                <flux:icon.magnifying-glass class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search receipt, loan, borrower…') }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pe-3 ps-9 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>

            <input type="month" wire:model.live="month" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none">
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="fc-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('Receipt') }}</th>
                        <th class="px-5 py-3">{{ __('Date') }}</th>
                        <th class="px-5 py-3">{{ __('Borrower') }}</th>
                        <th class="px-5 py-3">{{ __('Loan') }}</th>
                        <th class="px-5 py-3">{{ __('Mode') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Amount') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Principal') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Interest') }}</th>
                        <th class="px-5 py-3">{{ __('Distribution') }}</th>
                        <th class="px-5 py-3">{{ __('Collected by') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->payments as $payment)
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="px-5 py-3 font-medium text-slate-800">{{ $payment->receipt_no }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $payment->payment_date->format(\App\Support\Regional::dateFormat()) }}</td>
                            <td class="px-5 py-3">
                                <a href="{{ route('borrowers.show', $payment->loan->borrower) }}" class="font-medium text-slate-800 hover:text-blue-600">{{ $payment->loan->borrower->full_name }}</a>
                                <p class="text-[11px] text-slate-400">{{ $payment->loan->borrower->mobile }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <a href="{{ route('loans.show', $payment->loan) }}" class="font-medium text-blue-600 hover:text-blue-700">{{ $payment->loan->loan_code }}</a>
                            </td>
                            <td class="px-5 py-3 text-slate-600">{{ $payment->payment_method->label() }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($payment->amount) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-slate-600">{{ \App\Support\Money::format($payment->principal_component) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-slate-600">{{ \App\Support\Money::format($payment->interest_component) }}</td>
                            <td class="px-5 py-3">
                                @if ($payment->is_reversed)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/20">{{ __('Reversed') }}</span>
                                @elseif ($payment->distributions->isNotEmpty())
                                    <a href="{{ route('loans.distribution', $payment->loan) }}" class="text-[11px] font-semibold text-emerald-700 hover:text-emerald-800">
                                        {{ __(':count entries', ['count' => $payment->distributions->count()]) }}
                                    </a>
                                @else
                                    <span class="text-[11px] text-slate-400">{{ __('No interest') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500">{{ $payment->collectedBy?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end">
                                @if ($payment->is_reversed)
                                    <span class="text-[11px] text-slate-400" title="{{ $payment->reversal_reason }}">{{ __('Reversed') }}</span>
                                @elseif (auth()->user()->isSuperAdmin())
                                    <button type="button" wire:click="openReverseModal({{ $payment->id }})" class="text-[11px] font-semibold text-rose-600 transition hover:text-rose-700">{{ __('Reverse') }}</button>
                                @else
                                    <span class="text-[11px] text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-5 py-14 text-center"><p class="text-sm font-medium text-slate-600">{{ __('No collections found') }}</p><p class="mt-1 text-xs text-slate-400">{{ __('Record an EMI payment to see it here.') }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->payments->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $this->payments->links() }}</div>
        @endif
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>

            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold">{{ __('Record collection') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">{{ __('Post an EMI receipt against an active loan.') }}</p>
                    </div>
                    <button type="button" wire:click="$set('showModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><flux:icon.x-mark class="size-4" /></button>
                </div>

                <form wire:submit="save" class="mt-4 space-y-3">
                    <flux:select wire:model.live="loan_id" :label="__('Loan')">
                        <option value="">{{ __('Select an active loan') }}</option>
                        @foreach ($this->activeLoans as $loan)
                            <option value="{{ $loan->id }}">{{ $loan->loan_code }} · {{ $loan->borrower->full_name }} · {{ \App\Support\Money::format($loan->outstanding_principal) }} {{ __('outstanding') }}</option>
                        @endforeach
                    </flux:select>
                    @error('loan_id') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <flux:input wire:model="amount" :label="__('Amount received (₹)')" type="number" step="0.01" min="1" />
                    @error('amount') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    @if ($penalty_amount !== '' && (float) $penalty_amount > 0)
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5">
                            <flux:input wire:model="penalty_amount" :label="__('Late payment penalty (₹)')" type="number" step="0.01" min="0" />
                            <p class="mt-1.5 text-[11px] text-amber-800">{{ __('Suggested from the configured penalty rules for the overdue instalment. Adjust or clear it if the penalty is waived.') }}</p>
                            @error('penalty_amount') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        <flux:input wire:model="payment_date" :label="__('Payment date')" type="date" required max="{{ now()->format('Y-m-d') }}" />
                        <flux:select wire:model="payment_method" :label="__('Mode')" required>
                            @foreach (\App\Enums\PaymentMethod::options() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                    @error('payment_date') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    @error('payment_method') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <flux:input wire:model="reference" :label="__('Reference')" placeholder="Required for non-cash payments" maxlength="255" />
                    @error('reference') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" maxlength="1000" />

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Post receipt') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showReverseModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showReverseModal', false)"></div>

            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold">{{ __('Reverse receipt') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">{{ __('The payment, schedule balances and distribution entries are reversed with compensating records. Nothing is deleted.') }}</p>
                    </div>
                    <button type="button" wire:click="$set('showReverseModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><flux:icon.x-mark class="size-4" /></button>
                </div>

                <form wire:submit="reverse" class="mt-4 space-y-3">
                    <flux:textarea wire:model="reversal_reason" :label="__('Reason for reversal')" rows="3" maxlength="1000" required />
                    @error('reversal_reason') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    @error('reversePaymentId') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showReverseModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="danger" class="!rounded-xl">{{ __('Reverse receipt') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
