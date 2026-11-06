<?php

use App\Models\InterestConfiguration;
use App\Models\Investor;
use App\Models\InvestorInterestRate;
use App\Models\LoanDistributionConfig;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Interest & Distribution Settings')] class extends Component
{
    public bool $showConfigModal = false;

    public string $name = '';

    public string $effective_from = '';

    public string $borrower_monthly_rate = '1.5';

    public string $default_investor_monthly_rate = '1.5';

    public string $investor_allocation_percent = '60';

    public string $admin_allocation_percent = '10';

    public string $company_allocation_percent = '30';

    public string $default_admin_commission_rate = '10';

    public string $notes = '';

    public bool $is_active = true;

    public bool $showRateModal = false;

    public string $investor_id = '';

    public string $monthly_rate = '1.5';

    public string $rate_effective_from = '';

    public string $rate_effective_to = '';

    public string $rate_notes = '';

    public bool $rate_is_active = true;

    public function mount(): void
    {
        $this->effective_from = now()->format('Y-m-d');
        $this->rate_effective_from = now()->format('Y-m-d');
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
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, InvestorInterestRate>
     */
    #[Computed]
    public function rates()
    {
        return InvestorInterestRate::query()
            ->with(['investor', 'createdBy'])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Investor>
     */
    #[Computed]
    public function investors()
    {
        return Investor::query()->orderBy('name')->get();
    }

    #[Computed]
    public function activeConfiguration(): ?InterestConfiguration
    {
        return InterestConfiguration::activeFor();
    }

    public function openConfigModal(): void
    {
        Gate::authorize('create', InterestConfiguration::class);

        $this->resetValidation();
        $this->name = '';
        $this->effective_from = now()->format('Y-m-d');
        $this->borrower_monthly_rate = '1.5';
        $this->default_investor_monthly_rate = '1.5';
        $this->investor_allocation_percent = '60';
        $this->admin_allocation_percent = '10';
        $this->company_allocation_percent = '30';
        $this->default_admin_commission_rate = '10';
        $this->notes = '';
        $this->is_active = true;
        $this->showConfigModal = true;
    }

    public function saveConfiguration(): void
    {
        Gate::authorize('create', InterestConfiguration::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'effective_from' => ['required', 'date'],
            'borrower_monthly_rate' => ['required', 'numeric', 'gt:0', 'max:100'],
            'default_investor_monthly_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'investor_allocation_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'admin_allocation_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'company_allocation_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'default_admin_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);

        $total = Money::add(
            $validated['investor_allocation_percent'],
            $validated['admin_allocation_percent'],
            $validated['company_allocation_percent'],
        );

        if (Money::compare($total, '100') !== 0) {
            $this->addError('company_allocation_percent', __('Investor, admin and company shares must total exactly 100%. Current total: :total%.', ['total' => $total]));

            return;
        }

        if (Money::compare($validated['default_investor_monthly_rate'], $validated['borrower_monthly_rate']) > 0) {
            $this->addError('default_investor_monthly_rate', __('The default investor monthly rate cannot exceed the borrower monthly rate.'));

            return;
        }

        InterestConfiguration::query()->create([
            'name' => $validated['name'],
            'effective_from' => $validated['effective_from'],
            'borrower_monthly_rate' => $validated['borrower_monthly_rate'],
            'default_investor_monthly_rate' => $validated['default_investor_monthly_rate'],
            'investor_allocation_percent' => $validated['investor_allocation_percent'],
            'admin_allocation_percent' => $validated['admin_allocation_percent'],
            'company_allocation_percent' => $validated['company_allocation_percent'],
            'default_admin_commission_rate' => $validated['default_admin_commission_rate'] ?? null,
            'is_active' => $validated['is_active'],
            'notes' => filled($validated['notes']) ? $validated['notes'] : null,
            'created_by' => auth()->id(),
        ]);

        $this->showConfigModal = false;
        unset($this->configurations, $this->activeConfiguration);
        Flux::toast(variant: 'success', text: __('Interest configuration created. Existing loan snapshots are unchanged.'));
    }

    public function toggleConfiguration(int $configurationId): void
    {
        $configuration = InterestConfiguration::query()->findOrFail($configurationId);
        Gate::authorize('update', $configuration);

        $configuration->update(['is_active' => ! $configuration->is_active]);

        unset($this->configurations, $this->activeConfiguration);
        Flux::toast(variant: 'success', text: __('Configuration status updated.'));
    }

    public function deleteConfiguration(int $configurationId): void
    {
        $configuration = InterestConfiguration::query()->findOrFail($configurationId);
        Gate::authorize('delete', $configuration);

        if (LoanDistributionConfig::query()->where('interest_configuration_id', $configuration->id)->exists()) {
            Flux::toast(variant: 'danger', text: __('This configuration is locked into a loan snapshot and cannot be deleted. Deactivate it instead.'));

            return;
        }

        $configuration->delete();

        unset($this->configurations, $this->activeConfiguration);
        Flux::toast(variant: 'success', text: __('Configuration deleted.'));
    }

    public function openRateModal(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $this->resetValidation();
        $this->investor_id = '';
        $this->monthly_rate = (string) ($this->activeConfiguration?->default_investor_monthly_rate ?? '1.5');
        $this->rate_effective_from = now()->format('Y-m-d');
        $this->rate_effective_to = '';
        $this->rate_notes = '';
        $this->rate_is_active = true;
        $this->showRateModal = true;
    }

    public function saveRate(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $validated = $this->validate([
            'investor_id' => ['required', Rule::exists('investors', 'id')],
            'monthly_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'rate_effective_from' => ['required', 'date'],
            'rate_effective_to' => ['nullable', 'date', 'after_or_equal:rate_effective_from'],
            'rate_notes' => ['nullable', 'string', 'max:2000'],
            'rate_is_active' => ['boolean'],
        ]);

        $borrowerRate = (string) (InterestConfiguration::activeFor($validated['rate_effective_from'])?->borrower_monthly_rate ?? 0);

        if (Money::compare($borrowerRate, '0') > 0 && Money::compare($validated['monthly_rate'], $borrowerRate) > 0) {
            $this->addError('monthly_rate', __('An investor monthly rate cannot exceed the borrower monthly rate of :rate%.', ['rate' => rtrim(rtrim($borrowerRate, '0'), '.')]));

            return;
        }

        InvestorInterestRate::query()->updateOrCreate(
            [
                'investor_id' => $validated['investor_id'],
                'effective_from' => $validated['rate_effective_from'],
            ],
            [
                'monthly_rate' => $validated['monthly_rate'],
                'effective_to' => filled($validated['rate_effective_to']) ? $validated['rate_effective_to'] : null,
                'is_active' => $validated['rate_is_active'],
                'notes' => filled($validated['rate_notes']) ? $validated['rate_notes'] : null,
                'created_by' => auth()->id(),
            ],
        );

        $this->showRateModal = false;
        unset($this->rates);
        Flux::toast(variant: 'success', text: __('Investor rate saved. New loan snapshots will use it.'));
    }

    public function deleteRate(int $rateId): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        InvestorInterestRate::query()->findOrFail($rateId)->delete();

        unset($this->rates);
        Flux::toast(variant: 'success', text: __('Investor rate deleted.'));
    }
};
?>

<div class="mx-auto max-w-[1500px] space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Distribution') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Interest & Distribution Settings') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Global defaults for collected-interest allocation, individual investor rates and admin commission defaults.') }}</p>
        </div>

        @if (auth()->user()->isSuperAdmin())
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="openRateModal" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300">
                    <flux:icon.user-plus class="size-4" />
                    {{ __('Investor rate') }}
                </button>
                <button type="button" wire:click="openConfigModal" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                    <flux:icon.plus class="size-4" />
                    {{ __('New configuration') }}
                </button>
            </div>
        @endif
    </div>

    @if ($this->activeConfiguration)
        <div class="rounded-2xl border border-blue-200 bg-blue-50/70 px-5 py-4">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-blue-900">
                <span class="font-semibold">{{ __('Active configuration') }}: {{ $this->activeConfiguration->name }}</span>
                <span>{{ __('Borrower :rate%/mo', ['rate' => rtrim(rtrim((string) $this->activeConfiguration->borrower_monthly_rate, '0'), '.')]) }}</span>
                <span>{{ __('Investor :rate%/mo', ['rate' => rtrim(rtrim((string) $this->activeConfiguration->default_investor_monthly_rate, '0'), '.')]) }}</span>
                <span>{{ __('Split :split', ['split' => rtrim(rtrim((string) $this->activeConfiguration->investor_allocation_percent, '0'), '.') . ' / ' . rtrim(rtrim((string) $this->activeConfiguration->admin_allocation_percent, '0'), '.') . ' / ' . rtrim(rtrim((string) $this->activeConfiguration->company_allocation_percent, '0'), '.')]) }}</span>
                <span>{{ __('Effective :date', ['date' => $this->activeConfiguration->effective_from->format('d M Y')]) }}</span>
            </div>
        </div>
    @else
        <div class="rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-xs text-amber-900">
            {{ __('No active interest configuration. Create one before disbursing loans or distributing collected interest.') }}
        </div>
    @endif

    <div class="fc-card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <div>
                <p class="fc-eyebrow">{{ __('Global') }}</p>
                <h3 class="mt-1 text-sm font-semibold">{{ __('Interest configurations') }}</h3>
            </div>
            <span class="text-[11px] text-slate-400">{{ $this->configurations->count() }} {{ __('versions') }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="fc-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('Configuration') }}</th>
                        <th class="px-5 py-3">{{ __('Effective') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Borrower') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Investor') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Admin') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Company') }}</th>
                        <th class="px-5 py-3">{{ __('Status') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->configurations as $configuration)
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="px-5 py-3">
                                <p class="font-medium text-slate-800">{{ $configuration->name }}</p>
                                <p class="text-[11px] text-slate-400">{{ __('Default investor :rate%/mo · admin commission :admin%', ['rate' => rtrim(rtrim((string) $configuration->default_investor_monthly_rate, '0'), '.'), 'admin' => rtrim(rtrim((string) ($configuration->default_admin_commission_rate ?? '0'), '0'), '.')]) }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $configuration->effective_from->format('d M Y') }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-slate-600">{{ rtrim(rtrim((string) $configuration->borrower_monthly_rate, '0'), '.') }}%</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-blue-700">{{ rtrim(rtrim((string) $configuration->investor_allocation_percent, '0'), '.') }}%</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-violet-700">{{ rtrim(rtrim((string) $configuration->admin_allocation_percent, '0'), '.') }}%</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-emerald-700">{{ rtrim(rtrim((string) $configuration->company_allocation_percent, '0'), '.') }}%</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $configuration->is_active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20' }}">
                                    {{ $configuration->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-end">
                                @if (auth()->user()->isSuperAdmin())
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button" wire:click="toggleConfiguration({{ $configuration->id }})" class="text-[11px] font-semibold {{ $configuration->is_active ? 'text-amber-600 hover:text-amber-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                            {{ $configuration->is_active ? __('Deactivate') : __('Activate') }}
                                        </button>
                                        <button type="button" wire:click="deleteConfiguration({{ $configuration->id }})" wire:confirm="{{ __('Delete this configuration? This is only possible when no loan snapshot uses it.') }}" class="text-[11px] font-semibold text-rose-600 hover:text-rose-700">{{ __('Delete') }}</button>
                                    </div>
                                @else
                                    <span class="text-[11px] text-slate-400">{{ __('Super Admin only') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-14 text-center text-sm text-slate-400">{{ __('No configurations yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="fc-card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <div>
                <p class="fc-eyebrow">{{ __('Overrides') }}</p>
                <h3 class="mt-1 text-sm font-semibold">{{ __('Individual investor monthly rates') }}</h3>
            </div>
            <span class="text-[11px] text-slate-400">{{ $this->rates->count() }} {{ __('rates') }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="fc-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('Investor') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Monthly rate') }}</th>
                        <th class="px-5 py-3">{{ __('Effective') }}</th>
                        <th class="px-5 py-3">{{ __('Ends') }}</th>
                        <th class="px-5 py-3">{{ __('Status') }}</th>
                        <th class="px-5 py-3">{{ __('Notes') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->rates as $rate)
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="px-5 py-3">
                                <a href="{{ route('investors.show', $rate->investor) }}" class="font-medium text-slate-800 hover:text-blue-600">{{ $rate->investor->name }}</a>
                                <p class="text-[11px] text-slate-400">{{ $rate->investor->investor_code }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-end font-semibold tabular-nums text-slate-800">{{ rtrim(rtrim((string) $rate->monthly_rate, '0'), '.') }}%</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $rate->effective_from->format('d M Y') }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $rate->effective_to?->format('d M Y') ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $rate->is_active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/20' }}">
                                    {{ $rate->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500">{{ $rate->notes ?: '—' }}</td>
                            <td class="px-5 py-3 text-end">
                                @if (auth()->user()->isSuperAdmin())
                                    <button type="button" wire:click="deleteRate({{ $rate->id }})" wire:confirm="{{ __('Delete this investor rate? Existing loan snapshots are unaffected.') }}" class="text-[11px] font-semibold text-rose-600 hover:text-rose-700">{{ __('Delete') }}</button>
                                @else
                                    <span class="text-[11px] text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-14 text-center text-sm text-slate-400">{{ __('No individual investor rates. The global default applies.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showConfigModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showConfigModal', false)"></div>
            <div class="relative w-full max-w-xl rounded-2xl bg-white p-5 shadow-2xl">
                <h3 class="text-base font-semibold">{{ __('New interest configuration') }}</h3>
                <p class="mt-0.5 text-xs text-slate-400">{{ __('Applies to loans snapshotted from the effective date. Existing snapshots never change.') }}</p>

                <form wire:submit="saveConfiguration" class="mt-4 space-y-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <flux:input wire:model="name" :label="__('Name')" required maxlength="120" />
                        <flux:input wire:model="effective_from" :label="__('Effective from')" type="date" required />
                    </div>
                    @error('name') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    @error('effective_from') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="grid gap-3 sm:grid-cols-2">
                        <flux:input wire:model="borrower_monthly_rate" :label="__('Borrower monthly rate (%)')" type="number" step="0.000001" min="0" max="100" required />
                        <flux:input wire:model="default_investor_monthly_rate" :label="__('Default investor rate (%)')" type="number" step="0.000001" min="0" max="100" required />
                    </div>
                    @error('borrower_monthly_rate') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    @error('default_investor_monthly_rate') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="grid gap-3 sm:grid-cols-3">
                        <flux:input wire:model="investor_allocation_percent" :label="__('Investor %')" type="number" step="0.0001" min="0" max="100" required />
                        <flux:input wire:model="admin_allocation_percent" :label="__('Admin %')" type="number" step="0.0001" min="0" max="100" required />
                        <flux:input wire:model="company_allocation_percent" :label="__('Company %')" type="number" step="0.0001" min="0" max="100" required />
                    </div>
                    @error('investor_allocation_percent') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    @error('admin_allocation_percent') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    @error('company_allocation_percent') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="grid gap-3 sm:grid-cols-2">
                        <flux:input wire:model="default_admin_commission_rate" :label="__('Default admin commission (%)')" type="number" step="0.0001" min="0" max="100" />
                        <label class="flex items-end gap-2 pb-2.5 text-xs font-medium text-slate-600">
                            <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-blue-600">
                            {{ __('Active configuration') }}
                        </label>
                    </div>
                    @error('default_admin_commission_rate') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" maxlength="2000" />

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showConfigModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Create configuration') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showRateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showRateModal', false)"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <h3 class="text-base font-semibold">{{ __('Individual investor rate') }}</h3>
                <p class="mt-0.5 text-xs text-slate-400">{{ __('Overrides the global default for future loan snapshots.') }}</p>

                <form wire:submit="saveRate" class="mt-4 space-y-3">
                    <flux:select wire:model="investor_id" :label="__('Investor')" required>
                        <option value="">{{ __('Select an investor') }}</option>
                        @foreach ($this->investors as $investor)
                            <option value="{{ $investor->id }}">{{ $investor->name }} · {{ $investor->investor_code }}</option>
                        @endforeach
                    </flux:select>
                    @error('investor_id') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <flux:input wire:model="monthly_rate" :label="__('Monthly rate (%)')" type="number" step="0.000001" min="0" max="100" required />
                    @error('monthly_rate') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-2 gap-3">
                        <flux:input wire:model="rate_effective_from" :label="__('Effective from')" type="date" required />
                        <flux:input wire:model="rate_effective_to" :label="__('Effective to')" type="date" />
                    </div>
                    @error('rate_effective_from') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    @error('rate_effective_to') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <input type="checkbox" wire:model="rate_is_active" class="rounded border-slate-300 text-blue-600">
                        {{ __('Active rate') }}
                    </label>

                    <flux:textarea wire:model="rate_notes" :label="__('Notes')" rows="2" maxlength="2000" />

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showRateModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Save rate') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
