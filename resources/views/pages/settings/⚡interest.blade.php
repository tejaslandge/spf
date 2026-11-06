<?php

use App\Enums\Permission;
use App\Models\InterestConfiguration;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Interest Distribution Settings')] class extends Component
{
    public string $configName = '';

    public string $effectiveFrom = '';

    public string $borrowerMonthlyRate = '1.5';

    public string $investorMonthlyRate = '1.5';

    public string $adminCommissionRate = '0.5';

    public string $reason = '';

    public function mount(): void
    {
        abort_unless(
            auth()->user()?->hasAnyPermission([Permission::SettingsViewInterest, Permission::SettingsModify]) ?? false,
            403,
        );

        $active = InterestConfiguration::activeFor();

        $this->effectiveFrom = now()->format('Y-m-d');
        $this->configName = 'Monthly rates effective '.now()->format(\App\Support\Regional::dateFormat());

        if ($active !== null) {
            $rates = $active->rateBreakdown();

            $this->borrowerMonthlyRate = $this->trim($rates['borrower']);
            $this->investorMonthlyRate = $this->trim($rates['investor']);
            $this->adminCommissionRate = $this->trim($rates['admin']);
        }
    }

    /**
     * Company margin = borrower rate − investor rate − admin commission.
     */
    #[Computed]
    public function companyMonthlyRate(): string
    {
        return $this->trim(Money::sub($this->borrowerMonthlyRate ?: '0', $this->investorMonthlyRate ?: '0', $this->adminCommissionRate ?: '0'));
    }

    #[Computed]
    public function distributionTotal(): string
    {
        return $this->trim(Money::add($this->investorMonthlyRate ?: '0', $this->adminCommissionRate ?: '0', $this->companyMonthlyRate()));
    }

    /**
     * Collected-interest split derived from the configured rates. The loan
     * snapshot engine distributes collected interest by these percentages.
     */
    #[Computed]
    public function derivedSplit(): array
    {
        $borrower = $this->borrowerMonthlyRate ?: '0';

        if (Money::compare($borrower, '0') <= 0) {
            return ['investor' => '0', 'admin' => '0', 'company' => '100'];
        }

        $investor = Money::round(Money::div(Money::mul($this->investorMonthlyRate ?: '0', '100'), $borrower), 4);
        $admin = Money::round(Money::div(Money::mul($this->adminCommissionRate ?: '0', '100'), $borrower), 4);
        $company = Money::sub('100', $investor, $admin);

        if (bccomp($company, '0', 4) < 0) {
            $company = '0';
        }

        return [
            'investor' => rtrim(rtrim($investor, '0'), '.') ?: '0',
            'admin' => rtrim(rtrim($admin, '0'), '.') ?: '0',
            'company' => rtrim(rtrim($company, '0'), '.') ?: '0',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, InterestConfiguration>
     */
    #[Computed]
    public function configurations()
    {
        return InterestConfiguration::query()
            ->with('createdBy')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function activeConfiguration(): ?InterestConfiguration
    {
        return InterestConfiguration::activeFor();
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->hasPermission(Permission::SettingsModify) ?? false, 403);

        Gate::authorize('create', InterestConfiguration::class);

        $validated = $this->validate([
            'configName' => ['required', 'string', 'max:120'],
            'effectiveFrom' => ['required', 'date'],
            'borrowerMonthlyRate' => ['required', 'numeric', 'gt:0', 'max:100'],
            'investorMonthlyRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'adminCommissionRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $borrower = (string) $validated['borrowerMonthlyRate'];
        $investor = (string) $validated['investorMonthlyRate'];
        $admin = (string) $validated['adminCommissionRate'];

        $company = Money::sub($borrower, $investor, $admin);

        if (Money::compare($company, '0', 6) < 0) {
            $this->addError('adminCommissionRate', __('Investor rate + admin commission cannot exceed the borrower monthly rate of :rate%.', ['rate' => $this->trim($borrower)]));

            return;
        }

        $split = $this->derivedSplit();

        $configuration = InterestConfiguration::query()->create([
            'name' => $validated['configName'],
            'effective_from' => $validated['effectiveFrom'],
            'borrower_monthly_rate' => $borrower,
            'default_investor_monthly_rate' => $investor,
            'investor_allocation_percent' => $split['investor'],
            'admin_allocation_percent' => $split['admin'],
            'company_allocation_percent' => $split['company'],
            'default_admin_commission_rate' => $admin,
            'is_active' => true,
            'notes' => $validated['reason'],
            'created_by' => auth()->id(),
        ]);

        /** @var User $actor */
        $actor = auth()->user();

        AuditLogger::log('interest.configuration.created', $configuration, [], [
            'name' => $validated['configName'],
            'borrower_monthly_rate' => $borrower,
            'investor_monthly_rate' => $investor,
            'admin_commission_rate' => $admin,
            'company_monthly_rate' => $this->trim($company),
            'collected_interest_split' => $split,
            'reason' => $validated['reason'],
            'role' => $actor->role->value,
        ]);

        unset($this->configurations, $this->activeConfiguration);

        $this->reason = '';

        Flux::toast(variant: 'success', text: __('Interest distribution settings saved as a new version. Existing loan snapshots are unchanged.'));
    }

    private function trim(string $value): string
    {
        $value = rtrim(rtrim($value, '0'), '.');

        return $value === '' ? '0' : $value;
    }
};
?>

<section class="w-full">
    <x-pages::settings.layout :heading="__('Interest Distribution Settings')" :subheading="__('Rate distribution between investors, admins and the company for newly disbursed loans.')">
        {{-- Live distribution preview --}}
        <div class="rounded-2xl border border-navy-100 bg-navy-50/50 p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-navy-700">{{ __('Live distribution preview') }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Company margin is calculated automatically as borrower rate − investor rate − admin commission.') }}</p>
                </div>
                @if ($this->companyMonthlyRate() < 0)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-[11px] font-semibold text-red-700">
                        <flux:icon.exclamation-triangle class="size-3.5" />
                        {{ __('Negative company margin') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-semibold text-emerald-700">
                        <flux:icon.check-circle class="size-3.5" />
                        {{ __('Valid distribution') }}
                    </span>
                @endif
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full max-w-xl text-sm">
                    <thead class="fc-table-head">
                        <tr>
                            <th class="px-4 py-2.5">{{ __('Beneficiary') }}</th>
                            <th class="px-4 py-2.5 text-end">{{ __('Monthly rate') }}</th>
                            <th class="px-4 py-2.5 text-end">{{ __('Share of collected interest') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr>
                            <td class="px-4 py-2.5 text-slate-700">{{ __('Investor') }}</td>
                            <td class="px-4 py-2.5 text-end font-semibold tabular-nums text-blue-700">{{ $this->trim($investorMonthlyRate ?: '0') }}%</td>
                            <td class="px-4 py-2.5 text-end tabular-nums text-slate-500">{{ $this->derivedSplit()['investor'] }}%</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 text-slate-700">{{ __('Admin') }}</td>
                            <td class="px-4 py-2.5 text-end font-semibold tabular-nums text-violet-700">{{ $this->trim($adminCommissionRate ?: '0') }}%</td>
                            <td class="px-4 py-2.5 text-end tabular-nums text-slate-500">{{ $this->derivedSplit()['admin'] }}%</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-2.5 text-slate-700">{{ __('Company') }}</td>
                            <td class="px-4 py-2.5 text-end font-semibold tabular-nums text-emerald-700">{{ $this->companyMonthlyRate() }}%</td>
                            <td class="px-4 py-2.5 text-end tabular-nums text-slate-500">{{ $this->derivedSplit()['company'] }}%</td>
                        </tr>
                        <tr class="bg-slate-50/70">
                            <td class="px-4 py-2.5 font-semibold text-slate-800">{{ __('Total') }}</td>
                            <td class="px-4 py-2.5 text-end font-semibold tabular-nums text-slate-800">{{ $this->distributionTotal() }}%</td>
                            <td class="px-4 py-2.5 text-end tabular-nums text-slate-500">100%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        @if ($this->activeConfiguration)
            @php($activeRates = $this->activeConfiguration->rateBreakdown())
            <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-2xl border border-slate-200 bg-white px-5 py-4 text-xs text-slate-600 shadow-sm">
                <span class="font-semibold text-slate-800">{{ __('Active version') }}: {{ $this->activeConfiguration->name }}</span>
                <span>{{ __('Borrower :rate%/mo', ['rate' => $this->trim($activeRates['borrower'])]) }}</span>
                <span>{{ __('Investor :rate%/mo', ['rate' => $this->trim($activeRates['investor'])]) }}</span>
                <span>{{ __('Effective :date', ['date' => $this->activeConfiguration->effective_from->format(\App\Support\Regional::dateFormat())]) }}</span>
            </div>
        @endif

        <form wire:submit="save" class="mt-6 space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.chart-pie class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Rate settings') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Saving creates a new configuration version. Loans already disbursed keep their snapshotted rates.') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <flux:input wire:model="configName" :label="__('Version name')" required maxlength="120" />
                        @error('configName') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="effectiveFrom" :label="__('Effective from')" type="date" required />
                        @error('effectiveFrom') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model.live.debounce.400ms="borrowerMonthlyRate" :label="__('Borrower monthly interest rate (%)')" type="number" step="0.0001" min="0" max="100" required />
                        @error('borrowerMonthlyRate') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model.live.debounce.400ms="investorMonthlyRate" :label="__('Investor monthly interest rate (%)')" type="number" step="0.0001" min="0" max="100" required />
                        @error('investorMonthlyRate') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model.live.debounce.400ms="adminCommissionRate" :label="__('Admin monthly commission (%)')" type="number" step="0.0001" min="0" max="100" required />
                        @error('adminCommissionRate') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-slate-700">{{ __('Company monthly margin') }}</label>
                        <div class="mt-1 flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold tabular-nums {{ $this->companyMonthlyRate() < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                            {{ $this->companyMonthlyRate() }}%
                        </div>
                        <p class="mt-2 text-[11px] text-slate-400">{{ __('Calculated automatically — never edited directly.') }}</p>
                    </div>
                </div>

                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] leading-relaxed text-slate-500">
                    {{ __('Investor and admin rates are snapshotted against each loan on disbursement. Changing them here never alters existing approved loans, schedules or payouts.') }}
                </div>
            </div>

            {{-- Payout configuration (fixed by the distribution engine) --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.arrow-path-rounded-square class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Payout configuration') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Fixed by the approved distribution workflow — shown for transparency.') }}</p>
                    </div>
                </div>

                <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 px-4 py-3">
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">{{ __('Investor payout method') }}</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-800">{{ __('Actual interest collection') }}</dd>
                        <dd class="mt-1 text-[11px] text-slate-500">{{ __('Only verified, non-reversed EMI interest receipts create payouts.') }}</dd>
                    </div>
                    <div class="rounded-xl border border-slate-200 px-4 py-3">
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">{{ __('Payout frequency') }}</dt>
                        <dd class="mt-1 text-sm font-medium text-slate-800">{{ __('Monthly batches') }}</dd>
                        <dd class="mt-1 text-[11px] text-slate-500">{{ __('Generated per period month; Super Admin approves and processes each batch.') }}</dd>
                    </div>
                    <div class="rounded-xl border border-slate-200 px-4 py-3 sm:col-span-2">
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">{{ __('Distribution eligibility rules') }}</dt>
                        <dd class="mt-2 space-y-1 text-[11px] text-slate-500">
                            <p>{{ __('• Only EMI receipts marked verified and not reversed contribute.') }}</p>
                            <p>{{ __('• Scheduled or uncollected interest never creates an entitlement.') }}</p>
                            <p>{{ __('• Each ledger entry can be linked to exactly one payout item.') }}</p>
                            <p>{{ __('• Investor-specific rates override the default for new loan snapshots.') }}</p>
                        </dd>
                    </div>
                </dl>

                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('distribution.settings') }}" wire:navigate
                       class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300">
                        <flux:icon.adjustments-horizontal class="size-4" />
                        {{ __('Manage investor-specific rates') }}
                    </a>
                    <a href="{{ route('payouts.index') }}" wire:navigate
                       class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300">
                        <flux:icon.currency-dollar class="size-4" />
                        {{ __('Open payout batches') }}
                    </a>
                </div>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-5">
                <flux:textarea wire:model="reason" :label="__('Reason for this change')" rows="2" maxlength="500"
                    placeholder="{{ __('Example: investor rate revision approved on 05 Nov 2026') }}" required />
                <p class="mt-2 text-[11px] text-amber-800">{{ __('Interest distribution changes are financial decisions: your reason, role and IP address are recorded with the previous and new values.') }}</p>
                @error('reason') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-navy-900 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                    <flux:icon.check class="size-4" />
                    {{ __('Save as new version') }}
                </button>
            </div>
        </form>

        {{-- Version history --}}
        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('History') }}</p>
                    <h3 class="mt-1 text-sm font-semibold text-slate-900">{{ __('Configuration versions') }}</h3>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="fc-table-head">
                        <tr>
                            <th class="px-5 py-3">{{ __('Version') }}</th>
                            <th class="px-5 py-3">{{ __('Effective') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('Borrower') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('Investor') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('Admin') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('Company') }}</th>
                            <th class="px-5 py-3">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->configurations as $configuration)
                            @php($rates = $configuration->rateBreakdown())
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800">{{ $configuration->name }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $configuration->createdBy?->name ?? __('System') }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $configuration->effective_from->format(\App\Support\Regional::dateFormat()) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-slate-600">{{ $this->trim($rates['borrower']) }}%</td>
                                <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-blue-700">{{ $this->trim($rates['investor']) }}%</td>
                                <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-violet-700">{{ $this->trim($rates['admin']) }}%</td>
                                <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-emerald-700">{{ $this->trim($rates['company']) }}%</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $configuration->is_active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20' }}">
                                        {{ $configuration->is_active ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-14 text-center text-sm text-slate-400">{{ __('No interest configurations yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
