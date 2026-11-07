<?php

use App\Enums\Permission;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\Money;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Investor & Deposit Settings')] class extends Component
{
    public string $minInvestment = '0';

    public string $maxInvestment = '';

    public bool $requireKycVerified = false;

    public bool $activationRequiresKyc = false;

    public string $managementFeePercent = '10';

    public string $tdsPercent = '10';

    public string $depositMin = '1';

    public string $depositMax = '100000000';

    public bool $verificationRequired = true;

    public bool $allowReclearRejected = false;

    public string $minAllocationAmount = '0';

    public bool $allowPartial = true;

    public bool $allowReallocation = true;

    public string $reason = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin() ?? false, 403);

        $settings = app(SettingsService::class);

        $this->minInvestment = $settings->getDecimal('investor.min_investment', '0');
        $this->maxInvestment = (string) ($settings->get('investor.max_investment') ?? '');
        $this->requireKycVerified = $settings->getBool('investor.require_kyc_verified', false);
        $this->activationRequiresKyc = $settings->getBool('investor.activation_requires_kyc', false);
        $this->managementFeePercent = $settings->getDecimal('investor.management_fee_percent', '10');
        $this->tdsPercent = $settings->getDecimal('investor.tds_percent', '10');
        $this->depositMin = $settings->getDecimal('deposit.min_amount', '1');
        $this->depositMax = $settings->getDecimal('deposit.max_amount', '100000000');
        $this->verificationRequired = $settings->getBool('deposit.verification_required', true);
        $this->allowReclearRejected = $settings->getBool('deposit.allow_reclear_rejected', false);
        $this->minAllocationAmount = $settings->getDecimal('deposit.min_allocation_amount', '0');
        $this->allowPartial = $settings->getBool('allocation.allow_partial', true);
        $this->allowReallocation = $settings->getBool('allocation.allow_reallocation', true);
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin() ?? false, 403);

        $validated = $this->validate([
            'minInvestment' => ['required', 'numeric', 'min:0'],
            'maxInvestment' => ['nullable', 'numeric', 'gt:minInvestment'],
            'managementFeePercent' => ['required', 'numeric', 'min:0', 'max:100'],
            'tdsPercent' => ['required', 'numeric', 'min:0', 'max:100'],
            'depositMin' => ['required', 'numeric', 'min:0'],
            'depositMax' => ['required', 'numeric', 'gt:depositMin'],
            'minAllocationAmount' => ['required', 'numeric', 'min:0'],
            'requireKycVerified' => ['boolean'],
            'activationRequiresKyc' => ['boolean'],
            'verificationRequired' => ['boolean'],
            'allowReclearRejected' => ['boolean'],
            'allowPartial' => ['boolean'],
            'allowReallocation' => ['boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        /** @var User $actor */
        $actor = auth()->user();

        app(SettingsService::class)->putMany([
            'investor.min_investment' => $validated['minInvestment'],
            'investor.max_investment' => $validated['maxInvestment'] === '' ? null : $validated['maxInvestment'],
            'investor.require_kyc_verified' => $validated['requireKycVerified'],
            'investor.activation_requires_kyc' => $validated['activationRequiresKyc'],
            'investor.management_fee_percent' => $validated['managementFeePercent'],
            'investor.tds_percent' => $validated['tdsPercent'],
            'deposit.min_amount' => $validated['depositMin'],
            'deposit.max_amount' => $validated['depositMax'],
            'deposit.verification_required' => $validated['verificationRequired'],
            'deposit.allow_reclear_rejected' => $validated['allowReclearRejected'],
            'deposit.min_allocation_amount' => $validated['minAllocationAmount'],
            'allocation.allow_partial' => $validated['allowPartial'],
            'allocation.allow_reallocation' => $validated['allowReallocation'],
        ], $actor, $validated['reason']);

        $this->reason = '';

        Flux::toast(variant: 'success', text: __('Investor and deposit settings saved. Existing allocations are untouched.'));
    }
};
?>

<section class="w-full">
    <x-pages::settings.layout :heading="__('Investor & Deposit Settings')" :subheading="__('Eligibility limits, deposit verification rules and allocation controls.')">
        <form wire:submit="save" class="space-y-6">
            {{-- Investor configuration --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.building-library class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Investor configuration') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Applied when an investor commits or is allocated capital.') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <flux:input wire:model="minInvestment" :label="__('Minimum investment amount')" type="number" step="0.01" min="0" required />
                        <p class="mt-2 text-[11px] text-slate-400">{{ __('Minimum cleared deposits required before an investor can be allocated capital.') }}</p>
                        @error('minInvestment') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="maxInvestment" :label="__('Maximum investment amount (optional)')" type="number" step="0.01" min="0" />
                        <p class="mt-2 text-[11px] text-slate-400">{{ __('Leave blank for no upper limit.') }}</p>
                        @error('maxInvestment') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            <flux:switch wire:model="requireKycVerified" />
                            {{ __('Require verified KYC before funding a loan') }}
                        </label>
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            <flux:switch wire:model="activationRequiresKyc" />
                            {{ __('Require verified KYC before activating an investor') }}
                        </label>
                    </div>
                    <div>
                        <flux:input wire:model="managementFeePercent" :label="__('Investor management fee (%)')" type="number" step="0.01" min="0" max="100" required />
                        <p class="mt-2 text-[11px] text-slate-400">{{ __('Deducted when investor payouts are settled.') }}</p>
                        @error('managementFeePercent') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="tdsPercent" :label="__('Investor TDS (%)')" type="number" step="0.01" min="0" max="100" required />
                        <p class="mt-2 text-[11px] text-slate-400">{{ __('Withheld on investor payout settlement.') }}</p>
                        @error('tdsPercent') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Deposit configuration --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.wallet class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Deposit configuration') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Pending deposits are never treated as cleared funds.') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <flux:input wire:model="depositMin" :label="__('Minimum deposit amount')" type="number" step="0.01" min="0" required />
                        @error('depositMin') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="depositMax" :label="__('Maximum deposit amount')" type="number" step="0.01" min="0" required />
                        @error('depositMax') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            <flux:switch wire:model="verificationRequired" />
                            {{ __('Deposit verification required before clearing') }}
                        </label>
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            <flux:switch wire:model="allowReclearRejected" />
                            {{ __('Allow a rejected deposit to be re-cleared later') }}
                        </label>
                    </div>
                    <div class="sm:col-span-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] leading-relaxed text-slate-500">
                            {{ __('Rejection always requires a reason and is recorded in the deposit history. Clearing a deposit records who verified it and when.') }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Allocation configuration --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.arrows-pointing-out class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Allocation configuration') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Controls how cleared deposits are allocated to loans.') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <flux:input wire:model="minAllocationAmount" :label="__('Minimum allocation amount')" type="number" step="0.01" min="0" required />
                        <p class="mt-2 text-[11px] text-slate-400">{{ __('Smallest single allocation line allowed.') }}</p>
                        @error('minAllocationAmount') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="space-y-3 pt-1">
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            <flux:switch wire:model="allowPartial" />
                            {{ __('Allow capital to be drawn from multiple capital accounts (pool allocation)') }}
                        </label>
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            <flux:switch wire:model="allowReallocation" />
                            {{ __('Allow released allocations to be re-allocated') }}
                        </label>
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-400">
                            <flux:switch :checked="true" disabled />
                            {{ __('Prevent duplicate allocation (always enforced)') }}
                        </label>
                    </div>
                </div>

                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] leading-relaxed text-slate-500">
                    {{ __('Capital allocation uses the common available capital pool: available capital is total cleared deposits minus total active allocations. Investor and borrower records stay independent — no specific investor is selected for a loan. Changing these settings never rewrites existing allocations.') }}
                </div>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-5">
                <flux:textarea wire:model="reason" :label="__('Reason for this change')" rows="2" maxlength="500"
                    placeholder="{{ __('Example: verification workflow tightened after internal audit') }}" required />
                <p class="mt-2 text-[11px] text-amber-800">{{ __('Deposit verification and eligibility rules are financial controls: the reason is recorded with your name, role and IP address.') }}</p>
                @error('reason') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-navy-900 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                    <flux:icon.check class="size-4" />
                    {{ __('Save settings') }}
                </button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
