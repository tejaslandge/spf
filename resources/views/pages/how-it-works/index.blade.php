<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('How it works')] class extends Component
{
    /**
     * The end-to-end loan journey, grouped by operating phase.
     *
     * @return array<string, array<int, array{title: string, owner: string, description: string, route: string, link: string}>>
     */
    public function journey(): array
    {
        return [
            'Origination' => [
                [
                    'title' => 'Loan application',
                    'owner' => 'Admin',
                    'description' => 'Onboard the borrower, collect KYC and record the requested amount, tenure, purpose and income. The application opens the loan record everything else is built on.',
                    'route' => 'loans.create',
                    'link' => 'Start a loan',
                ],
                [
                    'title' => 'Loan offer',
                    'owner' => 'Admin',
                    'description' => 'Calculate flat and reducing balance options side by side, add processing fees or other charges, and issue a formal offer with the exact instalment amount.',
                    'route' => 'loans.index',
                    'link' => 'Open loans',
                ],
                [
                    'title' => 'Borrower acceptance',
                    'owner' => 'Borrower',
                    'description' => 'Confirm the borrower has accepted the terms. The offer is frozen as an immutable snapshot so later rate changes can never alter an agreed loan.',
                    'route' => 'loans.index',
                    'link' => 'Open loans',
                ],
            ],
            'Capital' => [
                [
                    'title' => 'Investor capital verification',
                    'owner' => 'Admin',
                    'description' => 'Compare cleared investor deposits with the approved amount. Pending or rejected deposits never count, so the company only lends against money that has actually arrived.',
                    'route' => 'loans.index',
                    'link' => 'Open loans',
                ],
                [
                    'title' => 'Funding confirmation',
                    'owner' => 'Admin / System',
                    'description' => 'Reserve the approved amount from the common available capital pool (cleared deposits net of active allocations). No investor is selected and no investor is linked to the borrower — capital is drawn from the pool exactly, and the loan locks at 100%.',
                    'route' => 'loans.index',
                    'link' => 'Open loans',
                ],
            ],
            'Execution' => [
                [
                    'title' => 'Final approval',
                    'owner' => 'Super Admin / Admin',
                    'description' => 'Review the borrower, participating investors, loan agreement and disbursement bank account, then authorise disbursement as an explicit, audited decision.',
                    'route' => 'loans.index',
                    'link' => 'Open loans',
                ],
                [
                    'title' => 'Disbursement',
                    'owner' => 'Admin',
                    'description' => 'Pay the borrower and record the disbursement date, amount and bank reference. The loan activates and the full EMI schedule is generated.',
                    'route' => 'loans.index',
                    'link' => 'Open loans',
                ],
                [
                    'title' => 'EMI collection',
                    'owner' => 'Admin',
                    'description' => 'Collect full, partial or late instalments with a payment reference. Each payment clears principal first, then interest, and posts to the ledger.',
                    'route' => 'collections.index',
                    'link' => 'Open collections',
                ],
            ],
            'Control' => [
                [
                    'title' => 'Reconciliation',
                    'owner' => 'Admin / System',
                    'description' => 'Match collected principal and interest to investor allocations. Investor payouts use only interest actually collected, never scheduled or hypothetical interest.',
                    'route' => 'collections.index',
                    'link' => 'Open collections',
                ],
                [
                    'title' => 'Loan closure',
                    'owner' => 'System / Admin',
                    'description' => 'Once every instalment is settled and outstanding principal is zero, close the loan, release investor exposure and archive the full history.',
                    'route' => 'loans.index',
                    'link' => 'Open loans',
                ],
            ],
        ];
    }

    /**
     * Every operational module, filtered by the signed-in role.
     *
     * @return array<int, array{title: string, route: string, icon: string, description: string, tone: string, super_admin_only: bool}>
     */
    public function modules(): array
    {
        $modules = [
            [
                'title' => 'Borrowers',
                'route' => 'borrowers.index',
                'icon' => 'users',
                'description' => 'KYC, contacts, income, bank details, guarantors and documents.',
                'tone' => 'blue',
                'super_admin_only' => false,
            ],
            [
                'title' => 'Loans',
                'route' => 'loans.index',
                'icon' => 'banknotes',
                'description' => 'Applications, offers, funding, disbursement and closure.',
                'tone' => 'navy',
                'super_admin_only' => false,
            ],
            [
                'title' => 'EMI Collections',
                'route' => 'collections.index',
                'icon' => 'receipt-percent',
                'description' => 'Payments, part-payments, overdue follow-ups and receipts.',
                'tone' => 'emerald',
                'super_admin_only' => false,
            ],
            [
                'title' => 'Daily Operations',
                'route' => 'operations.index',
                'icon' => 'clipboard-document-check',
                'description' => 'Today’s reminders, KYC reviews and collection follow-ups.',
                'tone' => 'amber',
                'super_admin_only' => false,
            ],
            [
                'title' => 'Investors',
                'route' => 'investors.index',
                'icon' => 'building-office-2',
                'description' => 'KYC, deposits, available capital and loan exposure.',
                'tone' => 'cyan',
                'super_admin_only' => false,
            ],
            [
                'title' => 'Interest Payouts',
                'route' => 'payouts.index',
                'icon' => 'currency-dollar',
                'description' => 'Gross interest, management fee, TDS and net payouts.',
                'tone' => 'violet',
                'super_admin_only' => false,
            ],
            [
                'title' => 'Reports',
                'route' => 'reports.index',
                'icon' => 'chart-bar-square',
                'description' => 'Collections, portfolio health and investor reporting.',
                'tone' => 'blue',
                'super_admin_only' => false,
            ],
            [
                'title' => 'Audit Logs',
                'route' => 'super-admin.audit-logs.index',
                'icon' => 'shield-check',
                'description' => 'Who changed what, when, and with which values.',
                'tone' => 'rose',
                'super_admin_only' => true,
            ],
            [
                'title' => 'Team & Access',
                'route' => 'super-admin.users.index',
                'icon' => 'user-group',
                'description' => 'Create users, assign roles and control access.',
                'tone' => 'slate',
                'super_admin_only' => true,
            ],
        ];

        return array_values(array_filter(
            $modules,
            fn (array $module): bool => ! $module['super_admin_only'] || auth()->user()->isSuperAdmin(),
        ));
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-6">
    {{-- Hero --}}
    <section class="relative overflow-hidden rounded-3xl bg-navy-950 px-6 py-8 text-white shadow-xl shadow-navy-950/20 sm:px-8 lg:px-10 lg:py-10">
        <div class="pointer-events-none absolute -right-24 -top-28 size-80 rounded-full bg-cyber-400/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 left-1/3 size-80 rounded-full bg-blue-500/10 blur-3xl"></div>

        <div class="relative grid gap-8 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] lg:items-center">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.06] px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-cyber-300">
                    <flux:icon.book-open class="size-3.5" />
                    {{ __('Product guide') }}
                </span>
                <h1 class="mt-4 max-w-2xl text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ __('How the platform moves money from investor to borrower — and back.') }}
                </h1>
                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-white/60">
                    {{ __('One workflow connects borrower KYC, loan offers, verified investor capital, disbursement, EMI collection, payouts and the general ledger. Follow the guide below to see exactly what happens at each step.') }}
                </p>

                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="{{ route('loans.index') }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl bg-cyber-400 px-4 py-2.5 text-xs font-semibold text-navy-950 shadow-lg shadow-black/20 transition hover:bg-cyber-300">
                        <flux:icon.banknotes class="size-4" />
                        {{ __('Open loan pipeline') }}
                    </a>
                    <a href="{{ route('loans.create') }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/[0.06] px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-white/[0.1]">
                        <flux:icon.plus class="size-4" />
                        {{ __('Start a loan') }}
                    </a>
                    <a href="{{ route('operations.index') }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/[0.06] px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-white/[0.1]">
                        <flux:icon.clipboard-document-check class="size-4" />
                        {{ __('Today’s operations') }}
                    </a>
                </div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-5 backdrop-blur">
                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-white/40">{{ __('The money path') }}</p>
                <ol class="mt-4 space-y-3 text-sm">
                    @foreach ([
                        ['label' => 'Investor deposits clear', 'icon' => 'arrow-down-tray'],
                        ['label' => 'Capital is verified and allocated', 'icon' => 'shield-check'],
                        ['label' => 'Borrower receives the loan', 'icon' => 'banknotes'],
                        ['label' => 'EMIs are collected and posted', 'icon' => 'receipt-percent'],
                        ['label' => 'Investors are paid their share', 'icon' => 'currency-dollar'],
                    ] as $item)
                        <li class="flex items-center gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/[0.07] text-cyber-300 ring-1 ring-white/10">
                                <x-dynamic-component :component="'flux::icon.'.$item['icon']" class="size-4" />
                            </span>
                            <span class="text-white/75">{{ __($item['label']) }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    {{-- Facts strip --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Workflow steps', 'value' => '11', 'hint' => 'Application to closure', 'icon' => 'arrow-path', 'tone' => 'blue'],
            ['label' => 'Roles', 'value' => '2', 'hint' => 'Super Admin and Admin', 'icon' => 'user-group', 'tone' => 'navy'],
            ['label' => 'Funding gate', 'value' => '100%', 'hint' => 'Cleared capital only', 'icon' => 'lock-closed', 'tone' => 'emerald'],
            ['label' => 'Money precision', 'value' => '2 dp', 'hint' => 'bcmath, never floats', 'icon' => 'calculator', 'tone' => 'amber'],
        ] as $fact)
            <x-stat-card :label="__($fact['label'])" :value="$fact['value']" :hint="__($fact['hint'])" :icon="$fact['icon']" :tone="$fact['tone']" />
        @endforeach
    </section>

    {{-- Animated data flow --}}
    <section class="fc-card overflow-hidden">
        <div class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="fc-eyebrow">{{ __('Data flow') }}</p>
                <h2 class="mt-1 text-lg font-semibold tracking-tight">{{ __('How data and money move through the system') }}</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">
                    {{ __('Blue is borrower origination, emerald is funding and execution, and amber is investor money and controls. Arrows show the order of operations; the dashed amber path shows payouts returning to investors.') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2 text-[11px] font-medium">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">
                    <span class="size-2 rounded-full bg-blue-600"></span>
                    {{ __('Origination') }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700">
                    <span class="size-2 rounded-full bg-emerald-500"></span>
                    {{ __('Funding & execution') }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-amber-700">
                    <span class="size-2 rounded-full bg-amber-500"></span>
                    {{ __('Investor money & controls') }}
                </span>
            </div>
        </div>

        <div class="overflow-x-auto bg-gradient-to-b from-slate-50/80 to-white p-4 sm:p-6">
            <svg viewBox="0 0 960 600" role="img" aria-labelledby="hiw-flow-title hiw-flow-desc" class="h-auto w-full min-w-[820px]">
                <title id="hiw-flow-title">{{ __('Finance and capital data flow') }}</title>
                <desc id="hiw-flow-desc">{{ __('Borrower KYC and application flow into offers and capital verification. Cleared investor deposits fund the loan through allocation, approval and disbursement. EMI collections feed investor payouts, the general ledger, reports and daily operations.') }}</desc>

                <defs>
                    <marker id="hiw-arrow-blue" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                        <path d="M0,0 L10,5 L0,10 z" fill="#2563eb" />
                    </marker>
                    <marker id="hiw-arrow-emerald" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                        <path d="M0,0 L10,5 L0,10 z" fill="#10b981" />
                    </marker>
                    <marker id="hiw-arrow-amber" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                        <path d="M0,0 L10,5 L0,10 z" fill="#f59e0b" />
                    </marker>
                </defs>

                <style>
                    .hiw-phase-label { fill: #94a3b8; font-size: 10px; font-weight: 700; letter-spacing: 0.14em; }
                    .hiw-node-title { fill: #0f172a; font-size: 12px; font-weight: 600; }
                    .hiw-node-sub { fill: #64748b; font-size: 10px; }
                    .hiw-node { fill: #ffffff; stroke: #e2e8f0; stroke-width: 1; }
                    .hiw-flow { fill: none; stroke-width: 2; stroke-linecap: round; stroke-dasharray: 7 7; animation: hiw-dash 1.4s linear infinite; }
                    .hiw-flow-blue { stroke: #2563eb; marker-end: url(#hiw-arrow-blue); }
                    .hiw-flow-emerald { stroke: #10b981; marker-end: url(#hiw-arrow-emerald); }
                    .hiw-flow-amber { stroke: #f59e0b; marker-end: url(#hiw-arrow-amber); }
                    .hiw-dot { transform-box: fill-box; transform-origin: center; animation: hiw-pulse 2.8s ease-in-out infinite; }
                    .hiw-return-label { fill: #b45309; font-size: 10px; font-weight: 600; letter-spacing: 0.04em; }

                    @keyframes hiw-dash {
                        to { stroke-dashoffset: -28; }
                    }

                    @keyframes hiw-pulse {
                        0%, 100% { opacity: 0.35; transform: scale(0.85); }
                        50% { opacity: 1; transform: scale(1.2); }
                    }

                    @media (prefers-reduced-motion: reduce) {
                        .hiw-flow { animation: none; stroke-dasharray: none; }
                        .hiw-dot { animation: none; opacity: 1; transform: none; }
                    }
                </style>

                <text x="30" y="84" class="hiw-phase-label">{{ __('ORIGINATION') }}</text>
                <text x="30" y="284" class="hiw-phase-label">{{ __('FUNDING & EXECUTION') }}</text>
                <text x="30" y="484" class="hiw-phase-label">{{ __('SERVICING & CONTROLS') }}</text>

                {{-- Investor capital source --}}
                <g>
                    <rect x="750" y="8" width="180" height="60" rx="14" class="hiw-node" stroke="#fcd34d" />
                    <rect x="762" y="24" width="4" height="28" rx="2" fill="#f59e0b" />
                    <text x="776" y="30" class="hiw-node-title">{{ __('Investors') }}</text>
                    <text x="776" y="48" class="hiw-node-sub">{{ __('Cleared deposits only') }}</text>
                    <circle cx="906" cy="24" r="3.5" fill="#f59e0b" class="hiw-dot" style="animation-delay: 0.2s" />
                </g>

                {{-- Row 1: origination --}}
                @foreach ([
                    ['x' => 30, 'title' => 'Borrower & KYC', 'sub' => 'Identity and documents', 'color' => '#2563eb', 'delay' => '0s'],
                    ['x' => 270, 'title' => 'Loan application', 'sub' => 'Amount, tenure, purpose', 'color' => '#2563eb', 'delay' => '0.3s'],
                    ['x' => 510, 'title' => 'Offer & acceptance', 'sub' => 'Flat or reducing terms', 'color' => '#2563eb', 'delay' => '0.6s'],
                    ['x' => 750, 'title' => 'Capital verification', 'sub' => 'Cleared deposits only', 'color' => '#10b981', 'delay' => '0.9s'],
                ] as $node)
                    <g>
                        <rect x="{{ $node['x'] }}" y="96" width="180" height="76" rx="14" class="hiw-node" />
                        <rect x="{{ $node['x'] + 12 }}" y="112" width="4" height="44" rx="2" fill="{{ $node['color'] }}" />
                        <text x="{{ $node['x'] + 26 }}" y="126" class="hiw-node-title">{{ __($node['title']) }}</text>
                        <text x="{{ $node['x'] + 26 }}" y="146" class="hiw-node-sub">{{ __($node['sub']) }}</text>
                        <circle cx="{{ $node['x'] + 162 }}" cy="110" r="3.5" fill="{{ $node['color'] }}" class="hiw-dot" style="animation-delay: {{ $node['delay'] }}" />
                    </g>
                @endforeach

                {{-- Row 2: funding and execution --}}
                @foreach ([
                    ['x' => 750, 'title' => 'Funding confirmation', 'sub' => 'Pool reserved at 100%', 'delay' => '1.2s'],
                    ['x' => 510, 'title' => 'Final approval', 'sub' => 'Audited sign-off', 'delay' => '1.5s'],
                    ['x' => 270, 'title' => 'Disbursement', 'sub' => 'Bank reference recorded', 'delay' => '1.8s'],
                    ['x' => 30, 'title' => 'EMI collections', 'sub' => 'Principal first', 'delay' => '2.1s'],
                ] as $node)
                    <g>
                        <rect x="{{ $node['x'] }}" y="296" width="180" height="76" rx="14" class="hiw-node" />
                        <rect x="{{ $node['x'] + 12 }}" y="312" width="4" height="44" rx="2" fill="#10b981" />
                        <text x="{{ $node['x'] + 26 }}" y="326" class="hiw-node-title">{{ __($node['title']) }}</text>
                        <text x="{{ $node['x'] + 26 }}" y="346" class="hiw-node-sub">{{ __($node['sub']) }}</text>
                        <circle cx="{{ $node['x'] + 162 }}" cy="310" r="3.5" fill="#10b981" class="hiw-dot" style="animation-delay: {{ $node['delay'] }}" />
                    </g>
                @endforeach

                {{-- Row 3: servicing and controls --}}
                @foreach ([
                    ['x' => 30, 'title' => 'Investor payouts', 'sub' => 'Fee + TDS, then net', 'delay' => '2.4s'],
                    ['x' => 270, 'title' => 'General ledger', 'sub' => 'Balanced double entry', 'delay' => '2.7s'],
                    ['x' => 510, 'title' => 'Reports & insights', 'sub' => 'Collections & exposure', 'delay' => '3s'],
                    ['x' => 750, 'title' => 'Daily ops & audit', 'sub' => 'Follow-ups & audit trail', 'delay' => '3.3s'],
                ] as $node)
                    <g>
                        <rect x="{{ $node['x'] }}" y="496" width="180" height="76" rx="14" class="hiw-node" />
                        <rect x="{{ $node['x'] + 12 }}" y="512" width="4" height="44" rx="2" fill="#f59e0b" />
                        <text x="{{ $node['x'] + 26 }}" y="526" class="hiw-node-title">{{ __($node['title']) }}</text>
                        <text x="{{ $node['x'] + 26 }}" y="546" class="hiw-node-sub">{{ __($node['sub']) }}</text>
                        <circle cx="{{ $node['x'] + 162 }}" cy="510" r="3.5" fill="#f59e0b" class="hiw-dot" style="animation-delay: {{ $node['delay'] }}" />
                    </g>
                @endforeach

                {{-- Flows --}}
                <path d="M210 134 H262" class="hiw-flow hiw-flow-blue" />
                <path d="M450 134 H502" class="hiw-flow hiw-flow-blue" />
                <path d="M690 134 H742" class="hiw-flow hiw-flow-blue" />
                <path d="M840 68 V88" class="hiw-flow hiw-flow-amber" />
                <path d="M840 172 V288" class="hiw-flow hiw-flow-emerald" />
                <path d="M750 334 H698" class="hiw-flow hiw-flow-emerald" />
                <path d="M510 334 H458" class="hiw-flow hiw-flow-emerald" />
                <path d="M270 334 H218" class="hiw-flow hiw-flow-emerald" />
                <path d="M120 372 V488" class="hiw-flow hiw-flow-emerald" />
                <path d="M210 534 H262" class="hiw-flow hiw-flow-amber" />
                <path d="M450 534 H502" class="hiw-flow hiw-flow-amber" />
                <path d="M690 534 H742" class="hiw-flow hiw-flow-amber" />

                {{-- Returns loop --}}
                <path d="M30 534 H12 V48 H742" class="hiw-flow hiw-flow-amber" />
                <text x="380" y="38" text-anchor="middle" class="hiw-return-label">{{ __('Net payouts return to investors') }}</text>
            </svg>
        </div>

        <p class="border-t border-slate-100 bg-slate-50/60 px-5 py-3 text-xs text-slate-400 lg:hidden">
            {{ __('Swipe horizontally to explore the complete flow.') }}
        </p>
    </section>

    {{-- Roles --}}
    <section class="grid gap-4 lg:grid-cols-2">
        <div class="fc-card p-5 sm:p-6">
            <div class="flex items-center gap-3">
                <span class="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 ring-1 ring-blue-600/10">
                    <flux:icon.user class="size-5" />
                </span>
                <div>
                    <h2 class="text-base font-semibold">{{ __('Admin') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Runs the day-to-day lending operation.') }}</p>
                </div>
            </div>

            <ul class="mt-5 space-y-2.5 text-sm text-slate-600">
                @foreach ([
                    'Onboard borrowers, upload KYC and maintain guarantors.',
                    'Create loan applications, calculate offers and record acceptance.',
                    'Verify investor capital, allocate funding and record disbursement.',
                    'Collect EMIs, follow up on overdue accounts and reconcile collections.',
                    'Onboard investors, record deposits and pay monthly interest.',
                    'Generate reports, export CSV and work the daily operations queue.',
                ] as $capability)
                    <li class="flex gap-2.5">
                        <flux:icon.check-circle class="mt-0.5 size-4 shrink-0 text-emerald-500" />
                        <span>{{ __($capability) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="relative overflow-hidden rounded-2xl border border-navy-800 bg-navy-950 p-5 text-white shadow-sm sm:p-6">
            <div class="pointer-events-none absolute -right-16 -top-16 size-52 rounded-full bg-cyber-400/10 blur-3xl"></div>
            <div class="relative flex items-center gap-3">
                <span class="flex size-10 items-center justify-center rounded-xl bg-cyber-400/15 text-cyber-300 ring-1 ring-cyber-400/20">
                    <flux:icon.shield-check class="size-5" />
                </span>
                <div>
                    <h2 class="text-base font-semibold">{{ __('Super Admin') }}</h2>
                    <p class="text-xs text-white/50">{{ __('Owns access, audit and configuration.') }}</p>
                </div>
            </div>

            <ul class="relative mt-5 space-y-2.5 text-sm text-white/70">
                @foreach ([
                    'Everything an Admin can do, across every module.',
                    'Create users, assign roles and deactivate access.',
                    'Review the full audit trail for every material action.',
                    'Manage financial settings, interest defaults and fees.',
                    'See portfolio, capital and compliance dashboards.',
                    'Investigate discrepancies with complete before/after values.',
                ] as $capability)
                    <li class="flex gap-2.5">
                        <flux:icon.check class="mt-0.5 size-4 shrink-0 text-cyber-300" />
                        <span>{{ __($capability) }}</span>
                    </li>
                @endforeach
            </ul>

            @if (auth()->user()->isSuperAdmin())
                <a href="{{ route('super-admin.users.index') }}" wire:navigate class="relative mt-5 inline-flex items-center gap-1.5 text-xs font-semibold text-cyber-300 transition hover:text-cyber-200">
                    {{ __('Manage team & access') }}
                    <flux:icon.chevron-right class="size-3.5" />
                </a>
            @endif
        </div>
    </section>

    {{-- Journey --}}
    <section class="fc-card p-5 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="fc-eyebrow">{{ __('Loan journey') }}</p>
                <h2 class="mt-1 text-xl font-semibold tracking-tight">{{ __('Eleven steps from application to closure') }}</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-500">
                    {{ __('Every loan follows the same path. The loan page always shows the current step, the checklist behind it and the next valid action.') }}
                </p>
            </div>
            <a href="{{ route('loans.index') }}" wire:navigate class="inline-flex shrink-0 items-center gap-1.5 text-xs font-semibold text-blue-600 transition hover:text-blue-700">
                {{ __('Open the loan pipeline') }}
                <flux:icon.arrow-right class="size-3.5" />
            </a>
        </div>

        <div class="mt-6 space-y-6">
            @php $stepNumber = 1; @endphp

            @foreach ($this->journey() as $phase => $steps)
                <div>
                    <div class="mb-3 flex items-center gap-3">
                        <span class="fc-eyebrow">{{ __($phase) }}</span>
                        <span class="h-px flex-1 bg-slate-200"></span>
                        <span class="text-[11px] font-medium text-slate-400">{{ trans_choice(':count step|:count steps', count($steps)) }}</span>
                    </div>

                    <ol class="space-y-3">
                        @foreach ($steps as $step)
                            <li class="flex gap-3 sm:gap-4">
                                <div class="flex flex-col items-center">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-white text-[11px] font-bold text-slate-500 ring-1 ring-slate-200">
                                        {{ $stepNumber }}
                                    </span>
                                    @if (! $loop->last)
                                        <span class="mt-1 w-px flex-1 bg-slate-200"></span>
                                    @endif
                                </div>

                                <div class="fc-card flex-1 p-4 sm:p-5">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <h3 class="text-sm font-semibold text-slate-900">{{ __($step['title']) }}</h3>
                                            <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-500">{{ __($step['description']) }}</p>
                                        </div>
                                        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                            {{ __($step['owner']) }}
                                        </span>
                                    </div>

                                    <a href="{{ route($step['route']) }}" wire:navigate class="mt-3 inline-flex items-center gap-1 text-[11px] font-semibold text-blue-600 transition hover:text-blue-700">
                                        {{ __($step['link']) }}
                                        <flux:icon.chevron-right class="size-3.5" />
                                    </a>
                                </div>
                            </li>

                            @php $stepNumber++; @endphp
                        @endforeach
                    </ol>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Money flow --}}
    <section class="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
        <div class="fc-card p-5 sm:p-6">
            <p class="fc-eyebrow">{{ __('Capital') }}</p>
            <h2 class="mt-1 text-lg font-semibold tracking-tight">{{ __('Where the money comes from') }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ __('Investor money is verified before it can fund a loan. The same chain runs in reverse when interest is paid out.') }}
            </p>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                @foreach ([
                    ['title' => '1. Deposit', 'body' => 'An investor transfers money and an admin records it as pending or cleared.', 'icon' => 'arrow-down-tray', 'tone' => 'blue'],
                    ['title' => '2. Verify', 'body' => 'Cleared deposits become available capital after active allocations are subtracted.', 'icon' => 'shield-check', 'tone' => 'emerald'],
                    ['title' => '3. Allocate', 'body' => 'Capital is reserved against one loan until the total equals the approved principal.', 'icon' => 'lock-closed', 'tone' => 'navy'],
                    ['title' => '4. Disburse', 'body' => 'After final approval the borrower is paid and the EMI schedule is generated.', 'icon' => 'banknotes', 'tone' => 'amber'],
                ] as $card)
                    @php
                        $tone = match ($card['tone']) {
                            'emerald' => 'bg-emerald-50 text-emerald-600 ring-emerald-600/10',
                            'navy' => 'bg-navy-50 text-navy-700 ring-navy-600/10',
                            'amber' => 'bg-amber-50 text-amber-600 ring-amber-600/10',
                            default => 'bg-blue-50 text-blue-600 ring-blue-600/10',
                        };
                    @endphp

                    <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4">
                        <span class="flex size-8 items-center justify-center rounded-lg ring-1 {{ $tone }}">
                            <x-dynamic-component :component="'flux::icon.'.$card['icon']" class="size-4" />
                        </span>
                        <p class="mt-2.5 text-xs font-semibold text-slate-900">{{ __($card['title']) }}</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ __($card['body']) }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/70 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">{{ __('Available capital formula') }}</p>
                <p class="mt-1.5 font-mono text-[13px] font-semibold text-amber-900">
                    {{ __('Cleared deposits − Active allocations = Available capital') }}
                </p>
                <p class="mt-1 text-[11px] leading-relaxed text-amber-700/80">
                    {{ __('Pending and rejected deposits never count. Released allocations return capital to the investor.') }}
                </p>
            </div>
        </div>

        <div class="fc-card p-5 sm:p-6">
            <p class="fc-eyebrow">{{ __('Returns') }}</p>
            <h2 class="mt-1 text-lg font-semibold tracking-tight">{{ __('How investors are paid') }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ __('Payouts are generated monthly only for active allocations on active loans, using interest that has actually been earned.') }}
            </p>

            <div class="mt-5 space-y-2">
                @foreach ([
                    ['label' => 'Gross interest', 'value' => '100%', 'hint' => 'Investor share of collected interest', 'class' => 'bg-slate-100 text-slate-700'],
                    ['label' => 'Management fee', 'value' => '10%', 'hint' => 'Platform fee income', 'class' => 'bg-blue-50 text-blue-700'],
                    ['label' => 'TDS', 'value' => '10%', 'hint' => 'Tax deducted at source', 'class' => 'bg-amber-50 text-amber-700'],
                    ['label' => 'Net payout', 'value' => '80%', 'hint' => 'Paid to the investor’s bank account', 'class' => 'bg-emerald-50 text-emerald-700'],
                ] as $row)
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-4 py-3">
                        <div>
                            <p class="text-xs font-semibold text-slate-800">{{ __($row['label']) }}</p>
                            <p class="mt-0.5 text-[11px] text-slate-400">{{ __($row['hint']) }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $row['class'] }}">{{ $row['value'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ __('Payout journal') }}</p>
                <div class="mt-2 space-y-1 font-mono text-[11px] text-slate-600">
                    <p>{{ __('Dr  Interest Expense') }} <span class="float-end">{{ __('gross') }}</span></p>
                    <p>{{ __('Cr  TDS Payable') }} <span class="float-end">{{ __('TDS') }}</span></p>
                    <p>{{ __('Cr  Management Fee Income') }} <span class="float-end">{{ __('fee') }}</span></p>
                    <p>{{ __('Cr  Cash & Bank') }} <span class="float-end">{{ __('net') }}</span></p>
                </div>
                <p class="mt-2 text-[11px] text-slate-400">{{ __('Every payout journal must balance before it is posted.') }}</p>
            </div>
        </div>
    </section>

    {{-- Interest example --}}
    <section class="fc-card p-5 sm:p-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="fc-eyebrow">{{ __('Interest') }}</p>
                <h2 class="mt-1 text-lg font-semibold tracking-tight">{{ __('Flat vs reducing, side by side') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Reference example: ₹1,00,000 at 12% per annum for 12 months. These figures are regression-tested and must not drift.') }}</p>
            </div>
            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold text-slate-600">
                <flux:icon.calculator class="size-3.5" />
                {{ __('₹ · en-IN · 2 dp') }}
            </span>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[560px] text-sm">
                <thead class="fc-table-head">
                    <tr>
                        <th class="rounded-s-lg px-4 py-3 font-semibold">{{ __('Method') }}</th>
                        <th class="px-4 py-3 text-end font-semibold">{{ __('EMI') }}</th>
                        <th class="px-4 py-3 text-end font-semibold">{{ __('Total interest') }}</th>
                        <th class="rounded-e-lg px-4 py-3 text-end font-semibold">{{ __('Total repayment') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-2 font-medium text-slate-800">
                                <span class="size-2 rounded-full bg-blue-600"></span>
                                {{ __('Flat') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-end font-mono tabular-nums text-slate-700">₹9,333.33</td>
                        <td class="px-4 py-3 text-end font-mono tabular-nums text-slate-700">₹12,000.00</td>
                        <td class="px-4 py-3 text-end font-mono tabular-nums text-slate-700">₹1,12,000.00</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-2 font-medium text-slate-800">
                                <span class="size-2 rounded-full bg-emerald-500"></span>
                                {{ __('Reducing') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-end font-mono tabular-nums text-slate-700">₹8,884.88</td>
                        <td class="px-4 py-3 text-end font-mono tabular-nums text-slate-700">₹6,618.53</td>
                        <td class="px-4 py-3 text-end font-mono tabular-nums text-slate-700">₹1,06,618.53</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    {{-- Module directory --}}
    <section>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="fc-eyebrow">{{ __('Directory') }}</p>
                <h2 class="mt-1 text-xl font-semibold tracking-tight">{{ __('Everything in one place') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Jump straight to the module you need. Administration links only appear for Super Admins.') }}</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->modules() as $module)
                @php
                    $tone = match ($module['tone']) {
                        'navy' => 'bg-navy-50 text-navy-700 ring-navy-600/10',
                        'emerald' => 'bg-emerald-50 text-emerald-600 ring-emerald-600/10',
                        'amber' => 'bg-amber-50 text-amber-600 ring-amber-600/10',
                        'cyan' => 'bg-cyan-50 text-cyan-700 ring-cyan-600/10',
                        'violet' => 'bg-violet-50 text-violet-600 ring-violet-600/10',
                        'rose' => 'bg-rose-50 text-rose-600 ring-rose-600/10',
                        'slate' => 'bg-slate-100 text-slate-600 ring-slate-500/10',
                        default => 'bg-blue-50 text-blue-600 ring-blue-600/10',
                    };
                @endphp

                <a href="{{ route($module['route']) }}" wire:navigate class="group fc-card flex flex-col gap-3 p-5 transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <span class="flex size-10 items-center justify-center rounded-xl ring-1 {{ $tone }}">
                            <x-dynamic-component :component="'flux::icon.'.$module['icon']" class="size-5" />
                        </span>
                        <flux:icon.arrow-up-right class="size-4 text-slate-300 transition group-hover:text-slate-500" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">{{ __($module['title']) }}</h3>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ __($module['description']) }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Guardrails --}}
    <section class="fc-card p-5 sm:p-6 lg:p-8">
        <p class="fc-eyebrow">{{ __('Guardrails') }}</p>
        <h2 class="mt-1 text-xl font-semibold tracking-tight">{{ __('What protects every rupee') }}</h2>
        <p class="mt-1 max-w-2xl text-sm text-slate-500">
            {{ __('The workflow is not just guidance — the services enforce these rules even if a form is bypassed.') }}
        </p>

        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ([
                ['title' => 'Exact decimal money', 'body' => 'Every amount is stored and calculated to 2 decimal places with bcmath. Floats are never used for money.', 'icon' => 'calculator', 'tone' => 'blue'],
                ['title' => 'Cleared capital only', 'body' => 'Pending and rejected investor deposits are excluded from available capital at every check.', 'icon' => 'shield-check', 'tone' => 'emerald'],
                ['title' => '100% funding gate', 'body' => 'A loan cannot be approved or disbursed while the allocated total is below the approved principal.', 'icon' => 'lock-closed', 'tone' => 'navy'],
                ['title' => 'Explicit approval', 'body' => 'Funding never triggers disbursement. A human must record final approval and the payment reference.', 'icon' => 'check-badge', 'tone' => 'amber'],
                ['title' => 'Balanced ledger', 'body' => 'Journals are rejected unless total debits equal total credits to the paisa.', 'icon' => 'scale', 'tone' => 'violet'],
                ['title' => 'Full audit trail', 'body' => 'Material actions record the actor, timestamp and before/after values for Super Admin review.', 'icon' => 'document-text', 'tone' => 'rose'],
            ] as $rule)
                @php
                    $tone = match ($rule['tone']) {
                        'emerald' => 'bg-emerald-50 text-emerald-600 ring-emerald-600/10',
                        'navy' => 'bg-navy-50 text-navy-700 ring-navy-600/10',
                        'amber' => 'bg-amber-50 text-amber-600 ring-amber-600/10',
                        'violet' => 'bg-violet-50 text-violet-600 ring-violet-600/10',
                        'rose' => 'bg-rose-50 text-rose-600 ring-rose-600/10',
                        default => 'bg-blue-50 text-blue-600 ring-blue-600/10',
                    };
                @endphp

                <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-4">
                    <span class="flex size-9 items-center justify-center rounded-lg ring-1 {{ $tone }}">
                        <x-dynamic-component :component="'flux::icon.'.$rule['icon']" class="size-4.5" />
                    </span>
                    <h3 class="mt-2.5 text-sm font-semibold text-slate-900">{{ __($rule['title']) }}</h3>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ __($rule['body']) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- CTA --}}
    <section class="relative overflow-hidden rounded-2xl bg-navy-950 px-6 py-7 text-white shadow-lg shadow-navy-950/15 sm:px-8">
        <div class="pointer-events-none absolute -right-20 -top-24 size-64 rounded-full bg-cyber-400/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-semibold">{{ __('Ready to work the pipeline?') }}</h2>
                <p class="mt-1 max-w-xl text-sm text-white/60">
                    {{ __('Start with today’s operations queue or open the loan pipeline to move an application to its next step.') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('operations.index') }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl bg-cyber-400 px-4 py-2.5 text-xs font-semibold text-navy-950 transition hover:bg-cyber-300">
                    <flux:icon.clipboard-document-check class="size-4" />
                    {{ __('Open daily operations') }}
                </a>
                <a href="{{ route('loans.index') }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/[0.06] px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-white/[0.1]">
                    <flux:icon.banknotes class="size-4" />
                    {{ __('Open loans') }}
                </a>
            </div>
        </div>
    </section>
</div>
