<?php

use App\Enums\ApplicationStatus;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanOffer;
use App\Services\LoanOfferService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Loan calculator')] class extends Component
{
    public Loan $loan;

    public string $principal_amount = '';

    public string $monthly_interest_rate = '4';

    public string $annual_interest_rate = '48';

    public string $interest_method = 'reducing';

    public string $tenure_months = '12';

    public string $processing_fee = '0';

    public string $other_charges = '0';

    public string $offer_notes = '';

    public bool $showRejectModal = false;

    public string $reject_reason = '';

    public function mount(Loan $loan): void
    {
        $this->loan = $loan->refresh()->load(['offers.acceptedBy', 'schedules', 'payments', 'borrower']);
        $this->principal_amount = (string) $loan->principal_amount;
        $this->annual_interest_rate = rtrim(rtrim((string) $loan->annual_interest_rate, '0'), '.');
        $this->monthly_interest_rate = rtrim(rtrim(number_format((float) $loan->annual_interest_rate / 12, 4), '0'), '.');
        $this->interest_method = $loan->interest_method->value;
        $this->tenure_months = (string) $loan->tenure_months;
    }

    /**
     * @return array{flat: \App\Support\LoanCalculation, reducing: \App\Support\LoanCalculation}|null
     */
    #[Computed]
    public function comparison(): ?array
    {
        try {
            return app(LoanOfferService::class)->compare(
                $this->principal_amount ?: 0,
                $this->annual_interest_rate ?: 0,
                max(1, (int) $this->tenure_months),
                $this->loan->first_emi_date,
            );
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, LoanOffer>
     */
    #[Computed]
    public function offers()
    {
        return $this->loan->offers()->get();
    }

    #[Computed]
    public function acceptedOffer(): ?LoanOffer
    {
        return $this->offers->first(fn (LoanOffer $offer) => $offer->accepted_at !== null);
    }

    public function generateOffer(): void
    {
        try {
            $validated = $this->validate([
                'principal_amount' => ['required', 'numeric', 'min:1000', 'max:100000000'],
                'monthly_interest_rate' => ['required', 'numeric', 'min:0', 'max:8.5'],
                'annual_interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'interest_method' => ['required', Rule::in(['flat', 'reducing'])],
                'tenure_months' => ['required', 'integer', 'min:1', 'max:120'],
                'processing_fee' => ['required', 'numeric', 'min:0', 'max:10000000'],
                'other_charges' => ['required', 'numeric', 'min:0', 'max:10000000'],
                'offer_notes' => ['nullable', 'string', 'max:1000'],
            ]);
        } catch (\Illuminate\Validation\ValidationException) {
            return;
        }

        try {
            app(LoanOfferService::class)->generate($this->loan, $validated, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Offer generated and ready for borrower acceptance.'));
        $this->redirectRoute('loans.calculator', $this->loan, navigate: true);
    }

    public function confirmAcceptance(int $offerId): void
    {
        $offer = $this->loan->offers()->findOrFail($offerId);

        try {
            $loan = app(LoanOfferService::class)->accept($offer, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Acceptance recorded. Terms are locked and capital verification is next.'));
        $this->redirectRoute('loans.capital-verification', $loan, navigate: true);
    }

    public function updatedMonthlyInterestRate(): void
    {
        if ($this->monthly_interest_rate === '' || ! is_numeric($this->monthly_interest_rate)) {
            return;
        }

        $this->annual_interest_rate = rtrim(rtrim(number_format((float) $this->monthly_interest_rate * 12, 4), '0'), '.');
    }

    public function openRejectModal(): void
    {
        $this->reset('reject_reason');
        $this->showRejectModal = true;
    }

    public function rejectApplication(): void
    {
        $this->validate(['reject_reason' => ['required', 'string', 'max:500']]);

        try {
            $loan = app(LoanOfferService::class)->rejectApplication($this->loan, $this->reject_reason, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->showRejectModal = false;
        Flux::toast(variant: 'success', text: __('Application rejected.'));

        $this->redirectRoute('loans.show', $loan, navigate: true);
    }
}
?>

<div>

    <div class="mx-auto max-w-[1400px] space-y-5">
        <div>
            <p class="fc-eyebrow">{{ __('Loan processing') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Loan calculator & offer') }}</h2>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <a href="{{ route('loans.show', $loan) }}" class="font-semibold text-navy-900 hover:underline">{{ $loan->loan_code }}</a>
                {{ __('·') }} {{ $loan->borrower->full_name }}
                <x-status-badge :status="$loan->application_status" />
            </p>
        </div>

        @if ($loan->status !== \App\Enums\LoanStatus::Pending)
            <div class="fc-card flex items-center gap-3 border-blue-200 bg-blue-50/60 p-5">
                <flux:icon.information-circle class="size-5 shrink-0 text-blue-600" />
                <p class="text-sm leading-relaxed text-blue-900">{{ __('This loan is no longer in the offer stage. Terms were locked when the offer was accepted.') }}</p>
            </div>
        @elseif ($loan->application_status === \App\Enums\ApplicationStatus::Accepted)
            <div class="fc-card flex items-center gap-3 border-emerald-200 bg-emerald-50/60 p-5">
                <flux:icon.lock-closed class="size-5 shrink-0 text-emerald-600" />
                <p class="text-sm leading-relaxed text-emerald-900">{{ __('Terms are locked as a snapshot. Future rate changes will not modify this loan.') }}</p>
            </div>
        @elseif (in_array($loan->application_status, [\App\Enums\ApplicationStatus::Rejected, \App\Enums\ApplicationStatus::Cancelled]))
            <div class="fc-card flex items-center gap-3 border-rose-200 bg-rose-50/60 p-5">
                <flux:icon.x-circle class="size-5 shrink-0 text-rose-600" />
                <p class="text-sm leading-relaxed text-rose-900">{{ __('This application was rejected. No further actions are available.') }}</p>
            </div>
        @endif

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="fc-card p-5 sm:p-6 xl:col-span-1">
                <h3 class="text-sm font-semibold">{{ __('Offer terms') }}</h3>
                <p class="mt-0.5 text-[11px] text-slate-400">{{ __('First EMI date · :date', ['date' => $loan->first_emi_date->format(\App\Support\Regional::dateFormat())]) }}</p>

                @if ($loan->status === \App\Enums\LoanStatus::Pending && ! in_array($loan->application_status, [\App\Enums\ApplicationStatus::Accepted, \App\Enums\ApplicationStatus::Rejected, \App\Enums\ApplicationStatus::Cancelled], true))
                    <form wire:submit="generateOffer" class="mt-4 space-y-3">
                        <flux:input wire:model="principal_amount" :label="__('Principal (₹)')" type="number" step="0.01" min="1000" required />
                        @error('principal_amount') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                        <flux:input wire:model.live.debounce.400ms="monthly_interest_rate" :label="__('Monthly interest rate (%)')" type="number" step="0.01" min="0" max="8.5" required />
                        @error('monthly_interest_rate') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                        <flux:input wire:model="annual_interest_rate" :label="__('Annual interest rate (%)')" type="number" step="0.01" readonly placeholder="= Monthly × 12" />
                        @error('annual_interest_rate') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                        <p class="-mt-2 text-[11px] text-slate-400">{{ __('Annual rate is auto-calculated as monthly rate × 12.') }}</p>

                        <flux:select wire:model="interest_method" :label="__('Interest method (for the offer)')" required>
                            <option value="flat">{{ __('Flat interest') }}</option>
                            <option value="reducing">{{ __('Reducing balance') }}</option>
                        </flux:select>
                        @error('interest_method') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                        <flux:input wire:model="tenure_months" :label="__('Tenure (months)')" type="number" min="1" max="120" required />
                        @error('tenure_months') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                        <div class="grid grid-cols-2 gap-3">
                            <flux:input wire:model="processing_fee" :label="__('Processing fee (₹)')" type="number" step="0.01" min="0" required />
                            <flux:input wire:model="other_charges" :label="__('Other charges (₹)')" type="number" step="0.01" min="0" required />
                        </div>

                        <flux:textarea wire:model="offer_notes" :label="__('Offer notes')" rows="2" maxlength="1000" />

                        <div class="flex items-center gap-2 pt-1">
                            <flux:button type="submit" variant="primary" class="flex-1 !rounded-xl">{{ __('Generate offer') }}</flux:button>
                            <button type="button" wire:click="openRejectModal" class="rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-50">{{ __('Reject') }}</button>
                        </div>
                    </form>
                @else
                    <dl class="mt-4 space-y-2.5 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Principal') }}</dt><dd class="font-semibold tabular-nums">{{ Money::format($loan->principal_amount) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Monthly rate') }}</dt><dd class="font-semibold tabular-nums">{{ rtrim(rtrim(number_format((float) $loan->annual_interest_rate / 12, 4), '0'), '.') }}%</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Annual rate') }}</dt><dd class="font-semibold tabular-nums">{{ rtrim(rtrim((string) $loan->annual_interest_rate, '0'), '.') }}%</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Method') }}</dt><dd class="font-semibold">{{ ucfirst($loan->interest_method->value) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Tenure') }}</dt><dd class="font-semibold">{{ $loan->tenure_months }} {{ __('months') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('EMI') }}</dt><dd class="font-semibold tabular-nums">{{ Money::format($loan->emi_amount) }}</dd></div>
                        <div class="flex justify-between gap-3 border-t border-slate-100 pt-2.5"><dt class="text-slate-400">{{ __('Total interest') }}</dt><dd class="font-semibold tabular-nums">{{ Money::format($loan->total_interest) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Total repayment') }}</dt><dd class="font-semibold tabular-nums">{{ Money::format($loan->total_payable) }}</dd></div>
                    </dl>
                @endif
            </div>

            <div class="space-y-4 xl:col-span-2">
                @if ($comparison = $this->comparison)
                    <div class="fc-card p-5 sm:p-6">
                        <h3 class="text-sm font-semibold">{{ __('Repayment options · side by side') }}</h3>
                        <p class="mt-0.5 text-[11px] text-slate-400">{{ __('Both interest methods calculated from the current terms. Fees and charges are added to the payable total.') }}</p>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach (['flat' => __('Flat interest'), 'reducing' => __('Reducing balance')] as $methodKey => $methodLabel)
                                @php $calc = $comparison[$methodKey]; $check = $this->interest_method === $methodKey; @endphp
                                <div class="rounded-2xl border p-4 {{ $check ? 'border-navy-900/30 bg-navy-900/[0.04]' : 'border-slate-200' }}">
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-semibold text-slate-700">{{ $methodLabel }}</p>
                                        @if ($check)
                                            <span class="rounded-full bg-navy-900 px-2 py-0.5 text-[9px] font-bold uppercase tracking-[0.12em] text-white">{{ __('Offer') }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-2 text-lg font-bold tabular-nums tracking-tight">{{ Money::format($calc->emiAmount) }}</p>
                                    <p class="text-[10px] uppercase tracking-[0.12em] text-slate-400">{{ __('monthly EMI') }}</p>
                                    <dl class="mt-3 space-y-1.5 text-xs">
                                        <div class="flex justify-between"><dt class="text-slate-400">{{ __('Installments') }}</dt><dd class="font-semibold tabular-nums">{{ count($calc->installments) }}</dd></div>
                                        <div class="flex justify-between"><dt class="text-slate-400">{{ __('Total interest') }}</dt><dd class="font-semibold tabular-nums">{{ Money::format($calc->totalInterest) }}</dd></div>
                                        <div class="flex justify-between"><dt class="text-slate-400">{{ __('Total repayment') }}</dt><dd class="font-semibold tabular-nums">{{ Money::format($calc->totalPayable) }}</dd></div>
                                        @if (Money::compare($this->processing_fee ?: 0, '0') > 0 || Money::compare($this->other_charges ?: 0, '0') > 0)
                                            <div class="flex justify-between border-t border-slate-100 pt-1.5"><dt class="text-slate-400">{{ __('With fees & charges') }}</dt><dd class="font-semibold tabular-nums">{{ Money::format(Money::add($calc->totalPayable, $this->processing_fee ?: 0, $this->other_charges ?: 0)) }}</dd></div>
                                        @endif
                                    </dl>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($this->offers->isNotEmpty())
                    <div class="fc-card p-5 sm:p-6">
                        <h3 class="text-sm font-semibold">{{ __('Offer history') }}</h3>
                        <p class="mt-0.5 text-[11px] text-slate-400">{{ __('Each accepted offer is stored as a snapshot; later rate changes never modify a loan already agreed.') }}</p>

                        <ul class="mt-4 space-y-3">
                            @foreach ($this->offers as $offer)
                                <li class="rounded-2xl border {{ $offer->accepted_at ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200' }} p-4" wire:key="offer-{{ $offer->id }}">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p class="flex items-center gap-2 text-sm font-semibold">
                                                {{ Money::format($offer->principal_amount) }} · {{ ucfirst($offer->interest_method->value) }} · {{ rtrim(rtrim((string) $offer->annual_interest_rate, '0'), '.') }}% · {{ $offer->tenure_months }} {{ __('months') }}
                                                @if ($offer->accepted_at)
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-600 px-2 py-0.5 text-[9px] font-bold uppercase tracking-[0.12em] text-white">{{ __('Accepted') }}</span>
                                                @endif
                                            </p>
                                            <p class="mt-1 text-[11px] text-slate-400">
                                                {{ __('EMI :emi · interest :interest · total :total · issued :date', [
                                                    'emi' => Money::format($offer->emi_amount),
                                                    'interest' => Money::format($offer->total_interest),
                                                    'total' => Money::format($offer->total_repayment),
                                                    'date' => $offer->offered_at->format(\App\Support\Regional::dateFormat().', '.\App\Support\Regional::timeFormat()),
                                                ]) }}
                                            </p>
                                            <p class="mt-0.5 text-[11px] text-slate-400">
                                                {{ __('Fees: processing :fee, other :charges', ['fee' => Money::format($offer->processing_fee), 'charges' => Money::format($offer->other_charges)]) }}
                                            </p>
                                        </div>
                                        @if (! $offer->accepted_at && $loan->status === \App\Enums\LoanStatus::Pending && $loan->application_status !== \App\Enums\ApplicationStatus::Accepted)
                                            <button type="button" wire:click="confirmAcceptance({{ $offer->id }})" wire:confirm="{{ __('Record the acceptance of this offer as signed by the borrower? The offered terms will be locked into the loan.') }}" class="rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white transition hover:bg-navy-800">
                                                {{ __('Record acceptance') }}
                                            </button>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showRejectModal', false)"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <h3 class="text-base font-semibold">{{ __('Reject application') }}</h3>
                <form wire:submit="rejectApplication" class="mt-4 space-y-3">
                    <flux:textarea wire:model="reject_reason" :label="__('Rejection reason')" rows="3" required placeholder="{{ __('Why was this application rejected?') }}" />
                    @error('reject_reason') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showRejectModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="danger" class="!rounded-xl">{{ __('Reject application') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

