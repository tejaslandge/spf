<?php

use App\Models\Loan;
use App\Services\LoanFundingService;
use App\Services\LoanService;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Disbursement')] class extends Component
{
    public Loan $loan;

    public string $disbursement_date = '';

    public string $disbursement_reference = '';

    public function mount(Loan $loan): void
    {
        $this->loan = $loan->refresh()->load(['borrower', 'allocations.investor']);
        $this->disbursement_date = now()->format('Y-m-d');
    }

    /**
     * @return array{required: string, committed: string, remaining: string, percent: float, investors: int, status: mixed}
     */
    #[Computed]
    public function funding(): array
    {
        return app(LoanFundingService::class)->progress($this->loan);
    }

    #[Computed]
    public function capitalVerified(): bool
    {
        return $this->loan->capital_verified_at !== null;
    }

    public function approve(): void
    {
        try {
            app(LoanService::class)->approveForDisbursement($this->loan, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Disbursement authorised. You can record the payment now.'));
        $this->redirectRoute('loans.disbursement', $this->loan, navigate: true);
    }

    public function disburse(): void
    {
        if (blank($this->disbursement_date)) {
            $this->disbursement_date = now()->format('Y-m-d');
        }

        if (blank($this->disbursement_reference)) {
            $this->disbursement_reference = 'MANUAL-'.$this->loan->loan_code;
        }

        $this->validate([
            'disbursement_date' => ['required', 'date', 'before_or_equal:today'],
            'disbursement_reference' => ['required', 'string', 'max:120'],
        ]);

        try {
            $loan = app(LoanService::class)->disburse($this->loan, auth()->user(), \Carbon\CarbonImmutable::parse($this->disbursement_date), $this->disbursement_reference);
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Loan disbursed and activated. EMI collection begins.'));
        $this->redirectRoute('loans.show', $loan, navigate: true);
    }
}
?>

<div>

    <div class="mx-auto max-w-[1400px] space-y-5">
        <div>
            <p class="fc-eyebrow">{{ __('Loan processing') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Funding confirmation & disbursement') }}</h2>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <a href="{{ route('loans.show', $loan) }}" class="font-semibold text-navy-900 hover:underline">{{ $loan->loan_code }}</a>
                {{ __('·') }} {{ $loan->borrower->full_name }}
                {{ __('· Approved amount') }} {{ Money::format($loan->principal_amount) }}
            </p>
        </div>

        @if ($loan->status !== \App\Enums\LoanStatus::Pending)
            @php $doneDisbursed = $loan->disbursement_date !== null; @endphp
            @if ($doneDisbursed)
            <div class="fc-card flex flex-wrap items-center gap-3 border-emerald-200 bg-emerald-50/60 p-5">
                <flux:icon.check-circle class="size-5 shrink-0 text-emerald-600" />
                <p class="min-w-0 flex-1 text-xs leading-relaxed text-emerald-900">
                    {{ __('Disbursed on :date. Payment reference: :ref.', ['date' => $loan->disbursement_date->format('d M Y'), 'ref' => $loan->disbursement_reference ?: '—']) }}
                </p>
            </div>
        @else
            <div class="fc-card flex flex-wrap items-center gap-3 border-blue-200 bg-blue-50/60 p-5">
                <flux:icon.information-circle class="size-5 shrink-0 text-blue-600" />
                <p class="min-w-0 flex-1 text-xs leading-relaxed text-blue-900">
                    {{ __('This loan is no longer in the disbursement stage.') }}
                </p>
            </div>
        @endif
        <div class="flex justify-end">
            <a href="{{ route('loans.show', $loan) }}" wire:navigate class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Back to loan') }}</a>
        </div>
        @endif
        @if ($loan->status === \App\Enums\LoanStatus::Pending)
            <div class="grid gap-4 xl:grid-cols-3">
                <div class="space-y-4 xl:col-span-2">
                    <div class="fc-card p-5 sm:p-6">
                        <h3 class="text-sm font-semibold">{{ __('Funding summary') }}</h3>
                        <p class="mt-0.5 text-[11px] text-slate-400">{{ __('Confirmed from locked investor allocations — the disbursement can only proceed at 100%.') }}</p>

                        <div class="mt-4 rounded-xl border border-slate-200 p-4">
                            <div class="flex items-center justify-between text-[11px] text-slate-400">
                                <span>{{ __(':committed of :required reserved', ['committed' => Money::format($this->funding['committed']), 'required' => Money::format($this->funding['required'])]) }}</span>
                                <span>{{ rtrim(rtrim(number_format($this->funding['percent'], 1), '0'), '.') }}%</span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-emerald-500" style="width: {{ $this->funding['percent'] }}%"></div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-slate-500">
                                <span>{{ __('Capital verified: :result', ['result' => $this->capitalVerified ? ($loan->capital_verified_at->format('d M Y, H:i')) : 'no']) }}</span>
                                <span>{{ __('Final approval: :result', ['result' => $loan->final_approved_at !== null ? $loan->final_approved_at->format('d M Y, H:i') : 'pending']) }}</span>
                            </div>
                        </div>

                        @if ($loan->funding_status !== \App\Enums\LoanFundingStatus::FullyFunded)
                            <div class="mt-4 flex items-start gap-2 rounded-xl border border-amber-100 bg-amber-50 px-3 py-2.5 text-[11px] leading-relaxed text-amber-800">
                                <flux:icon.exclamation-triangle class="mt-0.5 size-4 shrink-0" />
                                <span>
                                    {{ __('Pending Capital — this loan is only :percent% funded. Disbursement is blocked until investor capital allocation reaches 100%.', ['percent' => rtrim(rtrim(number_format($this->funding['percent'], 1), '0'), '.')]) }}
                                    <a href="{{ route('loans.capital-allocation', $loan) }}" wire:navigate class="font-semibold underline">{{ __('Allocate capital') }}</a>
                                </span>
                            </div>
                        @endif

                        @if ($loan->allocations->isNotEmpty())
                            <ul class="mt-4 space-y-2">
                                @foreach ($loan->allocations as $allocation)
                                    <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 p-3" wire:key="disb-alloc-{{ $allocation->id }}">
                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-semibold text-slate-800">{{ $allocation->investor->name }}</p>
                                            <p class="text-[11px] text-slate-400">{{ $allocation->investor->investor_code }} · {{ $allocation->allocation_date?->format('d M Y') }}</p>
                                        </div>
                                        <span class="text-xs font-semibold tabular-nums">{{ Money::format($allocation->principal_amount) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="fc-card p-5 sm:p-6">
                        <h3 class="text-sm font-semibold">{{ __('Borrower & repayment details') }}</h3>
                        <dl class="mt-3 grid gap-x-8 gap-y-2.5 text-xs sm:grid-cols-2">
                            <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Borrower') }}</dt><dd class="font-semibold">{{ $loan->borrower->full_name }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Mobile') }}</dt><dd class="font-semibold tabular-nums">{{ $loan->borrower->mobile }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Bank') }}</dt><dd class="font-semibold">{{ $loan->borrower->bank_name ?: '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Account') }}</dt><dd class="font-semibold tabular-nums">{{ $loan->borrower->bank_account_number ?: '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('IFSC') }}</dt><dd class="font-semibold">{{ $loan->borrower->bank_ifsc ?: '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('EMI') }}</dt><dd class="font-semibold tabular-nums">{{ Money::format($loan->emi_amount) }} × {{ $loan->tenure_months }}</dd></div>
                        </dl>
                        @if ($loan->borrower->bank_account_number === null)
                            <p class="mt-3 inline-flex items-center gap-2 rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-[11px] font-medium text-amber-800">
                                <flux:icon.exclamation-triangle class="size-4" />
                                {{ __('Borrower bank details are missing — record them on the borrower profile before disbursement.') }}
                            </p>
                        @endif
                    </div>

                    @if ($loan->final_approved_at !== null && $loan->status === \App\Enums\LoanStatus::Pending)
                        <div class="fc-card border-emerald-200 bg-emerald-50/40 p-5 sm:p-6">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold">{{ __('Make Active & Mark Disbursed') }}</h3>
                                    <p class="mt-0.5 text-[11px] text-slate-500">{{ __('All checks passed. Record the payment to activate this loan.') }}</p>
                                </div>
                            </div>

                            <form wire:submit="disburse" class="mt-4 grid gap-3 sm:grid-cols-2">
                                <flux:input wire:model="disbursement_date" :label="__('Disbursement date')" type="date" max="today" required />
                                @error('disbursement_date') <p class="text-xs text-rose-600 sm:col-span-2">{{ $message }}</p> @enderror

                                <flux:input wire:model="disbursement_reference" :label="__('Payment reference (UTR / cheque / wire)')" maxlength="120" required />
                                @error('disbursement_reference') <p class="text-xs text-rose-600 sm:col-span-2">{{ $message }}</p> @enderror

                                <div class="flex flex-wrap items-center gap-2 pt-1 sm:col-span-2">
                                    <flux:button type="submit" variant="primary" class="!rounded-xl bg-emerald-700">{{ __('Make Active & Mark Disbursed') }}</flux:button>
                                    <span class="text-[11px] text-slate-500">{{ __('This will make the loan Active and start EMI collection.') }}</span>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>

                <div class="fc-card h-fit p-5 lg:sticky lg:top-20">
                    <h3 class="text-sm font-semibold">{{ __('Final approval checklist') }}</h3>
                    <p class="mt-0.5 text-[11px] text-slate-400">{{ __('Verify everything below before authorising the payment.') }}</p>

                    <ul class="mt-4 space-y-2.5 text-xs">
                        @foreach ([
                            __('Borrower KYC and details verified') => true,
                            __('Investor capital verification complete') => $this->capitalVerified,
                            __('Loan fully funded (100% reserved)') => $loan->funding_status === \App\Enums\LoanFundingStatus::FullyFunded,
                            __('Loan agreement completed') => $loan->application_status === \App\Enums\ApplicationStatus::Accepted,
                            __('Borrower bank account confirmed') => $loan->borrower->bank_account_number !== null,
                            __('Final approval recorded') => $loan->final_approved_at !== null,
                            __('Disbursement recorded — loan Active') => $loan->status === \App\Enums\LoanStatus::Active,
                        ] as $label => $passed)
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full {{ $passed ? 'bg-emerald-500 text-white' : 'border border-slate-300 bg-white' }}">
                                    @if ($passed)<flux:icon.check class="size-2.5" />@endif
                                </span>
                                <span class="{{ $passed ? 'text-slate-700' : 'text-slate-500' }}">{{ $label }}</span>
                            </li>
                        @endforeach
                    </ul>

                    @if ($loan->final_approved_at === null)
                        <button type="button" wire:click="approve" wire:confirm="{{ __('Authorise disbursement for this loan? Verify borrower details, the agreement and the bank account first.') }}" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                            <flux:icon.shield-check class="size-4" />
                            {{ __('Approve for disbursement') }}
                        </button>
                        <p class="mt-2 text-center text-[10px] text-slate-400">{{ __('Funding is 100% reserved — approval unlocks the payment recording.') }}</p>
                    @else
                        <p class="mt-5 flex items-center gap-2 rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2.5 text-[11px] font-medium leading-relaxed text-emerald-800">
                            <flux:icon.check-circle class="size-4 shrink-0" />
                            {{ __('Authorised by :name on :date. Record the payment opposite.', ['name' => $loan->finalApprovedBy?->name ?? '—', 'date' => $loan->final_approved_at->format('d M Y, H:i')]) }}
                        </p>
                        <p class="mt-2 text-center text-[10px] text-slate-400">{{ __('Use the payment form on the left to make the loan Active.') }}</p>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

