<?php

use App\Models\User;
use App\Services\Settings\UserPreferenceService;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notification Preferences')] class extends Component
{
    public bool $emailEnabled = true;

    public bool $inAppEnabled = true;

    public bool $loanUpdates = true;

    public bool $loanApprovals = true;

    public bool $documentUpdates = true;

    public bool $depositUpdates = true;

    public bool $paymentUpdates = true;

    public bool $repaymentUpdates = true;

    public bool $systemAlerts = true;

    public function mount(): void
    {
        $preferences = app(UserPreferenceService::class);
        $user = auth()->user();

        $this->emailEnabled = $preferences->getBool($user, 'notifications.email_enabled', true);
        $this->inAppEnabled = $preferences->getBool($user, 'notifications.in_app_enabled', true);
        $this->loanUpdates = $preferences->getBool($user, 'notifications.loan_updates', true);
        $this->loanApprovals = $preferences->getBool($user, 'notifications.loan_approvals', true);
        $this->documentUpdates = $preferences->getBool($user, 'notifications.document_updates', true);
        $this->depositUpdates = $preferences->getBool($user, 'notifications.deposit_updates', true);
        $this->paymentUpdates = $preferences->getBool($user, 'notifications.payment_updates', true);
        $this->repaymentUpdates = $preferences->getBool($user, 'notifications.repayment_updates', true);
        $this->systemAlerts = $preferences->getBool($user, 'notifications.system_alerts', true);
    }

    public function save(): void
    {
        $this->validate([
            'emailEnabled' => ['boolean'],
            'inAppEnabled' => ['boolean'],
            'loanUpdates' => ['boolean'],
            'loanApprovals' => ['boolean'],
            'documentUpdates' => ['boolean'],
            'depositUpdates' => ['boolean'],
            'paymentUpdates' => ['boolean'],
            'repaymentUpdates' => ['boolean'],
            'systemAlerts' => ['boolean'],
        ]);

        /** @var User $user */
        $user = auth()->user();

        app(UserPreferenceService::class)->putMany($user, [
            'notifications.email_enabled' => $this->emailEnabled,
            'notifications.in_app_enabled' => $this->inAppEnabled,
            'notifications.loan_updates' => $this->loanUpdates,
            'notifications.loan_approvals' => $this->loanApprovals,
            'notifications.document_updates' => $this->documentUpdates,
            'notifications.deposit_updates' => $this->depositUpdates,
            'notifications.payment_updates' => $this->paymentUpdates,
            'notifications.repayment_updates' => $this->repaymentUpdates,
            'notifications.system_alerts' => $this->systemAlerts,
        ]);

        Flux::toast(variant: 'success', text: __('Notification preferences saved.'));
    }
};
?>

<section class="w-full">
    <x-pages::settings.layout :heading="__('Notifications')" :subheading="__('Choose which updates reach you and how they are delivered. These preferences apply to your account only.')">
        <form wire:submit="save" class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.bell class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Delivery channels') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Where your notifications are delivered.') }}</p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <flux:switch wire:model="emailEnabled" />
                        {{ __('Email notifications') }}
                    </label>
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <flux:switch wire:model="inAppEnabled" />
                        {{ __('In-app notifications') }}
                    </label>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.funnel class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Notification types') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Turn individual categories on or off.') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3">
                        <flux:switch wire:model="loanUpdates" />
                        <span>
                            <span class="block text-xs font-semibold text-slate-700">{{ __('Loan application updates') }}</span>
                            <span class="mt-0.5 block text-[11px] text-slate-400">{{ __('New applications, offer acceptance and status moves.') }}</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3">
                        <flux:switch wire:model="loanApprovals" />
                        <span>
                            <span class="block text-xs font-semibold text-slate-700">{{ __('Loan approval notifications') }}</span>
                            <span class="mt-0.5 block text-[11px] text-slate-400">{{ __('Final approvals and disbursements.') }}</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3">
                        <flux:switch wire:model="documentUpdates" />
                        <span>
                            <span class="block text-xs font-semibold text-slate-700">{{ __('Borrower & investor document updates') }}</span>
                            <span class="mt-0.5 block text-[11px] text-slate-400">{{ __('KYC submissions, approvals and rejections.') }}</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3">
                        <flux:switch wire:model="depositUpdates" />
                        <span>
                            <span class="block text-xs font-semibold text-slate-700">{{ __('Investor deposit notifications') }}</span>
                            <span class="mt-0.5 block text-[11px] text-slate-400">{{ __('Deposits recorded, cleared or rejected.') }}</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3">
                        <flux:switch wire:model="paymentUpdates" />
                        <span>
                            <span class="block text-xs font-semibold text-slate-700">{{ __('Payment notifications') }}</span>
                            <span class="mt-0.5 block text-[11px] text-slate-400">{{ __('Payout batches approved or processed.') }}</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3">
                        <flux:switch wire:model="repaymentUpdates" />
                        <span>
                            <span class="block text-xs font-semibold text-slate-700">{{ __('Repayment notifications') }}</span>
                            <span class="mt-0.5 block text-[11px] text-slate-400">{{ __('EMI receipts recorded or reversed.') }}</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 sm:col-span-2">
                        <flux:switch wire:model="systemAlerts" />
                        <span>
                            <span class="block text-xs font-semibold text-slate-700">{{ __('System alerts') }}</span>
                            <span class="mt-0.5 block text-[11px] text-slate-400">{{ __('Security events, backup failures and configuration warnings.') }}</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-navy-900 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                    <flux:icon.check class="size-4" />
                    {{ __('Save preferences') }}
                </button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
