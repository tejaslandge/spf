<?php

use App\Enums\ApplicationStatus;
use App\Models\Loan;
use App\Services\LoanFundingService;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Capital allocation')] class extends Component
{
    public Loan $loan;

    /**
     * @var array<int, string>
     */
    public array $allocations = [];

    public function mount(Loan $loan): void
    {
        $this->loan = $loan->refresh()->load(['borrower', 'allocations.investor']);
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function report(): array
    {
        return app(LoanFundingService::class)->capitalReport($this->loan);
    }

    #[Computed]
    public function enteredTotal(): float
    {
        return collect($this->allocations)
            ->filter(fn ($amount): bool => $amount !== null && $amount !== '')
            ->sum(fn ($amount): float => (float) $amount);
    }

    public function allocate(): void
    {
        $this->resetErrorBag();

        try {
            app(LoanFundingService::class)->allocateCapital($this->loan, $this->allocations, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            $this->addError('allocations_general', $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Capital reserved. Funding confirmed at 100% — final approval is next.'));
        $this->redirectRoute('loans.disbursement', $this->loan, navigate: true);
    }
}
?>

<div>

    @php
        $funded = $this->loan->funding_locked_at !== null;
        $blocked = $loan->application_status !== \App\Enums\ApplicationStatus::Accepted
            || $loan->capital_verified_at === null
            || $loan->status !== \App\Enums\LoanStatus::Pending
            || $funded;
    @endphp

    <div class="mx-auto max-w-[1400px] space-y-5">
        <div>
            <p class="fc-eyebrow">{{ __('Loan processing') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Capital allocation') }}</h2>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <a href="{{ route('loans.show', $loan) }}" class="font-semibold text-navy-900 hover:underline">{{ $loan->loan_code }}</a>
                {{ __('·') }} {{ $loan->borrower->full_name }}
                {{ __('· Approved amount') }} {{ Money::format($loan->principal_amount) }}
            </p>
        </div>

        <div class="fc-card flex items-start gap-3 p-5">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-navy-900/5">
                <flux:icon.puzzle-piece class="size-5 text-navy-900" />
            </span>
            <div class="min-w-0 text-xs leading-relaxed text-slate-500">
                <p class="font-semibold text-slate-800">{{ __('Validation rules') }}</p>
                <p class="mt-1">{{ __('No investor may contribute more than their available capital, the total must exactly match the approved amount, and capital is reserved against this loan only — never double-allocated. Reaching 100% locks funding automatically.') }}</p>
            </div>
        </div>

        @if ($funded)
            <div class="fc-card flex flex-wrap items-center gap-3 border-emerald-200 bg-emerald-50/60 p-5">
                <flux:icon.lock-closed class="size-5 shrink-0 text-emerald-600" />
                <p class="min-w-0 flex-1 text-xs leading-relaxed text-emerald-900">
                    {{ __('Fully funded on :date — funding is locked and allocation records are in place. Continue to final approval and disbursement.', ['date' => $this->loan->funding_locked_at->format('d M Y, H:i')]) }}
                </p>
                <a href="{{ route('loans.disbursement', $loan) }}" wire:navigate class="rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white transition hover:bg-navy-800">{{ __('Go to disbursement') }}</a>
            </div>

            <div class="fc-card p-5 sm:p-6">
                <h3 class="text-sm font-semibold">{{ __('Allocation records') }}</h3>
                <ul class="mt-3 space-y-2">
                    @foreach ($loan->allocations as $allocation)
                        <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 p-3" wire:key="alloc-{{ $allocation->id }}">
                            <div>
                                <p class="text-xs font-semibold text-slate-800">{{ $allocation->investor->name }}</p>
                                <p class="text-[11px] text-slate-400">{{ $allocation->investor->investor_code }} · {{ $allocation->allocation_date?->format('d M Y') }}</p>
                            </div>
                            <span class="text-xs font-semibold tabular-nums">{{ Money::format($allocation->principal_amount) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @elseif ($blocked)
            <div class="fc-card flex items-center gap-3 border-amber-200 bg-amber-50/60 p-5">
                <flux:icon.exclamation-triangle class="size-5 shrink-0 text-amber-600" />
                <p class="text-xs leading-relaxed text-amber-900">
                    @if ($loan->application_status !== \App\Enums\ApplicationStatus::Accepted)
                        {{ __('The borrower must accept the loan offer first.') }}
                    @elseif ($loan->capital_verified_at === null)
                        {{ __('Run investor capital verification before allocating capital.') }}
                    @else
                        {{ __('Capital can only be allocated while the loan awaits disbursement.') }}
                    @endif
                </p>
            </div>
        @else
            <div class="grid gap-4 xl:grid-cols-3">
                <div class="fc-card p-5 sm:p-6 xl:col-span-2">
                    <h3 class="text-sm font-semibold">{{ __('Investor contributions') }}</h3>
                    <p class="mt-0.5 text-[11px] text-slate-400">{{ __('Enter each investor\'s contribution. Leave an investor blank to exclude them.') }}</p>

                    <form wire:submit="allocate" class="mt-4 space-y-3">
                        @foreach ($this->report['investors'] as $investor)
                            <div class="flex flex-wrap items-end justify-between gap-3 rounded-xl border border-slate-200 p-3" wire:key="line-{{ $investor['id'] }}">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-slate-800">{{ $investor['name'] }}</p>
                                    <p class="text-[11px] text-slate-400">{{ __('Available: :amount', ['amount' => Money::format($investor['available'])]) }}</p>
                                </div>
                                <div class="w-40">
                                    <flux:input wire:model="allocations.{{ $investor['id'] }}" label="" type="number" step="0.01" min="0" placeholder="0.00" />
                                </div>
                            </div>
                        @endforeach

                        @error('allocations_general') <p class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-xs font-medium text-rose-700">{{ $message }}</p> @enderror

                        <div class="flex items-center justify-end gap-2 pt-1">
                            <a href="{{ route('loans.show', $loan) }}" wire:navigate class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</a>
                            <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Reserve capital & confirm funding') }}</flux:button>
                        </div>
                    </form>
                </div>

                <div class="fc-card h-fit p-5 lg:sticky lg:top-20">
                    <h3 class="text-sm font-semibold">{{ __('Requirement check') }}</h3>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-xs text-slate-400">{{ __('Approved amount') }}</dt><dd class="text-xs font-semibold tabular-nums">{{ Money::format($this->report['required']) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-xs text-slate-400">{{ __('Total available capital') }}</dt><dd class="text-xs font-semibold tabular-nums">{{ Money::format($this->report['total_available']) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-xs text-slate-400">{{ __('Entered so far') }}</dt><dd class="text-xs font-bold tabular-nums {{ $this->enteredTotal > (float) $this->report['required'] ? 'text-rose-600' : '' }}">{{ Money::format((string) $this->enteredTotal) }}</dd></div>
                    </dl>

                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full transition-all duration-500 {{ $this->enteredTotal > (float) $this->report['required'] ? 'bg-rose-500' : ($this->enteredTotal == (float) $this->report['required'] ? 'bg-emerald-500' : 'bg-blue-500') }}" style="width: {{ min(100.0, $this->report['required'] > 0 ? ($this->enteredTotal / (float) $this->report['required']) * 100 : 0) }}%"></div>
                    </div>

                    @if ($this->enteredTotal == (float) $this->report['required'] && $this->enteredTotal > 0)
                        <p class="mt-2 text-[11px] font-semibold text-emerald-700">{{ __('Exact match — the loan will be fully funded on submit.') }}</p>
                    @elseif ($this->enteredTotal > (float) $this->report['required'])
                        <p class="mt-2 text-[11px] font-semibold text-rose-600">{{ __('Entered total exceeds the approved amount and will be rejected.') }}</p>
                    @else
                        <p class="mt-2 text-[11px] text-slate-400">{{ __('Shortage still needed: :amount', ['amount' => Money::format(Money::sub((string) $this->report['required'], (string) $this->enteredTotal))]) }}</p>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

