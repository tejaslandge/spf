<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
        <flux:sidebar sticky collapsible="mobile" class="fc-sidebar dark border-e border-white/10">
            <flux:sidebar.header class="relative px-1 pb-3">
                <a href="{{ route(auth()->user()->dashboardRoute()) }}" class="flex min-w-0 items-center gap-3 pe-10 lg:pe-0" wire:navigate>
                    @if ($logoUrl = \App\Support\Branding::logoUrl())
                        <span class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white/95 shadow-lg shadow-black/40 ring-1 ring-white/25">
                            <img src="{{ $logoUrl }}" alt="{{ \App\Support\Branding::name() }}" class="size-full object-contain p-1">
                        </span>
                    @else
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyber-300 via-cyber-400 to-cyber-500 text-navy-950 shadow-lg shadow-black/40 ring-1 ring-white/25">
                            <flux:icon.chart-bar class="size-5" />
                        </span>
                    @endif
                    <span class="min-w-0">
                        <span class="block truncate text-[13px] font-semibold tracking-tight text-white lg:text-sm">{{ \App\Support\Branding::name() }}</span>
                        <span class="mt-1 flex items-center gap-1.5 whitespace-nowrap text-[10px] font-medium uppercase tracking-[0.16em] text-white/40 max-lg:hidden">
                            <span class="size-1.5 rounded-full bg-emerald-400 shadow-[0_0_0_3px_rgba(52,211,153,0.15)]"></span>
                            {{ __('Lending operations') }}
                        </span>
                    </span>
                </a>

                <flux:sidebar.collapse class="absolute end-0 top-1 text-white/60 hover:text-white lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Overview')">
                    <flux:sidebar.item icon="home" :href="route(auth()->user()->dashboardRoute())" :current="request()->routeIs('*.dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="book-open" :href="route('how-it-works.index')" :current="request()->routeIs('how-it-works.*')" wire:navigate>
                        {{ __('How it works') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Operations')">
                    <flux:sidebar.item icon="clipboard-document-check" :href="route('operations.index')" :current="request()->routeIs('operations.*')" wire:navigate>
                        {{ __('Daily Operations') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Portfolio')">
                    <flux:sidebar.item icon="users" :href="route('borrowers.index')" :current="request()->routeIs('borrowers.*')" wire:navigate>
                        {{ __('Borrowers') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="banknotes" :href="route('loans.index')" :current="request()->routeIs('loans.*')" wire:navigate>
                        {{ __('Loans') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="receipt-percent" :href="route('collections.index')" :current="request()->routeIs('collections.*')" wire:navigate>
                        {{ __('EMI Collections') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Capital')">
                    <flux:sidebar.item icon="building-office-2" :href="route('investors.index')" :current="request()->routeIs('investors.*')" wire:navigate>
                        {{ __('Investors') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="currency-dollar" :href="route('payouts.index')" :current="request()->routeIs('payouts.index')" wire:navigate>
                        {{ __('Interest Payouts') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="clock" :href="route('payouts.history')" :current="request()->routeIs('payouts.history')" wire:navigate>
                        {{ __('Payout History') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="banknotes" :href="route('super-admin.deposit-allocation.index')" :current="request()->routeIs('super-admin.deposit-allocation.*')" wire:navigate>
                        {{ __('Deposit Allocation') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Distribution')">
                    <flux:sidebar.item icon="chart-pie" :href="route('distribution.settings')" :current="request()->routeIs('distribution.settings')" wire:navigate>
                        {{ __('Interest Settings') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="user-group" :href="route('admin-commissions.index')" :current="request()->routeIs('admin-commissions.*')" wire:navigate>
                        {{ __('Admin Commissions') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="building-library" :href="route('company-earnings.index')" :current="request()->routeIs('company-earnings.*')" wire:navigate>
                        {{ __('Company Earnings') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Verification Management')">
                    <flux:sidebar.item icon="shield-check" :href="route('super-admin.verification.borrowers')" :current="request()->routeIs('super-admin.verification.borrowers')" wire:navigate>
                        {{ __('Borrower Verification') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="shield-check" :href="route('super-admin.verification.investors')" :current="request()->routeIs('super-admin.verification.investors')" wire:navigate>
                        {{ __('Investor Verification') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Insights')">
                    <flux:sidebar.item icon="chart-bar-square" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>
                        {{ __('Reports') }}
                    </flux:sidebar.item>

                    @if (auth()->user()->isSuperAdmin())
                        <flux:sidebar.item icon="shield-check" :href="route('super-admin.audit-logs.index')" :current="request()->routeIs('super-admin.audit-logs.*')" wire:navigate>
                            {{ __('Audit Logs') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>

                @if (auth()->user()->isSuperAdmin())
                    <flux:sidebar.group :heading="__('Administration')">
                        <flux:sidebar.item icon="user-group" :href="route('super-admin.users.index')" :current="request()->routeIs('super-admin.users.*')" wire:navigate>
                            {{ __('Team & Access') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="space-y-2.5">
                <a href="{{ route('loans.create') }}" class="group flex items-center gap-3 rounded-xl border border-white/10 bg-white/[0.04] p-2.5 transition hover:border-cyber-400/30 hover:bg-white/[0.07]" wire:navigate>
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-cyber-400/15 text-cyber-300">
                        <flux:icon.plus class="size-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-xs font-semibold text-white">{{ __('New loan') }}</span>
                        <span class="block truncate text-[10px] text-white/40">{{ __('Start an application') }}</span>
                    </span>
                    <flux:icon.chevron-right class="size-4 text-white/30 transition group-hover:text-cyber-300" />
                </a>

                <div class="border-t border-white/10 pt-2.5">
                    <x-desktop-user-menu class="w-full" :name="auth()->user()->name" />
                </div>
            </div>
        </flux:sidebar>

        <flux:main class="!p-0">
            <x-app-header :title="$title ?? null" />

            <div class="px-4 py-6 lg:px-8 lg:py-8">
                {{ $slot }}
            </div>
        </flux:main>

        <x-logout-modal />

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
