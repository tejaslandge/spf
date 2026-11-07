<?php

use App\Enums\ApplicationStatus;
use App\Enums\LoanFundingStatus;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Services\LoanFundingService;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Capital verification')] class extends Component
{
    public Loan $loan;

    public function mount(Loan $loan): void
    {
        $this->loan = $loan->refresh()->load(['borrower']);
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function report(): array
    {
        return app(LoanFundingService::class)->capitalReport($this->loan);
    }

    /**
     * @return array{total_cleared: string, total_allocated: string, total_available: string}
     */
    #[Computed]
    public function pool(): array
    {
        return app(LoanFundingService::class)->poolTotals();
    }

    public function confirmVerified(): void
    {
        try {
            $loan = app(LoanFundingService::class)->markCapitalVerified($this->loan, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Capital verification recorded. Reserve capital from the available pool when ready.'));
        $this->redirectRoute('loans.capital-verification', $loan, navigate: true);
    }

    public function reservePoolCapital(): void
    {
        try {
            app(LoanFundingService::class)->allocateFromPool($this->loan, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Capital reserved from the available pool. Funding is confirmed — final approval is next.'));
        $this->redirectRoute('loans.disbursement', $this->loan, navigate: true);
    }
}
?>

<div>

    <div class="mx-auto max-w-[1400px] space-y-5">
        <div>
            <p class="fc-eyebrow">{{ __('Loan processing') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Investor capital verification') }}</h2>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                <a href="{{ route('loans.show', $loan) }}" class="font-semibold text-navy-900 hover:underline">{{ $loan->loan_code }}</a>
                {{ __('·') }} {{ $loan->borrower->full_name }}
                {{ __('· Approved amount') }} {{ Money::format($loan->principal_amount) }}
            </p>
        </div>

        <div class="fc-card flex items-start gap-3 p-5">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-navy-900/5">
                <flux:icon.banknotes class="size-5 text-navy-900" />
            </span>
            <div class="min-w-0 text-xs leading-relaxed text-slate-500">
                <p class="font-semibold text-slate-800">{{ __('Pooled capital allocation') }}</p>
                <p class="mt-1">{{ __('Available Capital Pool = Total Cleared Deposits − Total Active Allocations. Once investor funds are cleared, they become part of the common pool. Capital is reserved for a loan from this pool without selecting a specific investor or linking investors to borrowers.') }}</p>
                @if ($loan->application_status !== \App\Enums\ApplicationStatus::Accepted)
                    <p class="mt-2 font-semibold text-amber-700">{{ __('The borrower must accept the loan offer before capital verification can be recorded.') }}</p>
                @endif
            </div>
        </div>

        @php($poolShortage = Money::compare($this->pool['total_available'], $this->report['required']) >= 0 ? '0.00' : Money::sub($this->report['required'], $this->pool['total_available']))
        @if (Money::compare($poolShortage, '0') <= 0)
            <div class="fc-card flex flex-wrap items-center gap-3 border-emerald-200 bg-emerald-50/60 p-5">
                <flux:icon.check-circle class="size-5 shrink-0 text-emerald-600" />
                <p class="min-w-0 flex-1 text-xs leading-relaxed text-emerald-900">
                    {{ __('Available capital pool :available covers the approved amount :required. Capital can be reserved for this loan without selecting a specific investor.', ['available' => Money::format($this->pool['total_available']), 'required' => Money::format($this->report['required'])]) }}
                </p>
            </div>
        @else
            <div class="fc-card flex flex-wrap items-center gap-3 border-amber-200 bg-amber-50/60 p-5">
                <flux:icon.exclamation-triangle class="size-5 shrink-0 text-amber-600" />
                <p class="min-w-0 flex-1 text-xs leading-relaxed text-amber-900">
                    {{ __('Pending Capital — available pool capital is :available against a requirement of :required. Shortage: :shortage. The application waits until new cleared deposits arrive in the common pool.', ['available' => Money::format($this->pool['total_available']), 'required' => Money::format($this->report['required']), 'shortage' => Money::format($poolShortage)]) }}
                </p>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card :label="__('Approved requirement')" :value="Money::format($this->report['required'])" icon="banknotes" tone="navy" />
            <x-stat-card :label="__('Total cleared capital')" :value="Money::format($this->pool['total_cleared'])" hint="{{ __('All cleared investor deposits') }}" icon="arrow-down-tray" tone="blue" />
            <x-stat-card :label="__('Available pool capital')" :value="Money::format($this->pool['total_available'])" hint="{{ __('Cleared − active allocations') }}" icon="check-circle" tone="{{ Money::compare($this->pool['total_available'], $this->report['required']) >= 0 ? 'emerald' : 'amber' }}" />
            <x-stat-card :label="__('Pool shortage')" :value="Money::compare($this->pool['total_available'], $this->report['required']) >= 0 ? Money::format('0') : Money::format(Money::sub($this->report['required'], $this->pool['total_available']))" hint="{{ __('If above zero, more cleared capital is needed') }}" icon="exclamation-triangle" tone="{{ Money::compare($this->pool['total_available'], $this->report['required']) >= 0 ? 'emerald' : 'amber' }}" />
        </div>

        <div class="fc-card p-5 sm:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold">{{ __('Eligible investors') }}</h3>
                    <p class="mt-0.5 text-[11px] text-slate-400">{{ __('Active investor accounts only. Record deposits from an investor\'s profile page — pending deposits show as uncounted capital.') }}</p>
                </div>
            </div>

            @if ($this->report['investors'] === [])
                <p class="mt-4 rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">{{ __('No active investors yet. Register investors and record their deposits first.') }}</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[560px] text-left text-xs">
                        <thead class="border-b border-slate-200 text-[10px] uppercase tracking-[0.12em] text-slate-400">
                            <tr>
                                <th class="pb-2 font-semibold">{{ __('Investor') }}</th>
                                <th class="pb-2 text-end font-semibold">{{ __('Cleared deposits') }}</th>
                                <th class="pb-2 text-end font-semibold">{{ __('Active allocations') }}</th>
                                <th class="pb-2 text-end font-semibold">{{ __('Available capital') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($this->report['investors'] as $investor)
                                <tr wire:key="cap-inv-{{ $investor['id'] }}">
                                    <td class="py-2.5">
                                        <a href="{{ route('investors.show', $investor['id']) }}" wire:navigate class="font-semibold text-slate-800 hover:underline">{{ $investor['name'] }}</a>
                                        <span class="ml-1.5 text-[10px] text-slate-400">{{ $investor['code'] }}</span>
                                    </td>
                                    <td class="py-2.5 text-end tabular-nums text-slate-700">{{ Money::format($investor['deposited']) }}</td>
                                    <td class="py-2.5 text-end tabular-nums text-slate-700">{{ Money::format($investor['allocated']) }}</td>
                                    <td class="py-2.5 text-end font-semibold tabular-nums {{ $investor['available'] === '0.00' ? 'text-slate-400' : 'text-navy-900' }}">{{ Money::format($investor['available']) }}</td>
                                </tr>
                            @endforeach
                            <tr class="border-t border-slate-200 bg-slate-50/60 font-semibold text-slate-700">
                                <td class="py-2.5">{{ __('Totals') }}</td>
                                <td class="py-2.5 text-end tabular-nums">{{ Money::format($this->report['total_deposited']) }}</td>
                                <td class="py-2.5 text-end tabular-nums">{{ Money::format($this->report['total_allocated']) }}</td>
                                <td class="py-2.5 text-end tabular-nums">{{ Money::format($this->report['total_available']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                @if (! $loan->capital_verified_at)
                    <button type="button" wire:click="confirmVerified" wire:confirm="{{ __('Confirm that pooled available capital has been checked against the approved amount?') }}" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800 {{ $loan->application_status !== \App\Enums\ApplicationStatus::Accepted ? 'cursor-not-allowed opacity-50' : '' }}">
                        <flux:icon.shield-check class="size-4" />
                        {{ __('Mark capital verified') }}
                    </button>
                @elseif ($loan->funding_status !== \App\Enums\LoanFundingStatus::FullyFunded)
                    <p class="inline-flex items-center gap-2 rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2.5 text-[11px] font-medium text-emerald-800">
                        <flux:icon.check-circle class="size-4 shrink-0" />
                        {{ __('Verified on :date.', ['date' => $loan->capital_verified_at->format(\App\Support\Regional::dateFormat().', '.\App\Support\Regional::timeFormat())]) }}
                    </p>
                    <button type="button" wire:click="reservePoolCapital" wire:confirm="{{ __('Reserve :amount from the available capital pool for this loan? No specific investor is selected.', ['amount' => Money::format($this->report['required'])]) }}" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800 {{ Money::compare($this->pool['total_available'], $this->report['required']) < 0 ? 'cursor-not-allowed opacity-50' : '' }}">
                        <flux:icon.lock-closed class="size-4" />
                        {{ __('Reserve capital from pool') }}
                    </button>
                @else
                    <p class="inline-flex items-center gap-2 rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2.5 text-[11px] font-medium text-emerald-800">
                        <flux:icon.lock-closed class="size-4 shrink-0" />
                        {{ __('Capital reserved and funding locked on :date.', ['date' => $loan->funding_locked_at?->format(\App\Support\Regional::dateFormat().', '.\App\Support\Regional::timeFormat()) ?? __('—')]) }}
                    </p>
                    <a href="{{ route('loans.disbursement', $loan) }}" wire:navigate class="rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white transition hover:bg-navy-800">{{ __('Go to disbursement') }}</a>
                @endif
                <a href="{{ route('loans.show', $loan) }}" wire:navigate class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Back to loan') }}</a>
            </div>
        </div>
    </div>
</div>

