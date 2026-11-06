<x-layouts::app :title="__('Dashboard')">
    @php
        $collectionChart = [
            'chart' => ['type' => 'area', 'height' => 280],
            'series' => [['name' => __('Collections'), 'data' => $metrics['collection_series']]],
            'xaxis' => ['categories' => $metrics['months']],
            'yaxis' => ['labels' => ['style' => ['colors' => '#94a3b8']]],
            'dataLabels' => ['enabled' => false],
            'stroke' => ['curve' => 'smooth', 'width' => 2.5],
            'fill' => ['type' => 'gradient', 'gradient' => ['shadeIntensity' => 0.35, 'opacityFrom' => 0.35, 'opacityTo' => 0, 'stops' => [0, 90]]],
            'grid' => ['borderColor' => '#e2e8f0', 'strokeDashArray' => 4],
            'colors' => ['#2563eb'],
            'tooltip' => ['theme' => 'light'],
        ];

        $portfolioChart = [
            'chart' => ['type' => 'donut', 'height' => 280],
            'series' => [
                (int) ($metrics['status_mix']['active'] ?? 0),
                (int) ($metrics['status_mix']['pending'] ?? 0),
                (int) ($metrics['status_mix']['closed'] ?? 0),
            ],
            'labels' => [__('Active'), __('Pending'), __('Closed')],
            'colors' => ['#2563eb', '#f59e0b', '#10b981'],
            'legend' => ['position' => 'bottom', 'fontSize' => '12px'],
            'stroke' => ['width' => 0],
            'dataLabels' => ['enabled' => false],
            'plotOptions' => ['pie' => ['donut' => ['size' => '72%', 'labels' => ['show' => true, 'total' => ['show' => true, 'label' => __('Loans'), 'fontSize' => '12px', 'color' => '#64748b']]]]],
        ];
    @endphp

    <div class="mx-auto max-w-[1400px] space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="fc-eyebrow">{{ __('Operations overview') }}</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Good day, :name', ['name' => str(auth()->user()->name)->before(' ')]) }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Here is what is happening across your lending book today.') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('collections.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900">
                    <flux:icon.receipt-percent class="size-4" />
                    {{ __('Record collection') }}
                </a>
                <a href="{{ route('loans.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                    <flux:icon.plus class="size-4" />
                    {{ __('New loan') }}
                </a>
            </div>
        </div>

        @if (($metrics['awaiting_funding_count'] ?? 0) > 0)
            <a href="{{ route('loans.index') }}" class="flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3.5 text-xs text-amber-900 transition hover:border-amber-300">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-100">
                    <flux:icon.lock-open class="size-4 text-amber-700" />
                </span>
                <span class="min-w-0 flex-1 leading-relaxed">
                    {{ __(':count approved loan(s) await investor funding (:amount). Disbursement unlocks only when each loan is 100% funded.', ['count' => $metrics['awaiting_funding_count'], 'amount' => \App\Support\Money::format($metrics['awaiting_funding_amount'])]) }}
                </span>
                <span class="inline-flex shrink-0 items-center gap-1 font-semibold text-amber-800">
                    {{ __('Review loans') }}
                    <flux:icon.arrow-right class="size-4" />
                </span>
            </a>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card
                :label="__('Outstanding portfolio')"
                :value="\App\Support\Money::format($metrics['portfolio_outstanding'])"
                :hint="__(':count active loans', ['count' => $metrics['active_loans']])"
                icon="banknotes"
                tone="blue"
            />
            <x-stat-card
                :label="__('Collected this month')"
                :value="\App\Support\Money::format($metrics['collected_this_month'])"
                :hint="__('Disbursed :amount lifetime', ['amount' => \App\Support\Money::format($metrics['disbursed_total'])])"
                icon="arrow-down-tray"
                tone="emerald"
            />
            <x-stat-card
                :label="__('Due in next 7 days')"
                :value="\App\Support\Money::format($metrics['due_soon_amount'])"
                :hint="__(':count EMIs scheduled', ['count' => $metrics['due_soon_count']])"
                icon="calendar-days"
                tone="amber"
            />
            <x-stat-card
                :label="__('Overdue EMIs')"
                :value="(string) $metrics['overdue_count']"
                :hint="\App\Support\Money::format($metrics['overdue_amount']).' '.__('past due')"
                icon="exclamation-triangle"
                tone="rose"
            />
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="fc-card p-5 sm:p-6 xl:col-span-2">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="fc-eyebrow">{{ __('Cash inflow') }}</p>
                        <h3 class="mt-1 text-base font-semibold">{{ __('Collections — last 6 months') }}</h3>
                    </div>
                    <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-[11px] font-semibold text-blue-700">{{ __('EMI receipts') }}</span>
                </div>
                <div class="mt-4" data-chart="{{ json_encode($collectionChart) }}"></div>
            </div>

            <div class="fc-card p-5 sm:p-6">
                <div>
                    <p class="fc-eyebrow">{{ __('Portfolio health') }}</p>
                    <h3 class="mt-1 text-base font-semibold">{{ __('Loans by status') }}</h3>
                </div>
                <div class="mt-2" data-chart="{{ json_encode($portfolioChart) }}"></div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="fc-card overflow-hidden xl:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <p class="fc-eyebrow">{{ __('Schedule') }}</p>
                        <h3 class="mt-1 text-base font-semibold">{{ __('Upcoming EMIs') }}</h3>
                    </div>
                    <a href="{{ route('collections.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">{{ __('View all') }}</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="fc-table-head">
                            <tr>
                                <th class="px-5 py-3">{{ __('Borrower') }}</th>
                                <th class="px-5 py-3">{{ __('Loan') }}</th>
                                <th class="px-5 py-3">{{ __('Due date') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('Amount') }}</th>
                                <th class="px-5 py-3">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($metrics['upcoming'] as $schedule)
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="whitespace-nowrap px-5 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <flux:avatar :name="$schedule->loan->borrower->full_name" :initials="str($schedule->loan->borrower->full_name)->substr(0, 1)" size="xs" />
                                            <div class="min-w-0">
                                                <p class="truncate font-medium text-slate-800">{{ $schedule->loan->borrower->full_name }}</p>
                                                <p class="text-[11px] text-slate-400">{{ $schedule->loan->borrower->mobile }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <a href="{{ route('loans.show', $schedule->loan) }}" class="font-medium text-blue-600 hover:text-blue-700">{{ $schedule->loan->loan_code }}</a>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $schedule->due_date->format(\App\Support\Regional::dateFormat()) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-end font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($schedule->total_amount) }}</td>
                                    <td class="px-5 py-3"><x-status-badge :status="$schedule->status" /></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-400">{{ __('No upcoming EMIs. Create a loan to build a schedule.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="fc-card p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="fc-eyebrow">{{ __('Ledger') }}</p>
                        <h3 class="mt-1 text-base font-semibold">{{ __('Recent payments') }}</h3>
                    </div>
                    <flux:icon.clock class="size-4 text-slate-300" />
                </div>

                <ul class="mt-4 space-y-3">
                    @forelse ($metrics['recent_payments'] as $payment)
                        <li class="flex items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                <flux:icon.arrow-down-left class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold text-slate-800">{{ $payment->loan->borrower->full_name }}</p>
                                <p class="truncate text-[11px] text-slate-400">{{ $payment->receipt_no }} · {{ $payment->payment_date->format('d M') }}</p>
                            </div>
                            <span class="text-xs font-semibold tabular-nums text-emerald-600">+{{ \App\Support\Money::format($payment->amount) }}</span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-xs text-slate-400">{{ __('No payments recorded yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-layouts::app>
