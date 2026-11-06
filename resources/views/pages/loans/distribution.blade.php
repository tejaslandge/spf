<?php

use App\Enums\DistributionBeneficiaryType;
use App\Enums\DistributionEntryType;
use App\Models\InterestDistributionLedger;
use App\Models\Loan;
use App\Services\InterestDistributionService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Loan Distribution')] class extends Component
{
    public Loan $loan;

    public string $beneficiary = '';

    public function mount(Loan $loan): void
    {
        Gate::authorize('view', $loan);

        $this->loan = $loan->load('borrower');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\LoanInvestorDistribution>
     */
    #[Computed]
    public function investorRows()
    {
        return $this->loan->investorDistributions()->with(['investor', 'allocation'])->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\LoanAdminAssignment>
     */
    #[Computed]
    public function adminRows()
    {
        return $this->loan->adminAssignments()->with('user')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, InterestDistributionLedger>
     */
    #[Computed]
    public function entries()
    {
        return InterestDistributionLedger::query()
            ->with(['investor', 'admin', 'payment'])
            ->where('loan_id', $this->loan->getKey())
            ->when($this->beneficiary !== '', fn ($query) => $query->where('beneficiary_type', $this->beneficiary))
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function totals(): array
    {
        $entries = InterestDistributionLedger::query()->where('loan_id', $this->loan->getKey());

        $earning = (clone $entries)->earnings();
        $reversals = (clone $entries)->reversals();

        return [
            'collected' => Money::round((string) $earning->sum('collected_interest')),
            'reversed' => Money::round((string) $reversals->sum('collected_interest')),
            'investor' => Money::round(Money::sub(
                (string) (clone $entries)->forInvestors()->earnings()->sum('amount'),
                (string) (clone $entries)->forInvestors()->reversals()->sum('amount'),
            )),
            'admin' => Money::round(Money::sub(
                (string) (clone $entries)->forAdmins()->earnings()->sum('amount'),
                (string) (clone $entries)->forAdmins()->reversals()->sum('amount'),
            )),
            'company' => Money::round(Money::sub(
                (string) (clone $entries)->forCompany()->earnings()->sum('amount'),
                (string) (clone $entries)->forCompany()->reversals()->sum('amount'),
            )),
        ];
    }

    #[Computed]
    public function pendingCollections(): int
    {
        return $this->loan->payments()
            ->where('is_reversed', false)
            ->where('interest_component', '>', 0)
            ->whereDoesntHave('distributions')
            ->count();
    }

    public function createSnapshot(): void
    {
        Gate::authorize('update', $this->loan);

        try {
            app(InterestDistributionService::class)->ensureSnapshot($this->loan, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->investorRows, $this->adminRows, $this->totals, $this->pendingCollections);
        $this->loan->refresh();

        Flux::toast(variant: 'success', text: __('Distribution snapshot locked for this loan.'));
    }

    public function distributeExisting(): void
    {
        Gate::authorize('update', $this->loan);

        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $pending = $this->loan->payments()
            ->where('is_reversed', false)
            ->where('interest_component', '>', 0)
            ->whereDoesntHave('distributions')
            ->get();

        if ($pending->isEmpty()) {
            Flux::toast(text: __('No un-distributed interest collections were found.'));

            return;
        }

        try {
            $service = app(InterestDistributionService::class);
            $service->ensureSnapshot($this->loan, auth()->user());

            foreach ($pending as $payment) {
                $service->distributeForPayment($payment, auth()->user());
            }
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->investorRows, $this->adminRows, $this->entries, $this->totals, $this->pendingCollections);
        $this->loan->refresh();

        Flux::toast(variant: 'success', text: __(':count collection(s) distributed.', ['count' => $pending->count()]));
    }
};
?>

<div class="mx-auto max-w-[1500px] space-y-5">
    <div class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ route('loans.index') }}" class="hover:text-blue-600">{{ __('Loans') }}</a>
        <flux:icon.chevron-right class="size-3" />
        <a href="{{ route('loans.show', $loan) }}" class="hover:text-blue-600">{{ $loan->loan_code }}</a>
        <flux:icon.chevron-right class="size-3" />
        <span class="text-slate-600">{{ __('Distribution') }}</span>
    </div>

    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Distribution') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Loan Interest Distribution') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $loan->loan_code }} · {{ $loan->borrower->full_name }} · {{ __('Immutable rules snapshot and collected-interest entries.') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('loans.show', $loan) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300">
                <flux:icon.arrow-left class="size-4" />
                {{ __('Back to loan') }}
            </a>
            @if ($loan->distributionConfig === null && auth()->user()->isSuperAdmin())
                <button type="button" wire:click="createSnapshot" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                    <flux:icon.lock-closed class="size-4" />
                    {{ __('Lock snapshot') }}
                </button>
            @endif
            @if ($loan->distributionConfig !== null && auth()->user()->isSuperAdmin() && $this->pendingCollections > 0)
                <button type="button" wire:click="distributeExisting" class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                    <flux:icon.arrow-path class="size-4" />
                    {{ __('Distribute :count collection(s)', ['count' => $this->pendingCollections]) }}
                </button>
            @endif
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat-card :label="__('Collected interest')" :value="\App\Support\Money::format($this->totals['collected'])" :hint="__('From verified EMI payments')" icon="receipt-percent" tone="navy" />
        <x-stat-card :label="__('Investor share')" :value="\App\Support\Money::format($this->totals['investor'])" :hint="__('Net of reversals')" icon="building-office-2" tone="blue" />
        <x-stat-card :label="__('Admin share')" :value="\App\Support\Money::format($this->totals['admin'])" :hint="__('Commission pool')" icon="user-group" tone="purple" />
        <x-stat-card :label="__('Company share')" :value="\App\Support\Money::format($this->totals['company'])" :hint="__('Residual')" icon="building-library" tone="emerald" />
        <x-stat-card :label="__('Reversed')" :value="\App\Support\Money::format($this->totals['reversed'])" :hint="__('Compensating entries')" icon="arrow-uturn-left" tone="rose" />
    </div>

    <div class="grid gap-4 xl:grid-cols-[360px_1fr]">
        <div class="space-y-4">
            <div class="fc-card p-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold">{{ __('Locked rules') }}</h3>
                    @if ($loan->distributionConfig?->isLocked())
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                            <flux:icon.lock-closed class="size-3" />
                            {{ __('Locked') }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">{{ __('Not snapshotted') }}</span>
                    @endif
                </div>

                @if ($config = $loan->distributionConfig)
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Configuration') }}</dt><dd class="text-end font-medium text-slate-800">{{ $config->configuration?->name ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Borrower monthly') }}</dt><dd class="font-medium text-slate-800">{{ rtrim(rtrim((string) $config->borrower_monthly_rate, '0'), '.') }}%</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Investor pool') }}</dt><dd class="font-medium text-blue-700">{{ rtrim(rtrim((string) $config->investor_allocation_percent, '0'), '.') }}%</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Admin pool') }}</dt><dd class="font-medium text-violet-700">{{ rtrim(rtrim((string) $config->admin_allocation_percent, '0'), '.') }}%</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Company pool') }}</dt><dd class="font-medium text-emerald-700">{{ rtrim(rtrim((string) $config->company_allocation_percent, '0'), '.') }}%</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Locked at') }}</dt><dd class="font-medium text-slate-800">{{ $config->locked_at?->format(\App\Support\Regional::dateFormat().', '.\App\Support\Regional::timeFormat()) ?? '—' }}</dd></div>
                    </dl>
                @else
                    <p class="mt-4 rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">{{ __('No snapshot exists yet. It is created automatically on disbursement, or manually by a Super Admin.') }}</p>
                @endif
            </div>

            <div class="fc-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold">{{ __('Responsible admins') }}</h3>
                    <span class="text-[11px] text-slate-400">{{ $this->adminRows->count() }}</span>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($this->adminRows as $assignment)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <flux:avatar :name="$assignment->user->name" :initials="str($assignment->user->name)->substr(0, 2)" size="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold text-slate-800">{{ $assignment->user->name }} @if ($assignment->is_primary)<span class="text-[10px] font-semibold text-blue-600">· {{ __('Primary') }}</span>@endif</p>
                                <p class="text-[11px] text-slate-400">{{ __('Commission weight') }} {{ rtrim(rtrim((string) $assignment->commission_rate, '0'), '.') }}%</p>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-xs text-slate-400">{{ __('No admins assigned in the snapshot.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="space-y-4">
            <div class="fc-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold">{{ __('Investor-wise principal & rates') }}</h3>
                    <span class="text-[11px] text-slate-400">{{ $this->investorRows->count() }} {{ __('investors') }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="fc-table-head">
                            <tr>
                                <th class="px-5 py-3">{{ __('Investor') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('Principal') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('Monthly rate') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('Principal share') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($this->investorRows as $row)
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="px-5 py-3">
                                        <a href="{{ route('investors.show', $row->investor) }}" class="font-medium text-slate-800 hover:text-blue-600">{{ $row->investor->name }}</a>
                                        <p class="text-[11px] text-slate-400">{{ $row->investor->investor_code }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-slate-600">{{ \App\Support\Money::format($row->principal_amount) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-slate-600">{{ rtrim(rtrim((string) $row->monthly_rate, '0'), '.') }}%</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-slate-600">{{ rtrim(rtrim((string) $row->share_percent, '0'), '.') }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-10 text-center text-xs text-slate-400">{{ __('No investor distribution rows.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="fc-card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold">{{ __('Distribution ledger') }}</h3>
                    <select wire:model.live="beneficiary" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-600 focus:border-blue-500 focus:outline-none">
                        <option value="">{{ __('All beneficiaries') }}</option>
                        @foreach (DistributionBeneficiaryType::cases() as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="fc-table-head">
                            <tr>
                                <th class="px-4 py-3">{{ __('Receipt') }}</th>
                                <th class="px-4 py-3">{{ __('Beneficiary') }}</th>
                                <th class="px-4 py-3">{{ __('Type') }}</th>
                                <th class="px-4 py-3 text-end">{{ __('Collected interest') }}</th>
                                <th class="px-4 py-3 text-end">{{ __('Rate') }}</th>
                                <th class="px-4 py-3 text-end">{{ __('Amount') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($this->entries as $entry)
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-slate-800">{{ $entry->reference ?: '—' }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $entry->period_month->format('M Y') }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        @if ($entry->beneficiary_type === DistributionBeneficiaryType::Investor)
                                            {{ $entry->investor?->name ?? '—' }}
                                        @elseif ($entry->beneficiary_type === DistributionBeneficiaryType::Admin)
                                            {{ $entry->admin?->name ?? '—' }}
                                        @else
                                            {{ __('Company') }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset {{ $entry->entry_type === DistributionEntryType::Reversal ? 'bg-rose-50 text-rose-700 ring-rose-600/20' : 'bg-blue-50 text-blue-700 ring-blue-600/20' }}">
                                            {{ $entry->entry_type->label() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-slate-500">{{ \App\Support\Money::format($entry->collected_interest) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-end tabular-nums text-slate-500">{{ $entry->applicable_rate !== null ? rtrim(rtrim((string) $entry->applicable_rate, '0'), '.').'%' : '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-end font-semibold tabular-nums {{ $entry->entry_type === DistributionEntryType::Reversal ? 'text-rose-600' : 'text-slate-800' }}">{{ \App\Support\Money::format($entry->signedAmount()) }}</td>
                                    <td class="px-4 py-3"><x-status-badge :status="$entry->status" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-5 py-10 text-center text-xs text-slate-400">{{ __('No distribution entries yet. They are created automatically when an EMI with interest is collected.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
