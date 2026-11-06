<?php

use App\Models\User;
use App\Services\Settings\UserPreferenceService;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Work Preferences')] class extends Component
{
    public string $dashboard = '';

    public string $dateFilter = 'today';

    public string $rowsPerPage = '12';

    public string $reportingPeriod = 'current_month';

    public function mount(): void
    {
        $preferences = app(UserPreferenceService::class);
        $user = auth()->user();

        $this->dashboard = (string) ($preferences->get($user, 'dashboard', '') ?? '');
        $this->dateFilter = (string) $preferences->get($user, 'date_filter', 'today');
        $this->rowsPerPage = (string) $preferences->getInt($user, 'rows_per_page', 12);
        $this->reportingPeriod = (string) $preferences->get($user, 'reporting_period', 'current_month');
    }

    public function save(): void
    {
        $validated = $this->validate([
            'dashboard' => ['nullable', 'in:,reports.index,operations.index,collections.index,loans.index,payouts.index'],
            'dateFilter' => ['required', 'in:today,this_week,this_month,this_financial_year'],
            'rowsPerPage' => ['required', 'integer', 'in:10,12,15,20,25,50'],
            'reportingPeriod' => ['required', 'in:current_month,last_month,current_quarter,current_financial_year'],
        ]);

        /** @var User $user */
        $user = auth()->user();

        app(UserPreferenceService::class)->putMany($user, [
            'dashboard' => $validated['dashboard'] ?: '',
            'date_filter' => $validated['dateFilter'],
            'rows_per_page' => (int) $validated['rowsPerPage'],
            'reporting_period' => $validated['reportingPeriod'],
        ]);

        Flux::toast(variant: 'success', text: __('Work preferences saved.'));
    }
};
?>

<section class="w-full">
    <x-pages::settings.layout :heading="__('Work Preferences')" :subheading="__('Personalise your dashboard, filters and table density. These preferences apply to your account only.')">
        <form wire:submit="save" class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.adjustments-horizontal class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Workspace defaults') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Applied next time you sign in or open the relevant screen.') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <flux:select wire:model="dashboard" :label="__('Default landing page after sign-in')">
                            <option value="">{{ __('My role dashboard (:role)', ['role' => auth()->user()->role->label()]) }}</option>
                            <option value="reports.index">{{ __('Reports') }}</option>
                            <option value="operations.index">{{ __('Daily Operations') }}</option>
                            <option value="collections.index">{{ __('EMI Collections') }}</option>
                            <option value="loans.index">{{ __('Loans') }}</option>
                            <option value="payouts.index">{{ __('Interest Payouts') }}</option>
                        </flux:select>
                        @error('dashboard') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:select wire:model="dateFilter" :label="__('Default date filter')">
                            <option value="today">{{ __('Today') }}</option>
                            <option value="this_week">{{ __('This week') }}</option>
                            <option value="this_month">{{ __('This month') }}</option>
                            <option value="this_financial_year">{{ __('This financial year') }}</option>
                        </flux:select>
                        @error('dateFilter') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:select wire:model="rowsPerPage" :label="__('Table rows per page')">
                            @foreach ([10, 12, 15, 20, 25, 50] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </flux:select>
                        @error('rowsPerPage') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:select wire:model="reportingPeriod" :label="__('Default reporting period')">
                            <option value="current_month">{{ __('Current month') }}</option>
                            <option value="last_month">{{ __('Last month') }}</option>
                            <option value="current_quarter">{{ __('Current quarter') }}</option>
                            <option value="current_financial_year">{{ __('Current financial year') }}</option>
                        </flux:select>
                        @error('reportingPeriod') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">{{ __('Notification preferences') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-500">{{ __('Notification choices live on their own screen so each category can be tuned.') }}</p>
                    </div>
                    <a href="{{ route('settings.notifications') }}" wire:navigate
                       class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300">
                        <flux:icon.bell class="size-4" />
                        {{ __('Manage notifications') }}
                    </a>
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
