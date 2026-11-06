<?php

use App\Services\DistributionReportService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Company Earnings')] class extends Component
{
    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->from = CarbonImmutable::today()->startOfYear()->toDateString();
        $this->to = CarbonImmutable::today()->toDateString();
    }

    /**
     * @return array<string, string|int>
     */
    #[Computed]
    public function summary(): array
    {
        return app(DistributionReportService::class)->summary();
    }

    /**
     * @return array<int, array{month: string, collected: string, investor: string, admin: string, company: string, reversed: string}>
     */
    #[Computed]
    public function monthly(): array
    {
        return app(DistributionReportService::class)->monthly(12);
    }

    /**
     * @return array<int, array{loan_id: int, loan_code: string, borrower: string, collected: string, investor: string, admin: string, company: string, reversed: string}>
     */
    #[Computed]
    public function loans(): array
    {
        $from = $this->from !== '' ? CarbonImmutable::parse($this->from)->startOfDay() : null;
        $to = $this->to !== '' ? CarbonImmutable::parse($this->to)->endOfDay() : null;

        return app(DistributionReportService::class)->byLoan($from, $to);
    }
};
?>

<div class="mx-auto max-w-[1500px] space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Distribution') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Company Earnings') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Residual company share of collected EMI interest, after investor and admin allocations.') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <input type="date" wire:model.live="from" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none">
            <input type="date" wire:model.live="to" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none">
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat-card :label="__('Interest collected')" :value="\App\Support\Money::format($this->summary['collected_interest'])" :hint="__('Actual EMI interest only')" icon="receipt-percent" tone="navy" />
        <x-stat-card :label="__('Investor share')" :value="\App\Support\Money::format($this->summary['investor_earned'])" :hint="__('Net of reversals')" icon="building-office-2" tone="blue" />
        <x-stat-card :label="__('Admin share')" :value="\App\Support\Money::format($this->summary['admin_earned'])" :hint="__('Commission entitlements')" icon="user-group" tone="purple" />
        <x-stat-card :label="__('Company earned')" :value="\App\Support\Money::format($this->summary['company_earned'])" :hint="__('Residual share to company')" icon="building-library" tone="emerald" />
        <x-stat-card :label="__('Reversed')" :value="\App\Support\Money::format($this->summary['reversed_interest'])" :hint="__('Compensating entries')" icon="arrow-uturn-left" tone="rose" />
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="fc-card overflow-hidden xl:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <p class="fc-eyebrow">{{ __('Monthly') }}</p>
                    <h3 class="mt-1 text-sm font-semibold">{{ __('Distribution by month') }}</h3>
                </div>
                <span class="text-[11px] text-slate-400">{{ __('Last 12 months') }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="fc-table-head">
                        <tr>
                            <th class="px-5 py-3">{{ __('Month') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('Collected') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('Investor') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('Admin') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('Company') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('Reversed') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->monthly as $row)
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="whitespace-nowrap px-5 py-3 font-medium text-slate-700">{{ $row['month'] }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-slate-600">{{ \App\Support\Money::format($row['collected']) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-blue-700">{{ \App\Support\Money::format($row['investor']) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-violet-700">{{ \App\Support\Money::format($row['admin']) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-end font-semibold tabular-nums text-emerald-700">{{ \App\Support\Money::format($row['company']) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-rose-600">{{ \App\Support\Money::format($row['reversed']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="fc-card p-5">
            <p class="fc-eyebrow">{{ __('Reconciliation') }}</p>
            <h3 class="mt-1 text-sm font-semibold">{{ __('Collected interest split') }}</h3>

            @php
                $collected = (float) $this->summary['collected_interest'];
                $investor = (float) $this->summary['investor_earned'];
                $admin = (float) $this->summary['admin_earned'];
                $company = (float) $this->summary['company_earned'];
                $chart = [
                    'chart' => ['type' => 'donut', 'height' => 260],
                    'series' => [$investor, $admin, $company],
                    'labels' => [__('Investors'), __('Admins'), __('Company')],
                    'colors' => ['#2563eb', '#8b5cf6', '#10b981'],
                    'legend' => ['position' => 'bottom', 'fontSize' => '12px'],
                    'stroke' => ['width' => 0],
                    'dataLabels' => ['enabled' => false],
                    'plotOptions' => ['pie' => ['donut' => ['size' => '68%', 'labels' => ['show' => true, 'total' => ['show' => true, 'label' => __('Collected'), 'fontSize' => '12px', 'color' => '#64748b']]]]],
                    'tooltip' => ['theme' => 'light'],
                ];
            @endphp

            @if ($collected > 0)
                <div class="mt-2" data-chart="{{ json_encode($chart) }}"></div>
            @else
                <div class="mt-4 flex h-[260px] items-center justify-center rounded-xl border border-dashed border-slate-200 text-center">
                    <p class="px-6 text-xs text-slate-400">{{ __('No collected interest has been distributed yet.') }}</p>
                </div>
            @endif

            <div class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-xs">
                <div class="flex items-center justify-between"><span class="text-slate-400">{{ __('Distribution entries') }}</span><span class="font-semibold tabular-nums text-slate-700">{{ $this->summary['distribution_entries'] }}</span></div>
                <div class="flex items-center justify-between"><span class="text-slate-400">{{ __('Reversal entries') }}</span><span class="font-semibold tabular-nums text-rose-600">{{ $this->summary['reversal_entries'] }}</span></div>
                <div class="flex items-center justify-between"><span class="text-slate-400">{{ __('Company share of collected') }}</span><span class="font-semibold tabular-nums text-emerald-700">{{ $collected > 0 ? number_format(($company / $collected) * 100, 2).'%' : '—' }}</span></div>
            </div>
        </div>
    </div>

    <div class="fc-card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <div>
                <p class="fc-eyebrow">{{ __('Loan-wise') }}</p>
                <h3 class="mt-1 text-sm font-semibold">{{ __('Distribution by loan') }}</h3>
            </div>
            <span class="text-[11px] text-slate-400">{{ count($this->loans) }} {{ __('loans') }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="fc-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('Loan') }}</th>
                        <th class="px-5 py-3">{{ __('Borrower') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Collected') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Investor') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Admin') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Company') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Reversed') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Breakdown') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->loans as $row)
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-5 py-3 font-medium text-slate-800">{{ $row['loan_code'] }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $row['borrower'] }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-slate-600">{{ \App\Support\Money::format($row['collected']) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-blue-700">{{ \App\Support\Money::format($row['investor']) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-violet-700">{{ \App\Support\Money::format($row['admin']) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end font-semibold tabular-nums text-emerald-700">{{ \App\Support\Money::format($row['company']) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-rose-600">{{ \App\Support\Money::format($row['reversed']) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end">
                                <a href="{{ route('loans.distribution', $row['loan_id']) }}" class="text-[11px] font-semibold text-blue-600 hover:text-blue-700">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-14 text-center text-sm text-slate-400">{{ __('No distributed interest in the selected period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
