<x-layout>
    @push('scripts')
        <script>
            // Initialize flatpickr for the effective date datepicker
            document.addEventListener('DOMContentLoaded', function() {
                if (flatpickr) {
                    flatpickrEffectiveDate.refresh();
                }
            });
        </script>
    @endpush

    @section('content')
    <div class="px-4 py-6">
        <div class="fc-card p-5 sm:p-6">
            <div class="flex flex-col sm:flex-row justify-between items-start gap-4 mb-5">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight">{{ __('Foreclose Loan') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Loan account :code', ['code' => $loan->loan_code]) }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Borrower :name', ['name' => $loan->borrower->full_name]) }}</p>
                </div>

                @if ($loan->status === LoanStatus::Active)
                    <flux:button wire:click="open" variant="primary" class="!rounded-xl">
                        {{ __('Foreclose Loan') }}
                    </flux:button>
                @endif
            </div>

            @if ($loan->status !== LoanStatus::Active)
                <div class="fc-card p-5 mt-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-slate-500">{{ __('This loan is not active and cannot be foreclosed.') }}</p>
                    </div>
                </div>
            @endif

            @if ($showForecloseModal)
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showForecloseModal', false)"></div>

                    <div class="relative w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl max-w-md">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-base font-semibold">{{ __('Foreclose Loan') }}</h3>
                                <p class="mt-0.5 text-xs text-slate-400">{{ $loan->loan_code }} · {{ $loan->borrower->full_name }}</p>
                            </div>
                            <button type="button" wire:click="$set('showForecloseModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                                <flux:icon-x-mark class="size-4" />
                            </button>
                        </div>

                        <form wire:submit.prevent="submitForeclosureRequest" class="mt-6 space-y-5">
                            <!-- Foreclosure Calculation Summary -->
                            <div class="fc-card p-4">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-400">{{ __('Calculation Summary') }}</h3>

                                <dl class="mt-3 space-y-2 text-sm">
                                    <div class="flex justify-between"><dt class="text-slate-400">{{ __('Outstanding principal') }}</dt><dd class="font-medium tabular-nums">{{ \App\Support\Money::format($outstandingPrincipal) }}</dd></div>
                                    <div class="flex justify-between"><dt class="text-slate-400">{{ __('Accrued interest') }}</dt><dd class="font-medium tabular-nums">{{ \App\Support\Money::format($accruedInterest) }}</dd></div>
                                    @if ($chargesEnabled)
                                        <div class="flex justify-between"><dt class="text-slate-400">{{ __('Foreclosure charges') }}</dt><dd class="font-medium tabular-nums text-rose-600">{{ \App\Support\Money::format($foreclosureCharges) }}</dd></div>
                                    @else
                                        <div class="flex justify-between"><dt class="text-slate-400">{{ __('Foreclosure charges') }}</dt><dd class="font-medium tabular-nums text-emerald-600">{{ \App\Support\Money::format($foreclosureCharges) }} ({{ __('disabled - per current policy') }})</dd></div>
                                    @endif
                                    <div class="flex justify-between"><dt class="text-slate-400">{{ __('Other outstanding dues') }}</dt><dd class="font-medium tabular-nums">{{ \App\Support\Money::format($otherOutstandingDues) }}</dd></div>
                                    <div class="flex justify-between"><dt class="text-slate-400">{{ __('Eligible adjustments/credits') }}</dt><dd class="font-medium tabular-nums text-emerald-600 {{ Money::compare($eligibleAdjustments, '0') > 0 ? '- ' + \App\Support\Money::format($eligibleAdjustments) : '' }}">{{ Money::compare($eligibleAdjustments, '0') > 0 ? '-' + \App\Support\Money::format($eligibleAdjustments) : _('none') }}</dd></div>
                                </dl>

                                <div class="mt-4 pt-4 border-t border-slate-100">
                                    <div class="flex justify-between text-[11px] text-slate-600">
                                        <span>{{ __('Calculation date') }}</span>
                                        <span>{{ \Illuminate\Support\Carbon::parse($calculationDate)->format('d M, Y') }}</span>
                                    </div>
                                    <div class="flex justify-between text-[11px] text-slate-600">
                                        <span>{{ __('Total foreclosure amount') }}</span>
                                        <span class="font-semibold tabular-nums">{{ \App\Support\Money::format($totalSettlement) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Instructions -->
                            <div class="fc-card p-4">
                                <h3 class="text-sm font-semibold uppercase tracking-wider text-slate-400">{{ __('Payment Instructions') }}</h3>
                                <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $paymentInstructions }}</p>
                                <p class="mt-2 text-xs text-slate-500">{{ __('Once paid, the loan will be marked as Foreclosed.') }}</p>
                            </div>

                            <!-- Effective Date -->
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">{{ __('Foreclosure effective date') }}</label>
                                <flux:input wire:model="foreclosureEffectiveDate" type="date" required max="{{ now()->format('Y-m-d') }}" class="block w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 px-3 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20"/>
                                @error('foreclosureEffectiveDate') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            <!-- Charges Status -->
                            @if ($chargesEnabled)
                                <div class="mt-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl">
                                    <p class="text-xs font-semibold text-emerald-700 mb-1">{{ __('Foreclosure charges are currently enabled') }}</p>
                                    <p class="text-[11px] text-emerald-600">{{ __('Charge type: :type', ['type' => ucfirst($chargeType)]) }}</p>
                                    <p class="text-[11px] text-emerald-600">{{ __('Charge value: :value', ['value' => \App\Support\Money::format($chargeValue)]) }}</p>
                                </div>
                            @else
                                <div class="mt-4 p-3 bg-slate-50 border border-slate-200 rounded-xl">
                                    <p class="text-xs font-semibold text-slate-700 mb-1">{{ __('Foreclosure charges are currently disabled') }}</p>
                                    <p class="text-[11px] text-slate-600">{{ __('Per current borrower policy, no foreclosure charges are applied. Future interest is also excluded.') }}</p>
                                </div>
                            @endif

                            <div class="flex items-center justify-end gap-3 mt-6">
                                <button type="button" wire:click="$set('showForecloseModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                                    {{ __('Cancel') }}
                                </button>
                                <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Submit Foreclosure Request') }}</flux:button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
    @endsection
</x-layout>