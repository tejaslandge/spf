<?php

use App\Models\Investor;
use App\Models\Loan;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Investor profile')] class extends Component
{
    public Investor $investor;

    public bool $showAllocationModal = false;

    public bool $showDepositModal = false;

    public string $loan_id = '';

    public string $principal_amount = '';

    public string $allocation_date = '';

    public string $notes = '';

    public string $deposit_amount = '';

    public string $deposit_status = 'cleared';

    public string $deposit_date = '';

    public string $deposit_reference = '';

    public string $deposit_notes = '';

    public bool $showRateModal = false;

    public string $rate_monthly = '';

    public string $rate_effective_from = '';

    public string $rate_effective_to = '';

    public string $rate_notes = '';

    public bool $rate_active = true;

    public function mount(Investor $investor): void
    {
        $this->investor = $investor->loadCount('allocations');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvestorDeposit>
     */
    #[Computed]
    public function deposits()
    {
        return $this->investor->deposits()->latest('deposited_at')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvestorAllocation>
     */
    #[Computed]
    public function allocations()
    {
        return $this->investor->allocations()->with('loan.borrower')->latest('allocation_date')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvestorPayout>
     */
    #[Computed]
    public function payouts()
    {
        return $this->investor->payouts()->latest('period_month')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Loan>
     */
    #[Computed]
    public function activeLoans()
    {
        return Loan::query()->with('borrower')->where('status', 'active')->orderBy('loan_code')->get();
    }

    #[Computed]
    public function totals(): array
    {
        $allocations = $this->investor->allocations();

        $deposited = (string) $this->investor->deposits()->where('status', 'cleared')->sum('amount');
        $available = \App\Support\Money::sub($deposited, (string) $allocations->where('status', 'active')->sum('principal_amount'));
        $available = \App\Support\Money::compare($available, '0') > 0 ? $available : '0.00';

        return [
            'allocated' => (string) $allocations->where('status', 'active')->sum('principal_amount'),
            'deposited' => $deposited,
            'available' => $available,
            'payout_paid' => (string) $this->investor->payouts()->where('status', 'paid')->sum('net_amount'),
            'payout_pending' => (string) $this->investor->payouts()->where('status', 'pending')->sum('net_amount'),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvestorInterestRate>
     */
    #[Computed]
    public function interestRates()
    {
        return $this->investor->interestRates()->get();
    }

    #[Computed]
    public function activeRate(): ?\App\Models\InvestorInterestRate
    {
        return \App\Models\InvestorInterestRate::activeFor($this->investor);
    }

    public function openRateModal(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $this->reset(['rate_monthly', 'rate_effective_to', 'rate_notes']);
        $this->rate_monthly = (string) ($this->activeRate?->monthly_rate ?? \App\Models\InterestConfiguration::activeFor()?->default_investor_monthly_rate ?? '1.5');
        $this->rate_effective_from = now()->format('Y-m-d');
        $this->rate_active = true;
        $this->showRateModal = true;
    }

    public function saveRate(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $validated = $this->validate([
            'rate_monthly' => ['required', 'numeric', 'min:0', 'max:100'],
            'rate_effective_from' => ['required', 'date'],
            'rate_effective_to' => ['nullable', 'date', 'after_or_equal:rate_effective_from'],
            'rate_notes' => ['nullable', 'string', 'max:2000'],
            'rate_active' => ['boolean'],
        ]);

        $borrowerRate = (string) (\App\Models\InterestConfiguration::activeFor($validated['rate_effective_from'])?->borrower_monthly_rate ?? 0);

        if (\App\Support\Money::compare($borrowerRate, '0') > 0 && \App\Support\Money::compare($validated['rate_monthly'], $borrowerRate) > 0) {
            $this->addError('rate_monthly', __('An investor monthly rate cannot exceed the borrower monthly rate of :rate%.', ['rate' => rtrim(rtrim($borrowerRate, '0'), '.')]));

            return;
        }

        \App\Models\InvestorInterestRate::query()->updateOrCreate(
            [
                'investor_id' => $this->investor->id,
                'effective_from' => $validated['rate_effective_from'],
            ],
            [
                'monthly_rate' => $validated['rate_monthly'],
                'effective_to' => filled($validated['rate_effective_to']) ? $validated['rate_effective_to'] : null,
                'is_active' => $validated['rate_active'],
                'notes' => filled($validated['rate_notes']) ? $validated['rate_notes'] : null,
                'created_by' => auth()->id(),
            ],
        );

        $this->showRateModal = false;
        unset($this->interestRates, $this->activeRate);
        Flux::toast(variant: 'success', text: __('Investor interest rate saved.'));
    }

    public function deleteRate(int $rateId): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $this->investor->interestRates()->findOrFail($rateId)->delete();

        unset($this->interestRates, $this->activeRate);
        Flux::toast(variant: 'success', text: __('Investor interest rate deleted.'));
    }

    public function openDepositModal(): void
    {
        $this->reset(['deposit_amount', 'deposit_reference', 'deposit_notes']);
        $this->deposit_status = 'cleared';
        $this->deposit_date = now()->format('Y-m-d');
        $this->showDepositModal = true;
    }

    public function saveDeposit(): void
    {
        $validated = $this->validate([
            'deposit_amount' => ['required', 'numeric', 'min:1', 'max:100000000'],
            'deposit_status' => ['required', Rule::enum(\App\Enums\DepositStatus::class)],
            'deposit_date' => ['required', 'date', 'before_or_equal:today'],
            'deposit_reference' => ['nullable', 'string', 'max:120'],
            'deposit_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->investor->deposits()->create([
            'amount' => $validated['deposit_amount'],
            'status' => $validated['deposit_status'],
            'transaction_reference' => filled($validated['deposit_reference']) ? $validated['deposit_reference'] : null,
            'deposited_at' => $validated['deposit_date'],
            'cleared_at' => $validated['deposit_status'] === 'cleared' ? now() : null,
            'cleared_by' => $validated['deposit_status'] === 'cleared' ? auth()->id() : null,
            'notes' => filled($validated['deposit_notes']) ? $validated['deposit_notes'] : null,
            'created_by' => auth()->id(),
        ]);

        $this->showDepositModal = false;
        unset($this->deposits, $this->totals);
        Flux::toast(variant: 'success', text: __('Deposit recorded.'));
    }

    public function openAllocationModal(): void
    {
        $this->reset(['loan_id', 'principal_amount', 'notes']);
        $this->allocation_date = now()->format('Y-m-d');
        $this->showAllocationModal = true;
    }

    public function allocate(): void
    {
        $this->validate([
            'loan_id' => ['required', \Illuminate\Validation\Rule::exists('loans', 'id')->where('status', 'active')],
            'principal_amount' => ['required', 'numeric', 'min:1000', 'max:100000000'],
            'allocation_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $loan = Loan::query()->findOrFail($this->loan_id);

        if ($loan->status !== \App\Enums\LoanStatus::Active) {
            $this->addError('loan_id', __('Only active loans can receive investor capital.'));

            return;
        }

        $alreadyAllocated = (string) $loan->allocations()->where('status', 'active')->sum('principal_amount');
        $available = \App\Support\Money::sub($loan->principal_amount, $alreadyAllocated);

        if (\App\Support\Money::compare($this->principal_amount, $available) > 0) {
            $this->addError('principal_amount', __('Allocation exceeds the available principal (remaining: :amount).', ['amount' => \App\Support\Money::format($available)]));

            return;
        }

        DB::transaction(function () use ($loan): void {
            $this->investor->allocations()->create([
                'loan_id' => $loan->id,
                'principal_amount' => $this->principal_amount,
                'allocation_date' => $this->allocation_date,
                'status' => 'active',
                'notes' => $this->notes ?: null,
            ]);
        });

        $this->showAllocationModal = false;
        unset($this->allocations);
        Flux::toast(variant: 'success', text: __('Capital allocated successfully.'));
    }

    public function releaseAllocation(int $allocationId): void
    {
        $allocation = $this->investor->allocations()->findOrFail($allocationId);

        $allocation->update(['status' => 'released', 'released_at' => now()]);

        unset($this->allocations);
        Flux::toast(variant: 'success', text: __('Allocation released.'));
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ route('investors.index') }}" class="hover:text-blue-600">{{ __('Investors') }}</a>
        <flux:icon.chevron-right class="size-3" />
        <span class="text-slate-600">{{ $investor->investor_code }}</span>
    </div>

    <div class="fc-card p-5 sm:p-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div class="flex items-center gap-4">
                <flux:avatar :name="$investor->name" :initials="str($investor->name)->substr(0, 2)" class="size-14 text-base" />
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-semibold tracking-tight">{{ $investor->name }}</h2>
                        <x-status-badge :status="$investor->status" />
                    </div>
                    <p class="mt-1 text-xs text-slate-400">{{ $investor->investor_code }} · {{ $investor->pan_number ?: '—' }} · {{ __('Joined :date', ['date' => $investor->created_at->format('d M Y')]) }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('investors.edit', $investor) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900">
                    <flux:icon.pencil-square class="size-4" />
                    {{ __('Edit') }}
                </a>
                <button type="button" wire:click="openDepositModal" class="inline-flex items-center gap-2 rounded-xl border border-navy-900 bg-navy-900/5 px-3.5 py-2.5 text-xs font-semibold text-navy-900 transition hover:bg-navy-900/10">
                    <flux:icon.plus class="size-4" />
                    {{ __('Record deposit') }}
                </button>
                <button type="button" wire:click="openAllocationModal" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                    <flux:icon.plus class="size-4" />
                    {{ __('Allocate capital') }}
                </button>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat-card :label="__('Cleared deposits')" :value="\App\Support\Money::format($this->totals['deposited'])" :hint="__('Cleared only — pending excluded')" icon="arrow-down-tray" tone="blue" />
        <x-stat-card :label="__('Available capital')" :value="\App\Support\Money::format($this->totals['available'])" :hint="__('Cleared − active allocations')" icon="banknotes" tone="navy" />
        <x-stat-card :label="__('Allocated capital')" :value="\App\Support\Money::format($this->totals['allocated'])" :hint="__('Active allocations')" icon="building-office-2" tone="purple" />
        <x-stat-card :label="__('Paid out')" :value="\App\Support\Money::format($this->totals['payout_paid'])" :hint="__('Lifetime net payouts')" icon="currency-dollar" tone="emerald" />
        <x-stat-card :label="__('Pending payouts')" :value="\App\Support\Money::format($this->totals['payout_pending'])" :hint="__('Awaiting release')" icon="clock" tone="amber" />
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="space-y-4">
            <div class="fc-card p-5">
                <h3 class="text-sm font-semibold">{{ __('Contact') }}</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Mobile') }}</dt><dd class="font-medium text-slate-800">{{ $investor->mobile }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Email') }}</dt><dd class="font-medium text-slate-800">{{ $investor->email ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Aadhaar') }}</dt><dd class="font-medium text-slate-800">{{ $investor->aadhaar_number ?: '—' }}</dd></div>
                </dl>
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Address') }}</p>
                    <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $investor->address }}</p>
                </div>
            </div>

            <div class="fc-card p-5">
                <h3 class="text-sm font-semibold">{{ __('Bank details') }}</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Bank') }}</dt><dd class="font-medium text-slate-800">{{ $investor->bank_name ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Account') }}</dt><dd class="font-medium text-slate-800">{{ $investor->bank_account_number ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('IFSC') }}</dt><dd class="font-medium text-slate-800">{{ $investor->bank_ifsc ?: '—' }}</dd></div>
                </dl>
                @if ($investor->notes)
                    <div class="mt-4 rounded-xl bg-amber-50 p-3 text-xs leading-relaxed text-amber-800">{{ $investor->notes }}</div>
                @endif
            </div>

            <div class="fc-card p-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold">{{ __('Interest rate') }}</h3>
                    @if (auth()->user()->isSuperAdmin())
                        <button type="button" wire:click="openRateModal" class="text-[11px] font-semibold text-blue-600 hover:text-blue-700">{{ __('Configure') }}</button>
                    @endif
                </div>

                <div class="mt-3 rounded-xl bg-slate-50 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Active monthly rate') }}</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900">{{ $this->activeRate ? rtrim(rtrim((string) $this->activeRate->monthly_rate, '0'), '.') . '%' : __('Global default') }}</p>
                    <p class="mt-1 text-[11px] text-slate-400">
                        @if ($this->activeRate)
                            {{ __('Effective :date', ['date' => $this->activeRate->effective_from->format('d M Y')]) }}
                        @else
                            {{ __('No individual override — the global configuration applies.') }}
                        @endif
                    </p>
                </div>

                <ul class="mt-3 space-y-2">
                    @forelse ($this->interestRates as $rate)
                        <li class="flex items-center justify-between gap-2 rounded-xl border border-slate-100 px-3 py-2">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-slate-800">{{ rtrim(rtrim((string) $rate->monthly_rate, '0'), '.') }}% {{ __('monthly') }}</p>
                                <p class="text-[11px] text-slate-400">{{ $rate->effective_from->format('d M Y') }} → {{ $rate->effective_to?->format('d M Y') ?? __('open') }}</p>
                            </div>
                            @if (auth()->user()->isSuperAdmin())
                                <button type="button" wire:click="deleteRate({{ $rate->id }})" wire:confirm="{{ __('Delete this rate? Existing loan snapshots are unaffected.') }}" class="text-[11px] font-semibold text-rose-600 hover:text-rose-700">{{ __('Delete') }}</button>
                            @endif
                        </li>
                    @empty
                        <li class="text-center text-[11px] text-slate-400">{{ __('No individual rate history.') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="fc-card p-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold">{{ __('KYC documents') }}</h3>
                    <span class="text-[11px] text-slate-400">{{ $investor->kycDocuments()->count() }} {{ __('uploaded') }}</span>
                </div>
                <ul class="mt-3 space-y-2">
                    @forelse ($investor->kycDocuments()->latest()->get() as $document)
                        <li class="rounded-xl border border-slate-200 p-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-slate-800">{{ $document->type->label() }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-400">{{ $document->original_name }}</p>
                                </div>
                                <x-status-badge :status="$document->status" />
                            </div>
                            <a href="{{ route('documents.download', $document) }}" class="mt-1.5 inline-block text-[11px] font-semibold text-blue-600 hover:text-blue-700">{{ __('Download') }}</a>
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">{{ __('No KYC documents uploaded.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="space-y-4 xl:col-span-2">
            <div class="fc-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold">{{ __('Deposit history') }}</h3>
                    <span class="text-xs text-slate-400">{{ __(':count deposits', ['count' => $this->deposits->count()]) }}</span>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($this->deposits as $deposit)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl {{ $deposit->status === \App\Enums\DepositStatus::Cleared ? 'bg-emerald-50 text-emerald-600' : ($deposit->status === \App\Enums\DepositStatus::Pending ? 'bg-amber-50 text-amber-600' : 'bg-rose-50 text-rose-600') }}">
                                <flux:icon.banknotes class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold text-slate-800">
                                    {{ __('Deposited :date', ['date' => $deposit->deposited_at->format('d M Y')]) }}@if ($deposit->transaction_reference) · {{ $deposit->transaction_reference }}@endif
                                </p>
                                <p class="text-[11px] text-slate-400">@if ($deposit->status === \App\Enums\DepositStatus::Cleared){{ __('Cleared — counts as available capital') }}@elseif ($deposit->status === \App\Enums\DepositStatus::Pending){{ __('Awaiting clearance — not counted as available') }}@else{{ $deposit->status->label() }}@endif</p>
                            </div>
                            <div class="text-end">
                                <p class="text-xs font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($deposit->amount) }}</p>
                                <x-status-badge :status="$deposit->status" class="mt-1" />
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-xs text-slate-400">{{ __('No deposits recorded yet.') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="fc-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold">{{ __('Loan-wise exposure') }}</h3>
                    <span class="text-xs text-slate-400">{{ $this->allocations->count() }} {{ __('allocations') }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="fc-table-head">
                            <tr>
                                <th class="px-5 py-3">{{ __('Loan') }}</th>
                                <th class="px-5 py-3">{{ __('Borrower') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('Allocated') }}</th>
                                <th class="px-5 py-3">{{ __('Date') }}</th>
                                <th class="px-5 py-3">{{ __('Status') }}</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($this->allocations as $allocation)
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="px-5 py-3">
                                        <a href="{{ route('loans.show', $allocation->loan) }}" class="font-medium text-blue-600 hover:text-blue-700">{{ $allocation->loan->loan_code }}</a>
                                        <p class="text-[11px] text-slate-400">{{ $allocation->loan->interest_method->label() }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-slate-700">{{ $allocation->loan->borrower->full_name }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-end font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($allocation->principal_amount) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $allocation->allocation_date->format('d M Y') }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $allocation->status === \App\Enums\AllocationStatus::Active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20' }}">
                                            {{ $allocation->status === \App\Enums\AllocationStatus::Active ? __('Active') : __('Released') }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-end">
                                        @if ($allocation->status === \App\Enums\AllocationStatus::Active)
                                            <button type="button" wire:click="releaseAllocation({{ $allocation->id }})" class="text-[11px] font-semibold text-rose-600 hover:text-rose-700">{{ __('Release') }}</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-10 text-center text-xs text-slate-400">{{ __('No capital allocated yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="fc-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold">{{ __('Payout history') }}</h3>
                    <a href="{{ route('payouts.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">{{ __('Manage payouts') }}</a>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($this->payouts as $payout)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                                <flux:icon.currency-dollar class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold text-slate-800">{{ $payout->payout_code }} · {{ $payout->period_month->format('F Y') }}</p>
                                <p class="text-[11px] text-slate-400">{{ __('Gross :gross · Fee :fee · TDS :tds', ['gross' => \App\Support\Money::format($payout->gross_interest), 'fee' => \App\Support\Money::format($payout->management_fee), 'tds' => \App\Support\Money::format($payout->tds_amount)]) }}</p>
                            </div>
                            <div class="text-end">
                                <p class="text-xs font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($payout->net_amount) }}</p>
                                <x-status-badge :status="$payout->status" class="mt-1" />
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-xs text-slate-400">{{ __('No payouts generated yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    @if ($showDepositModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showDepositModal', false)"></div>

            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold">{{ __('Record investor deposit') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">{{ $investor->name }} · {{ __('only cleared deposits count as available capital') }}</p>
                    </div>
                    <button type="button" wire:click="$set('showDepositModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><flux:icon.x-mark class="size-4" /></button>
                </div>

                <form wire:submit="saveDeposit" class="mt-4 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <flux:input wire:model="deposit_amount" :label="__('Amount (₹)')" type="number" step="0.01" min="1" required />
                        <flux:select wire:model="deposit_status" :label="__('Status')" required>
                            @foreach (\App\Enums\DepositStatus::options() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                    @error('deposit_amount') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    @error('deposit_status') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <flux:input wire:model="deposit_date" :label=" __('Deposit date')" type="date" required max="{{ now()->format('Y-m-d') }}" />
                    @error('deposit_date') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <flux:input wire:model="deposit_reference" :label="__('Transaction reference')" placeholder="UTR / cheque / wire reference" maxlength="120" />

                    <flux:textarea wire:model="deposit_notes" :label="__('Notes')" rows="2" maxlength="1000" />

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showDepositModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Record deposit') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showAllocationModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showAllocationModal', false)"></div>

            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold">{{ __('Allocate capital') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">{{ $investor->name }} · {{ $investor->investor_code }}</p>
                    </div>
                    <button type="button" wire:click="$set('showAllocationModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><flux:icon.x-mark class="size-4" /></button>
                </div>

                <form wire:submit="allocate" class="mt-4 space-y-3">
                    <flux:select wire:model="loan_id" :label="__('Active loan')" required>
                        <option value="">{{ __('Select a loan') }}</option>
                        @foreach ($this->activeLoans as $loan)
                            <option value="{{ $loan->id }}">{{ $loan->loan_code }} · {{ $loan->borrower->full_name }}</option>
                        @endforeach
                    </flux:select>
                    @error('loan_id') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <flux:input wire:model="principal_amount" :label="__('Principal amount (₹)')" type="number" step="0.01" min="1000" max="100000000" required />
                    @error('principal_amount') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <flux:input wire:model="allocation_date" :label="__('Allocation date')" type="date" required max="{{ now()->format('Y-m-d') }}" />
                    @error('allocation_date') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" maxlength="1000" />

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showAllocationModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Allocate') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showRateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showRateModal', false)"></div>

            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold">{{ __('Investor interest rate') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">{{ $investor->name }} · {{ __('overrides the global default for new snapshots') }}</p>
                    </div>
                    <button type="button" wire:click="$set('showRateModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><flux:icon.x-mark class="size-4" /></button>
                </div>

                <form wire:submit="saveRate" class="mt-4 space-y-3">
                    <flux:input wire:model="rate_monthly" :label="__('Monthly rate (%)')" type="number" step="0.000001" min="0" max="100" required />
                    @error('rate_monthly') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-2 gap-3">
                        <flux:input wire:model="rate_effective_from" :label="__('Effective from')" type="date" required />
                        <flux:input wire:model="rate_effective_to" :label="__('Effective to')" type="date" />
                    </div>
                    @error('rate_effective_from') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    @error('rate_effective_to') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <input type="checkbox" wire:model="rate_active" class="rounded border-slate-300 text-blue-600">
                        {{ __('Active rate') }}
                    </label>

                    <flux:textarea wire:model="rate_notes" :label="__('Notes')" rows="2" maxlength="2000" />

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showRateModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Save rate') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
