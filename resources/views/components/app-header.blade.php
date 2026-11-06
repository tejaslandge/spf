@props(['title' => null])

@php
    $section = match (true) {
        request()->routeIs('investors.*', 'payouts.*') => __('Capital'),
        request()->routeIs('borrowers.*', 'loans.*', 'collections.*') => __('Portfolio'),
        request()->routeIs('reports.*', 'super-admin.audit-logs.*') => __('Insights'),
        request()->routeIs('super-admin.users.*') => __('Administration'),
        default => __('Operations'),
    };
@endphp

<header class="sticky top-0 z-10 border-b border-slate-200/80 bg-white/85 backdrop-blur-xl">
    <div class="flex h-16 items-center gap-3 px-4 lg:px-8">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <div class="min-w-0">
            <p class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                <span>{{ $section }}</span>
                <span class="hidden size-1 rounded-full bg-slate-300 sm:block"></span>
                <span class="hidden sm:inline">{{ now()->format('D, '.\App\Support\Regional::dateFormat()) }}</span>
            </p>
            <h1 class="truncate text-[15px] font-semibold tracking-tight text-slate-900">
                {{ $title ?? __('Dashboard') }}
            </h1>
        </div>

        <div class="ms-auto flex items-center gap-2">
            <div class="hidden h-10 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/80 px-3 text-xs text-slate-400 xl:flex">
                <flux:icon.magnifying-glass class="size-4" />
                <span>{{ __('Search accounts, loans, receipts…') }}</span>
                <kbd class="ms-8 rounded border border-slate-200 bg-white px-1.5 py-0.5 font-sans text-[10px] font-medium text-slate-400">Ctrl K</kbd>
            </div>

            <a href="{{ route('payouts.index') }}" class="hidden h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900 sm:flex">
                <flux:icon.banknotes class="size-4 text-blue-600" />
                <span class="hidden lg:inline">{{ __('Payouts') }}</span>
            </a>

            <livewire:notifications-bell />

            <span class="mx-1 hidden h-6 w-px bg-slate-200 sm:block"></span>

            <flux:dropdown position="bottom" align="end">
                <button type="button" class="flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white p-1 ps-2.5 shadow-sm transition hover:border-slate-300">
                    <span class="hidden text-end sm:block">
                        <span class="block max-w-36 truncate text-xs font-semibold leading-tight text-slate-900">{{ auth()->user()->name }}</span>
                        <span class="block text-[10px] leading-tight text-slate-400">{{ auth()->user()->role->label() }}</span>
                    </span>
                    <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" size="sm" />
                    <flux:icon.chevron-down class="me-0.5 size-3.5 text-slate-400" />
                </button>

                <flux:menu>
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                            <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                        </div>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Profile settings') }}
                    </flux:menu.item>

                    <flux:modal.trigger name="logout-confirmation">
                        <flux:menu.item as="button" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-menu-item">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </flux:modal.trigger>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>
</header>
