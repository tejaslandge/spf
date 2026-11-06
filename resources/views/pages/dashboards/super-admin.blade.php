<x-layouts::app :title="__('Super Admin Dashboard')">
    @php
        $collectionChart = [
            'chart' => ['type' => 'area', 'height' => 280],
            'series' => [['name' => __('Collections'), 'data' => $metrics['collection_series']]],
            'xaxis' => ['categories' => $metrics['months']],
            'dataLabels' => ['enabled' => false],
            'stroke' => ['curve' => 'smooth', 'width' => 2.5],
            'fill' => ['type' => 'gradient', 'gradient' => ['shadeIntensity' => 0.35, 'opacityFrom' => 0.35, 'opacityTo' => 0, 'stops' => [0, 90]]],
            'grid' => ['borderColor' => '#e2e8f0', 'strokeDashArray' => 4],
            'colors' => ['#0b1b33'],
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
                <p class="fc-eyebrow">{{ __('Super admin control centre') }}</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Capital & portfolio overview') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Monitor investor capital, lending exposure and platform activity.') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('payouts.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900">
                    <flux:icon.currency-dollar class="size-4" />
                    {{ __('Investor payouts') }}
                </a>
                <a href="{{ route('super-admin.users.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                    <flux:icon.user-plus class="size-4" />
                    {{ __('Manage team') }}
                </a>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card
                :label="__('Investor capital')"
                :value="\App\Support\Money::format($metrics['total_capital'])"
                :hint="__(':count active investors', ['count' => $metrics['investors_count']])"
                icon="building-office-2"
                tone="navy"
            />
            <x-stat-card
                :label="__('Outstanding portfolio')"
                :value="\App\Support\Money::format($metrics['portfolio_outstanding'])"
                :hint="__(':count active loans', ['count' => $metrics['active_loans']])"
                icon="banknotes"
                tone="blue"
            />
            <x-stat-card
                :label="__('Interest earned')"
                :value="\App\Support\Money::format($metrics['interest_earned'])"
                :hint="__('Collected lifetime')"
                icon="chart-bar"
                tone="emerald"
            />
            <x-stat-card
                :label="__('Pending payouts')"
                :value="\App\Support\Money::format($metrics['pending_payouts_amount'])"
                :hint="__(':count payouts awaiting release', ['count' => $metrics['pending_payouts_count']])"
                icon="currency-dollar"
                tone="amber"
            />
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card
                :label="__('Collected interest')"
                :value="\App\Support\Money::format($metrics['distribution']['collected_interest'])"
                :hint="__('Actual EMI interest distributed')"
                icon="receipt-percent"
                tone="navy"
            />
            <x-stat-card
                :label="__('Investor earnings')"
                :value="\App\Support\Money::format($metrics['distribution']['investor_earned'])"
                :hint="__('Net of reversals')"
                icon="building-office-2"
                tone="blue"
            />
            <x-stat-card
                :label="__('Admin commissions')"
                :value="\App\Support\Money::format($metrics['distribution']['admin_earned'])"
                :hint="__(':count pending · :amount', ['count' => $metrics['distribution']['admin_pending'], 'amount' => \App\Support\Money::format($metrics['distribution']['admin_approved'])])"
                icon="user-group"
                tone="purple"
            />
            <x-stat-card
                :label="__('Company share')"
                :value="\App\Support\Money::format($metrics['distribution']['company_earned'])"
                :hint="__('Residual collected interest')"
                icon="building-library"
                tone="emerald"
            />
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <div class="fc-card p-5 sm:p-6 xl:col-span-2">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="fc-eyebrow">{{ __('Cash inflow') }}</p>
                        <h3 class="mt-1 text-base font-semibold">{{ __('Collections — last 6 months') }}</h3>
                    </div>
                    <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">{{ __('Platform wide') }}</span>
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

        <div class="grid gap-4 xl:grid-cols-2">
            <div class="fc-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <p class="fc-eyebrow">{{ __('Governance') }}</p>
                        <h3 class="mt-1 text-base font-semibold">{{ __('Recent audit activity') }}</h3>
                    </div>
                    <a href="{{ route('super-admin.audit-logs.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">{{ __('Full log') }}</a>
                </div>

                <ul class="divide-y divide-slate-100">
                    @forelse ($metrics['recent_audits'] as $log)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                <flux:icon.shield-check class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold text-slate-800">
                                    {{ $log->user?->name ?? __('System') }} · {{ ucfirst($log->action) }}
                                </p>
                                <p class="truncate text-[11px] text-slate-400">
                                    {{ class_basename((string) $log->auditable_type) }} #{{ $log->auditable_id }} · {{ $log->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-xs text-slate-400">{{ __('No audit activity recorded yet.') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="fc-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <p class="fc-eyebrow">{{ __('Investor relations') }}</p>
                        <h3 class="mt-1 text-base font-semibold">{{ __('Latest payouts') }}</h3>
                    </div>
                    <a href="{{ route('payouts.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">{{ __('Manage') }}</a>
                </div>

                <ul class="divide-y divide-slate-100">
                    @forelse ($metrics['recent_payouts'] as $payout)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <flux:avatar :name="$payout->investor->name" :initials="str($payout->investor->name)->substr(0, 2)" size="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold text-slate-800">{{ $payout->investor->name }}</p>
                                <p class="truncate text-[11px] text-slate-400">{{ $payout->period_month->format('F Y') }}</p>
                            </div>
                            <div class="text-end">
                                <p class="text-xs font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($payout->net_amount) }}</p>
                                <x-status-badge :status="$payout->status" class="mt-1" />
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-xs text-slate-400">{{ __('No investor payouts generated yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="rounded-2xl border border-cyber-300/60 bg-cyber-100/60 px-5 py-4 text-xs text-amber-900">
            <div class="flex items-start gap-3">
                <flux:icon.information-circle class="mt-0.5 size-4 shrink-0" />
                <p>{{ __('Super Admin access is unrestricted. Every financial action is recorded in the audit trail and journal ledger.') }}</p>
            </div>
        </div>
    </div>
</x-layouts::app>
