<?php

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\EmiPayment;
use App\Models\EmiSchedule;
use App\Models\InvestorPayout;
use App\Models\Loan;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Reports')] class extends Component
{
    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $today = CarbonImmutable::today();

        $period = (string) app(\App\Services\Settings\UserPreferenceService::class)
            ->get(auth()->user(), 'reporting_period', 'current_month');

        [$from, $to] = match ($period) {
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'current_quarter' => [$today->startOfQuarter(), $today],
            'current_financial_year' => [\App\Support\Regional::financialYearStart($today), $today],
            default => [$today->startOfMonth(), $today],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }

    public function updatedFrom(): void
    {
        $this->validateOnly('from', ['from' => ['required', 'date', 'before_or_equal:to']]);
        $this->resetReportCache();
    }

    public function updatedTo(): void
    {
        $this->validateOnly('to', ['to' => ['required', 'date', 'after_or_equal:from']]);
        $this->resetReportCache();
    }

    public function setPreset(string $preset): void
    {
        $today = CarbonImmutable::today();

        [$from, $to] = match ($preset) {
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'last_3_months' => [$today->startOfMonth()->subMonthsNoOverflow(2), $today],
            'this_year' => [$today->startOfYear(), $today],
            default => [$today->startOfMonth()->subMonthsNoOverflow(5), $today],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
        $this->resetReportCache();
    }

    public function exportCsv(): StreamedResponse
    {
        [$from, $to] = $this->period;

        $payments = EmiPayment::query()
            ->with('loan.borrower')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        return response()->streamDownload(function () use ($payments): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Receipt', 'Payment date', 'Loan', 'Borrower', 'Amount',
                'Principal', 'Interest', 'Penalty', 'Method', 'Reference',
            ], ',', '"', '');

            foreach ($payments as $payment) {
                fputcsv($handle, [
                    $payment->receipt_no,
                    $payment->payment_date->format('Y-m-d'),
                    $payment->loan->loan_code,
                    $payment->loan->borrower->full_name,
                    $payment->amount,
                    $payment->principal_component,
                    $payment->interest_component,
                    $payment->penalty_amount,
                    $payment->payment_method->label(),
                    $payment->reference,
                ], ',', '"', '');
            }

            fclose($handle);
        }, 'collections-'.$from->toDateString().'-to-'.$to->toDateString().'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    #[Computed]
    public function period(): array
    {
        $from = CarbonImmutable::parse($this->from ?: now()->startOfMonth()->toDateString())->startOfDay();
        $to = CarbonImmutable::parse($this->to ?: now()->toDateString())->endOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function summary(): array
    {
        [$from, $to] = $this->period;

        $payments = EmiPayment::query()
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()]);

        $overdue = EmiSchedule::query()->overdue();
        $currentMonth = CarbonImmutable::today();
        $expectedCollection = EmiSchedule::query()
            ->whereBetween('due_date', [$currentMonth->startOfMonth()->toDateString(), $currentMonth->endOfMonth()->toDateString()])
            ->sum('total_amount');

        return [
            'collected' => (string) (clone $payments)->sum('amount'),
            'principal' => (string) (clone $payments)->sum('principal_component'),
            'interest' => (string) (clone $payments)->sum('interest_component'),
            'penalty' => (string) (clone $payments)->sum('penalty_amount'),
            'receipts' => (clone $payments)->count(),
            'overdue_count' => (clone $overdue)->count(),
            'overdue_amount' => (string) (clone $overdue)->sum('total_amount'),
            'active_loans' => Loan::query()->where('status', LoanStatus::Active)->count(),
            'outstanding' => (string) Loan::query()->where('status', LoanStatus::Active)->sum('outstanding_principal'),
            'disbursed' => (string) Loan::query()->whereNotNull('disbursement_date')->sum('principal_amount'),
            'expected_collection' => (string) $expectedCollection,
        ];
    }

    /**
     * @return array{labels: array<int, string>, collections: array<int, float>, interest: array<int, float>}
     */
    #[Computed]
    public function monthly(): array
    {
        [$from, $to] = $this->period;

        $cursor = $from->startOfMonth();
        $last = $to->startOfMonth();

        $labels = [];
        $collections = [];
        $interest = [];
        $guard = 0;

        while ($cursor->lessThanOrEqualTo($last) && $guard < 24) {
            $monthPayments = EmiPayment::query()
                ->whereBetween('payment_date', [$cursor->startOfMonth()->toDateString(), $cursor->endOfMonth()->toDateString()]);

            $labels[] = $cursor->format('M y');
            $collections[] = (float) (clone $monthPayments)->sum('amount');
            $interest[] = (float) (clone $monthPayments)->sum('interest_component');

            $cursor = $cursor->addMonthNoOverflow();
            $guard++;
        }

        return [
            'labels' => $labels,
            'collections' => $collections,
            'interest' => $interest,
        ];
    }

    /**
     * @return array<string, float>
     */
    #[Computed]
    public function paymentMix(): array
    {
        [$from, $to] = $this->period;

        return EmiPayment::query()
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method')
            ->mapWithKeys(fn ($total, $method) => [
                PaymentMethod::tryFrom((string) $method)?->label() ?? (string) $method => (float) $total,
            ])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function loanMix(): array
    {
        return Loan::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, EmiSchedule>
     */
    #[Computed]
    public function overdueSchedules()
    {
        return EmiSchedule::query()
            ->overdue()
            ->with('loan.borrower')
            ->orderBy('due_date')
            ->limit(10)
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, EmiPayment>
     */
    #[Computed]
    public function receipts()
    {
        [$from, $to] = $this->period;

        return EmiPayment::query()
            ->with(['loan.borrower'])
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->latest('payment_date')
            ->latest('id')
            ->limit(10)
            ->get();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function investorSummary(): array
    {
        [$from, $to] = $this->period;

        $payouts = InvestorPayout::query()
            ->whereBetween('period_month', [$from->startOfMonth()->toDateString(), $to->endOfMonth()->toDateString()]);

        return [
            'gross' => (string) (clone $payouts)->sum('gross_interest'),
            'fee' => (string) (clone $payouts)->sum('management_fee'),
            'tds' => (string) (clone $payouts)->sum('tds_amount'),
            'net' => (string) (clone $payouts)->sum('net_amount'),
            'pending' => (string) (clone $payouts)->where('status', 'pending')->sum('net_amount'),
            'paid' => (string) (clone $payouts)->where('status', 'paid')->sum('net_amount'),
            'count' => (string) (clone $payouts)->count(),
        ];
    }

    private function resetReportCache(): void
    {
        unset(
            $this->period,
            $this->summary,
            $this->monthly,
            $this->paymentMix,
            $this->loanMix,
            $this->overdueSchedules,
            $this->receipts,
            $this->investorSummary,
        );
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Insights') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Reports') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Collections, portfolio health and investor payouts for the selected period.') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="setPreset('this_month')" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-[11px] font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900">
                {{ __('This month') }}
            </button>
            <button type="button" wire:click="setPreset('last_3_months')" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-[11px] font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900">
                {{ __('Last 3 months') }}
            </button>
            <button type="button" wire:click="setPreset('this_year')" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-[11px] font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900">
                {{ __('This year') }}
            </button>
            <button type="button" wire:click="exportCsv" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                <flux:icon.arrow-down-tray class="size-4" />
                {{ __('Export CSV') }}
            </button>
        </div>
    </div>

    <div class="fc-card p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[220px_220px_1fr] lg:items-end">
            <flux:input wire:model.live="from" type="date" :label="__('From')" />
            <flux:input wire:model.live="to" type="date" :label="__('To')" />

            <div class="flex flex-wrap items-center justify-start gap-2 lg:justify-end">
                <p class="text-xs text-slate-400">
                    {{ __('Showing :from to :to', ['from' => $this->period[0]->format(\App\Support\Regional::dateFormat()), 'to' => $this->period[1]->format(\App\Support\Regional::dateFormat())]) }}
                </p>
            </div>
        </div>

        @error('from')
            <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
        @enderror
        @error('to')
            <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat-card :label="__('Collections')" :value="\App\Support\Money::format($this->summary['collected'])" :hint="__(':count receipts in period', ['count' => $this->summary['receipts']])" icon="banknotes" tone="blue" />
        <x-stat-card :label="__('Interest earned')" :value="\App\Support\Money::format($this->summary['interest'])" :hint="__('Recognised on collections')" icon="chart-bar" tone="emerald" />
        <x-stat-card :label="__('Principal recovered')" :value="\App\Support\Money::format($this->summary['principal'])" :hint="__('Against disbursed :amount', ['amount' => \App\Support\Money::format($this->summary['disbursed'])])" icon="arrow-uturn-left" tone="navy" />
        <x-stat-card :label="__('Expected Collection (Monthly)')" :value="\App\Support\Money::format($this->summary['expected_collection'])" :hint="__('EMI schedules due this month')" icon="calendar-days" tone="amber" />
        <x-stat-card :label="__('Overdue EMIs')" :value="(string) $this->summary['overdue_count']" :hint="\App\Support\Money::format($this->summary['overdue_amount']).' '.__('past due')" icon="exclamation-triangle" tone="rose" />
    </div>

    @php
        $cashflowChart = [
            'chart' => ['type' => 'bar', 'height' => 300],
            'series' => [
                ['name' => __('Collections'), 'data' => $this->monthly['collections']],
                ['name' => __('Interest'), 'data' => $this->monthly['interest']],
            ],
            'xaxis' => ['categories' => $this->monthly['labels'], 'labels' => ['style' => ['colors' => '#94a3b8', 'fontSize' => '11px']]],
            'yaxis' => ['labels' => ['style' => ['colors' => '#94a3b8']]],
            'plotOptions' => ['bar' => ['columnWidth' => '45%', 'borderRadius' => 5, 'borderRadiusApplication' => 'end']],
            'dataLabels' => ['enabled' => false],
            'grid' => ['borderColor' => '#e2e8f0', 'strokeDashArray' => 4],
            'colors' => ['#2563eb', '#10b981'],
            'legend' => ['position' => 'top', 'horizontalAlign' => 'right', 'fontSize' => '12px'],
            'tooltip' => ['theme' => 'light'],
        ];

        $paymentMix = $this->paymentMix;

        $paymentMixChart = $paymentMix === [] ? null : [
            'chart' => ['type' => 'donut', 'height' => 300],
            'series' => array_values($paymentMix),
            'labels' => array_keys($paymentMix),
            'colors' => ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#f43f5e'],
            'legend' => ['position' => 'bottom', 'fontSize' => '12px'],
            'stroke' => ['width' => 0],
            'dataLabels' => ['enabled' => false],
            'plotOptions' => ['pie' => ['donut' => ['size' => '70%', 'labels' => ['show' => true, 'total' => ['show' => true, 'label' => __('Collected'), 'fontSize' => '12px', 'color' => '#64748b']]]]],
            'tooltip' => ['theme' => 'light'],
        ];
    @endphp

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="fc-card p-5 sm:p-6 xl:col-span-2">
            <div class="flex items-center justify-between">
                <div>
                    <p class="fc-eyebrow">{{ __('Cash inflow') }}</p>
                    <h3 class="mt-1 text-base font-semibold">{{ __('Collections vs interest') }}</h3>
                </div>
                <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-700">{{ __('Monthly') }}</span>
            </div>
            <div class="mt-4" data-chart="{{ json_encode($cashflowChart) }}"></div>
        </div>

        <div class="fc-card p-5 sm:p-6">
            <div>
                <p class="fc-eyebrow">{{ __('Channels') }}</p>
                <h3 class="mt-1 text-base font-semibold">{{ __('Collections by method') }}</h3>
            </div>

            @if ($paymentMixChart)
                <div class="mt-2" data-chart="{{ json_encode($paymentMixChart) }}"></div>
            @else
                <div class="mt-4 flex h-[260px] items-center justify-center rounded-xl border border-dashed border-slate-200 text-center">
                    <p class="px-6 text-xs text-slate-400">{{ __('No collections recorded in this period.') }}</p>
                </div>
            @endif
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="fc-card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <p class="fc-eyebrow">{{ __('Loan book') }}</p>
                    <h3 class="mt-1 text-base font-semibold">{{ __('Portfolio by status') }}</h3>
                </div>
                <a href="{{ route('loans.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">{{ __('View loans') }}</a>
            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Active loans') }}</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900">{{ $this->summary['active_loans'] }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Outstanding principal') }}</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900">{{ \App\Support\Money::format($this->summary['outstanding']) }}</p>
                </div>

                @foreach (\App\Enums\LoanStatus::cases() as $status)
                    <div class="flex items-center justify-between rounded-xl border border-slate-100 px-4 py-3">
                        <x-status-badge :status="$status" />
                        <span class="text-sm font-semibold tabular-nums text-slate-800">{{ $this->loanMix[$status->value] ?? 0 }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="fc-card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <p class="fc-eyebrow">{{ __('Capital') }}</p>
                    <h3 class="mt-1 text-base font-semibold">{{ __('Investor payouts') }}</h3>
                </div>
                <a href="{{ route('payouts.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">{{ __('Manage payouts') }}</a>
            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Gross interest') }}</p>
                    <p class="mt-1 text-base font-semibold tabular-nums text-slate-900">{{ \App\Support\Money::format($this->investorSummary['gross']) }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Management fee') }}</p>
                    <p class="mt-1 text-base font-semibold tabular-nums text-slate-900">{{ \App\Support\Money::format($this->investorSummary['fee']) }}</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('TDS withheld') }}</p>
                    <p class="mt-1 text-base font-semibold tabular-nums text-slate-900">{{ \App\Support\Money::format($this->investorSummary['tds']) }}</p>
                </div>
                <div class="rounded-xl bg-emerald-50 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">{{ __('Net payable') }}</p>
                    <p class="mt-1 text-base font-semibold tabular-nums text-emerald-700">{{ \App\Support\Money::format($this->investorSummary['net']) }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-4 border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                <span>{{ __(':count payouts generated', ['count' => $this->investorSummary['count']]) }}</span>
                <span class="text-amber-600">{{ __('Pending :amount', ['amount' => \App\Support\Money::format($this->investorSummary['pending'])]) }}</span>
                <span class="text-emerald-600">{{ __('Paid :amount', ['amount' => \App\Support\Money::format($this->investorSummary['paid'])]) }}</span>
            </div>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="fc-card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <p class="fc-eyebrow">{{ __('Attention') }}</p>
                    <h3 class="mt-1 text-base font-semibold">{{ __('Overdue EMIs') }}</h3>
                </div>
                <a href="{{ route('collections.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">{{ __('Collections') }}</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="fc-table-head">
                        <tr>
                            <th class="px-5 py-3">{{ __('Borrower') }}</th>
                            <th class="px-5 py-3">{{ __('Due') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('Amount') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('Days') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->overdueSchedules as $schedule)
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="px-5 py-3">
                                    <a href="{{ route('loans.show', $schedule->loan) }}" class="font-medium text-slate-800 hover:text-blue-600">{{ $schedule->loan->borrower->full_name }}</a>
                                    <p class="text-[11px] text-slate-400">{{ $schedule->loan->loan_code }} · #{{ $schedule->installment_no }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $schedule->due_date->format(\App\Support\Regional::dateFormat()) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-end font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($schedule->total_amount) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-rose-600">{{ (int) $schedule->due_date->diffInDays(today()) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-12 text-center text-xs text-slate-400">{{ __('No overdue EMIs.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="fc-card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <p class="fc-eyebrow">{{ __('Receipts') }}</p>
                    <h3 class="mt-1 text-base font-semibold">{{ __('Recent collections') }}</h3>
                </div>
                <button type="button" wire:click="exportCsv" class="text-xs font-semibold text-blue-600 hover:text-blue-700">{{ __('Download CSV') }}</button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="fc-table-head">
                        <tr>
                            <th class="px-5 py-3">{{ __('Receipt') }}</th>
                            <th class="px-5 py-3">{{ __('Borrower') }}</th>
                            <th class="px-5 py-3">{{ __('Date') }}</th>
                            <th class="px-5 py-3">{{ __('Method') }}</th>
                            <th class="px-5 py-3 text-end">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->receipts as $payment)
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="whitespace-nowrap px-5 py-3 font-medium text-slate-800">{{ $payment->receipt_no }}</td>
                                <td class="px-5 py-3">
                                    <a href="{{ route('loans.show', $payment->loan) }}" class="font-medium text-slate-800 hover:text-blue-600">{{ $payment->loan->borrower->full_name }}</a>
                                    <p class="text-[11px] text-slate-400">{{ $payment->loan->loan_code }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $payment->payment_date->format(\App\Support\Regional::dateFormat()) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $payment->payment_method->label() }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-end font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($payment->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-12 text-center text-xs text-slate-400">{{ __('No collections in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
