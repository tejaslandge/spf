@php
    $settingsUser = auth()->user();
    $isSuperAdmin = $settingsUser->isSuperAdmin();
    $settingsHome = $isSuperAdmin ? route('super-admin.settings') : route('admin.settings');
    $activeItem = 'flex items-center gap-3 rounded-xl bg-navy-900 px-3 py-2.5 text-xs font-semibold text-white shadow-sm transition';
    $idleItem = 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50 hover:text-navy-900';
    $activeIcon = 'size-4 text-cyber-400';
    $idleIcon = 'size-4 text-slate-400';

    $systemLinks = collect([
        [
            'label' => __('General Settings'),
            'route' => 'super-admin.settings.general',
            'icon' => 'cog-6-tooth',
            'visible' => $isSuperAdmin || $settingsUser->hasPermission(\App\Enums\Permission::SettingsViewGeneral),
            'active' => ['super-admin.settings.general', 'super-admin.settings.general.update'],
        ],
        [
            'label' => __('Loan Settings'),
            'route' => 'super-admin.settings.loans',
            'icon' => 'banknotes',
            'visible' => $isSuperAdmin || $settingsUser->hasPermission(\App\Enums\Permission::SettingsViewLoan),
            'active' => ['super-admin.settings.loans'],
        ],
        [
            'label' => __('Interest Distribution'),
            'route' => 'super-admin.settings.interest',
            'icon' => 'chart-pie',
            'visible' => $isSuperAdmin || $settingsUser->hasPermission(\App\Enums\Permission::SettingsViewInterest),
            'active' => ['super-admin.settings.interest'],
        ],
        [
            'label' => __('Investor & Deposit'),
            'route' => 'super-admin.settings.investors',
            'icon' => 'building-library',
            'visible' => $isSuperAdmin,
            'active' => ['super-admin.settings.investors'],
        ],
        [
            'label' => __('KYC & Verification'),
            'route' => 'super-admin.settings.kyc',
            'icon' => 'identification',
            'visible' => $isSuperAdmin,
            'active' => ['super-admin.settings.kyc'],
        ],
        [
            'label' => __('User & Permissions'),
            'route' => 'super-admin.users.index',
            'icon' => 'user-group',
            'visible' => $isSuperAdmin,
            'active' => ['super-admin.users.*'],
        ],
        [
            'label' => __('System & Security'),
            'route' => 'super-admin.settings.security',
            'icon' => 'shield-check',
            'visible' => $isSuperAdmin,
            'active' => ['super-admin.settings.security'],
        ],
    ])->filter(fn (array $link): bool => $link['visible']);
@endphp

<div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
    <aside class="w-full lg:w-64 lg:shrink-0">
        <a href="{{ route($settingsUser->dashboardRoute()) }}"
           class="mb-3 inline-flex items-center gap-2 px-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400 transition hover:text-navy-900"
           wire:navigate>
            <flux:icon.arrow-left class="size-3.5" />
            {{ __('Back to dashboard') }}
        </a>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <a href="{{ $settingsHome }}" class="flex items-center gap-3 border-b border-slate-100 pb-4" wire:navigate>
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-navy-900 text-cyber-400 shadow-sm">
                    <flux:icon.cog-6-tooth class="size-5" />
                </span>
                <span class="min-w-0">
                    <span class="block text-[13px] font-semibold leading-tight tracking-tight text-slate-900">{{ __('Settings') }}</span>
                    <span class="mt-0.5 block truncate text-[10px] font-medium uppercase tracking-[0.16em] text-slate-400">
                        {{ $isSuperAdmin ? __('Super Admin') : __('Admin') }}
                    </span>
                </span>
            </a>

            <p class="mt-4 px-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Account') }}</p>
            <nav class="mt-2 space-y-1" aria-label="{{ __('Account settings') }}">
                <a href="{{ route('profile.edit') }}" wire:navigate
                   class="{{ request()->routeIs('profile.edit') ? $activeItem : $idleItem }}"
                   @if (request()->routeIs('profile.edit')) aria-current="page" @endif>
                    <flux:icon.user class="{{ request()->routeIs('profile.edit') ? $activeIcon : $idleIcon }}" />
                    {{ __('My Profile') }}
                </a>
                <a href="{{ route('security.edit') }}" wire:navigate
                   class="{{ request()->routeIs('security.edit', 'security.password.update') ? $activeItem : $idleItem }}"
                   @if (request()->routeIs('security.edit', 'security.password.update')) aria-current="page" @endif>
                    <flux:icon.shield-check class="{{ request()->routeIs('security.edit', 'security.password.update') ? $activeIcon : $idleIcon }}" />
                    {{ __('Security') }}
                </a>
                <a href="{{ route('settings.notifications') }}" wire:navigate
                   class="{{ request()->routeIs('settings.notifications') ? $activeItem : $idleItem }}"
                   @if (request()->routeIs('settings.notifications')) aria-current="page" @endif>
                    <flux:icon.bell class="{{ request()->routeIs('settings.notifications') ? $activeIcon : $idleIcon }}" />
                    {{ __('Notifications') }}
                </a>
                <a href="{{ route('settings.preferences') }}" wire:navigate
                   class="{{ request()->routeIs('settings.preferences') ? $activeItem : $idleItem }}"
                   @if (request()->routeIs('settings.preferences')) aria-current="page" @endif>
                    <flux:icon.adjustments-horizontal class="{{ request()->routeIs('settings.preferences') ? $activeIcon : $idleIcon }}" />
                    {{ __('Work Preferences') }}
                </a>
            </nav>

            @if ($systemLinks->isNotEmpty())
                <p class="mt-5 flex items-center gap-2 px-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                    {{ __('System Settings') }}
                    @if ($isSuperAdmin)
                        <span class="rounded-full bg-navy-900 px-1.5 py-0.5 text-[8px] font-semibold uppercase tracking-[0.14em] text-cyber-400">{{ __('Super Admin') }}</span>
                    @endif
                </p>
                <nav class="mt-2 space-y-1" aria-label="{{ __('System settings') }}">
                    @foreach ($systemLinks as $link)
                        @php($isActive = request()->routeIs($link['active']))
                        <a href="{{ route($link['route']) }}" wire:navigate
                           class="{{ $isActive ? $activeItem : $idleItem }}"
                           @if ($isActive) aria-current="page" @endif>
                            <flux:icon :name="$link['icon']" class="{{ $isActive ? $activeIcon : $idleIcon }}" />
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>

        <div class="mt-4 rounded-2xl border border-navy-100 bg-navy-50/60 p-4">
            <div class="flex items-start gap-3">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white text-navy-700 shadow-sm ring-1 ring-navy-600/10">
                    <flux:icon.information-circle class="size-4" />
                </span>
                <div>
                    <p class="text-xs font-semibold text-navy-900">{{ __('Security notice') }}</p>
                    <p class="mt-1 text-[11px] leading-relaxed text-navy-800/80">{{ __('Never share your password. Sensitive changes are recorded in the audit log.') }}</p>
                </div>
            </div>
        </div>
    </aside>

    <div class="min-w-0 flex-1">
        <div class="mb-5">
            <h1 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">{{ $heading ?? '' }}</h1>
            @if (filled($subheading ?? null))
                <p class="mt-1 text-sm text-slate-500">{{ $subheading }}</p>
            @endif
        </div>

        <div class="w-full max-w-3xl">
            {{ $slot }}
        </div>
    </div>
</div>
