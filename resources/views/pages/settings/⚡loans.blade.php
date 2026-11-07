<?php

use App\Enums\InterestMethod;
use App\Enums\Permission;
use App\Services\Settings\SettingsRegistry;
use App\Services\Settings\SettingsService;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Loan Settings')] class extends Component
{
    public string $reason = '';

    public string $minAmount = '1000';

    public string $maxAmount = '100000000';

    public string $defaultAmount = '';

    public string $minTenure = '1';

    public string $maxTenure = '360';

    public string $tenureOptions = '6, 12, 18, 24, 36';

    public string $defaultMonthlyRate = '1.5';

    public string $minMonthlyRate = '0';

    public string $maxMonthlyRate = '5';

    /** @var array<int, string> */
    public array $allowedMethods = ['flat', 'reducing'];

    public string $processingFeeAmount = '0';

    public string $processingFeeType = 'fixed';

    public string $processingFeePercent = '0';

    public string $processingFeeCollection = 'record_only';

    public bool $requireSuperAdminApproval = false;

    public bool $penaltyEnabled = false;

    public string $penaltyMethod = 'per_day_percent';

    public string $penaltyRate = '0';

    public string $penaltyGraceDays = '0';

    public string $penaltyMaxAmount = '';

    public function mount(): void
    {
        abort_unless(
            auth()->user()?->hasAnyPermission([Permission::SettingsViewLoan, Permission::SettingsModify]) ?? false,
            403,
        );

        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        $settings = app(SettingsService::class);

        $this->minAmount = $settings->getDecimal('loan.min_amount', '1000');
        $this->maxAmount = $settings->getDecimal('loan.max_amount', '100000000');
        $this->defaultAmount = (string) ($settings->get('loan.default_amount') ?? '');
        $this->minTenure = (string) $settings->getInt('loan.min_tenure_months', 1);
        $this->maxTenure = (string) $settings->getInt('loan.max_tenure_months', 360);
        $this->tenureOptions = implode(', ', $settings->getJson('loan.tenure_options', [6, 12, 18, 24, 36]));
        $this->defaultMonthlyRate = $settings->getDecimal('loan.default_monthly_rate', '4');
        $this->minMonthlyRate = $settings->getDecimal('loan.min_monthly_rate', '0');
        $this->maxMonthlyRate = $settings->getDecimal('loan.max_monthly_rate', '5');
        $this->allowedMethods = array_values(array_intersect(
            $settings->getJson('loan.allowed_interest_methods', ['flat', 'reducing']),
            array_map(fn (InterestMethod $method): string => $method->value, InterestMethod::cases()),
        )) ?: ['flat', 'reducing'];
        $this->processingFeeAmount = $settings->getDecimal('loan.processing_fee_amount', '0');
        $this->processingFeeType = (string) $settings->get('loan.processing_fee_type', 'fixed');
        $this->processingFeePercent = $settings->getDecimal('loan.processing_fee_percent', '0');
        $this->processingFeeCollection = (string) $settings->get('loan.processing_fee_collection', 'record_only');
        $this->requireSuperAdminApproval = $settings->getBool('loan.require_super_admin_approval', false);
        $this->penaltyEnabled = $settings->getBool('loan.penalty_enabled', false);
        $this->penaltyMethod = (string) $settings->get('loan.penalty_method', 'per_day_percent');
        $this->penaltyRate = $settings->getDecimal('loan.penalty_rate', '0');
        $this->penaltyGraceDays = (string) $settings->getInt('loan.penalty_grace_days', 0);
        $this->penaltyMaxAmount = (string) ($settings->get('loan.penalty_max_amount') ?? '');
    }

    public function resetForm(): void
    {
        $this->loadSettings();
        $this->resetValidation();
        $this->reason = '';

        Flux::toast(variant: 'info', text: __('Form reset to the saved settings.'));
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->hasPermission(Permission::SettingsModify) ?? false, 403);

        $validated = $this->validate([
            'minAmount' => ['required', 'numeric', 'min:0'],
            'maxAmount' => ['required', 'numeric', 'gt:minAmount'],
            'defaultAmount' => ['nullable', 'numeric', 'min:minAmount', 'max:maxAmount'],
            'minTenure' => ['required', 'integer', 'min:1', 'max:600'],
            'maxTenure' => ['required', 'integer', 'gte:minTenure', 'max:600'],
            'tenureOptions' => ['required', 'string', 'max:255'],
            'defaultMonthlyRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'minMonthlyRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'maxMonthlyRate' => ['required', 'numeric', 'gte:minMonthlyRate', 'max:100'],
            'allowedMethods' => ['required', 'array', 'min:1'],
            'allowedMethods.*' => ['string', 'in:flat,reducing'],
            'processingFeeAmount' => ['required', 'numeric', 'min:0'],
            'processingFeeType' => ['required', 'in:fixed,percentage'],
            'processingFeePercent' => ['required', 'numeric', 'min:0', 'max:100'],
            'processingFeeCollection' => ['required', 'in:record_only,deduct_at_disbursement'],
            'penaltyMethod' => ['required', 'in:per_day_percent,flat_percent'],
            'penaltyRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'penaltyGraceDays' => ['required', 'integer', 'min:0', 'max:365'],
            'penaltyMaxAmount' => ['nullable', 'numeric', 'min:0'],
            'requireSuperAdminApproval' => ['boolean'],
            'penaltyEnabled' => ['boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $defaultRate = (float) $validated['defaultMonthlyRate'];

        if ($defaultRate < (float) $validated['minMonthlyRate'] || $defaultRate > (float) $validated['maxMonthlyRate']) {
            $this->addError('defaultMonthlyRate', __('The default monthly rate must fall between the minimum and maximum rate.'));

            return;
        }

        $tenureOptions = collect(explode(',', $validated['tenureOptions']))
            ->map(fn (string $value): int => (int) trim($value))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($tenureOptions->isEmpty()) {
            $this->addError('tenureOptions', __('Provide at least one tenure option in months.'));

            return;
        }

        if ($tenureOptions->first() < (int) $validated['minTenure'] || $tenureOptions->last() > (int) $validated['maxTenure']) {
            $this->addError('tenureOptions', __('Tenure options must fall between the minimum and maximum tenure.'));

            return;
        }

        if ($validated['penaltyEnabled'] && (float) $validated['penaltyRate'] <= 0) {
            $this->addError('penaltyRate', __('Set a penalty rate before enabling the late payment penalty.'));

            return;
        }

        if ($validated['processingFeeType'] === 'percentage' && (float) $validated['processingFeePercent'] <= 0) {
            $this->addError('processingFeePercent', __('Set a processing fee percentage, or switch the fee type to fixed.'));

            return;
        }

        /** @var \App\Models\User $actor */
        $actor = auth()->user();

        app(SettingsService::class)->putMany([
            'loan.min_amount' => $validated['minAmount'],
            'loan.max_amount' => $validated['maxAmount'],
            'loan.default_amount' => $validated['defaultAmount'] === '' ? null : $validated['defaultAmount'],
            'loan.min_tenure_months' => (int) $validated['minTenure'],
            'loan.max_tenure_months' => (int) $validated['maxTenure'],
            'loan.tenure_options' => $tenureOptions->all(),
            'loan.default_monthly_rate' => $validated['defaultMonthlyRate'],
            'loan.min_monthly_rate' => $validated['minMonthlyRate'],
            'loan.max_monthly_rate' => $validated['maxMonthlyRate'],
            'loan.allowed_interest_methods' => array_values($validated['allowedMethods']),
            'loan.processing_fee_amount' => $validated['processingFeeAmount'],
            'loan.processing_fee_type' => $validated['processingFeeType'],
            'loan.processing_fee_percent' => $validated['processingFeePercent'],
            'loan.processing_fee_collection' => $validated['processingFeeCollection'],
            'loan.require_super_admin_approval' => $validated['requireSuperAdminApproval'],
            'loan.penalty_enabled' => $validated['penaltyEnabled'],
            'loan.penalty_method' => $validated['penaltyMethod'],
            'loan.penalty_rate' => $validated['penaltyRate'],
            'loan.penalty_grace_days' => (int) $validated['penaltyGraceDays'],
            'loan.penalty_max_amount' => $validated['penaltyMaxAmount'] === '' ? null : $validated['penaltyMaxAmount'],
        ], $actor, $validated['reason']);

        $this->reason = '';
        $this->loadSettings();

        Flux::toast(variant: 'success', text: __('Loan settings saved. Existing loans keep their original terms.'));
    }
};
?>

<section class="w-full">
    <x-pages::settings.layout :heading="__('Loan Settings')" :subheading="__('Amount, tenure, interest and penalty rules applied to new loan applications. Existing loans are never recalculated.')">
        <form wire:submit="save" class="space-y-6">
            {{-- Loan amounts --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.banknotes class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Loan amount') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Limits enforced on every new loan application.') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-3">
                    <div>
                        <flux:input wire:model="minAmount" :label="__('Minimum loan amount')" type="number" step="0.01" min="0" required />
                        @error('minAmount') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="maxAmount" :label="__('Maximum loan amount')" type="number" step="0.01" min="0" required />
                        @error('maxAmount') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="defaultAmount" :label="__('Default loan amount (optional)')" type="number" step="0.01" min="0" />
                        @error('defaultAmount') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Tenure --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.calendar-days class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Loan tenure') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Tenure is measured in months.') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <flux:input wire:model="minTenure" :label="__('Minimum tenure (months)')" type="number" min="1" required />
                        @error('minTenure') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="maxTenure" :label="__('Maximum tenure (months)')" type="number" min="1" required />
                        @error('maxTenure') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <flux:input wire:model="tenureOptions" :label="__('Available tenure options')" placeholder="6, 12, 18, 24, 36" required />
                        <p class="mt-2 text-[11px] text-slate-400">{{ __('Comma-separated months offered as quick choices on the loan form.') }}</p>
                        @error('tenureOptions') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Interest --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.percent-badge class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Interest configuration') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Rates are monthly percentages on the outstanding terms.') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-3">
                    <div>
                        <flux:input wire:model="defaultMonthlyRate" :label="__('Default monthly rate (%)')" type="number" step="0.01" min="0" required />
                        @error('defaultMonthlyRate') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="minMonthlyRate" :label="__('Minimum monthly rate (%)')" type="number" step="0.01" min="0" required />
                        @error('minMonthlyRate') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="maxMonthlyRate" :label="__('Maximum monthly rate (%)')" type="number" step="0.01" min="0" required />
                        @error('maxMonthlyRate') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-5">
                    <p class="text-xs font-semibold text-slate-700">{{ __('Available interest methods') }}</p>
                    <div class="mt-2 flex flex-wrap gap-5">
                        @foreach (\App\Enums\InterestMethod::cases() as $method)
                            <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                                <input type="checkbox" wire:model="allowedMethods" value="{{ $method->value }}" class="rounded border-slate-300 text-navy-700 focus:ring-navy-600">
                                {{ $method->label() }}
                            </label>
                        @endforeach
                    </div>
                    @error('allowedMethods') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Processing --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.receipt-percent class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Loan processing') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Default fee applied when a loan offer is generated.') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <flux:select wire:model.live="processingFeeType" :label="__('Processing fee type')" required>
                            <option value="fixed">{{ __('Fixed amount') }}</option>
                            <option value="percentage">{{ __('Percentage of principal') }}</option>
                        </flux:select>
                    </div>
                    @if ($processingFeeType === 'fixed')
                        <div>
                            <flux:input wire:model="processingFeeAmount" :label="__('Processing fee amount')" type="number" step="0.01" min="0" />
                            @error('processingFeeAmount') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div>
                            <flux:input wire:model="processingFeePercent" :label="__('Processing fee (%)')" type="number" step="0.01" min="0" max="100" />
                            @error('processingFeePercent') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    <div>
                        <flux:select wire:model="processingFeeCollection" :label="__('Fee collection rule')">
                            <option value="record_only">{{ __('Record on the loan only') }}</option>
                            <option value="deduct_at_disbursement">{{ __('Deduct from the disbursed amount') }}</option>
                        </flux:select>
                        <p class="mt-2 text-[11px] text-slate-400">{{ __('Deduction posts the fee as income and reduces the cash disbursed to the borrower.') }}</p>
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            <flux:switch wire:model="requireSuperAdminApproval" />
                            {{ __('Final approval must be recorded by a Super Admin') }}
                        </label>
                    </div>
                </div>

                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] leading-relaxed text-slate-500">
                    {{ __('Every loan still follows the existing approval gates: accepted application → capital verified → fully funded → final approval → disbursement.') }}
                </div>
            </div>

            {{-- Penalty --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                            <flux:icon.exclamation-triangle class="size-4" />
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">{{ __('Late payment penalty') }}</h2>
                            <p class="text-xs text-slate-500">{{ __('Automatically suggested for overdue EMI collections when enabled.') }}</p>
                        </div>
                    </div>
                    <flux:switch wire:model.live="penaltyEnabled" />
                </div>

                @if ($penaltyEnabled)
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div>
                            <flux:select wire:model="penaltyMethod" :label="__('Penalty calculation method')">
                                <option value="per_day_percent">{{ __('Percentage per day on the overdue amount') }}</option>
                                <option value="flat_percent">{{ __('One-time percentage of the overdue amount') }}</option>
                            </flux:select>
                        </div>
                        <div>
                            <flux:input wire:model="penaltyRate" :label="__('Penalty rate (%)')" type="number" step="0.01" min="0" max="100" />
                            @error('penaltyRate') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <flux:input wire:model="penaltyGraceDays" :label="__('Grace period (days)')" type="number" min="0" max="365" />
                            @error('penaltyGraceDays') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <flux:input wire:model="penaltyMaxAmount" :label="__('Maximum penalty (optional)')" type="number" step="0.01" min="0" />
                            @error('penaltyMaxAmount') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif
            </div>

            {{-- Reason and actions --}}
            <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-5">
                <flux:textarea wire:model="reason" :label="__('Reason for this change')" rows="2" maxlength="500"
                    placeholder="{{ __('Example: revised rate policy approved by the board on 01 Nov 2026') }}" required />
                <p class="mt-2 text-[11px] text-amber-800">{{ __('Loan limits and penalties are financial controls: the reason is recorded with your name, role, IP address and the previous values.') }}</p>
                @error('reason') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <button type="button" wire:click="resetForm"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900">
                    {{ __('Reset') }}
                </button>
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-navy-900 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                    <flux:icon.check class="size-4" />
                    {{ __('Save loan settings') }}
                </button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
