<?php

use App\Enums\Permission;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Settings')] class extends Component
{
    //
}; ?>

@php
    $user = auth()->user();
    $isSuperAdmin = $user->isSuperAdmin();
    $pageTitle = $isSuperAdmin ? __('Super Admin Settings') : __('Admin Settings');
    $pageDescription = $isSuperAdmin
        ? __('Manage your account, security and administrative configuration.')
        : __('Manage your profile, security, notifications and work preferences.');
    $roleBadge = $isSuperAdmin
        ? 'bg-navy-900 text-cyber-400'
        : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200';

    $systemTiles = collect([
        [
            'label' => __('General Settings'),
            'description' => __('Company identity, logo, regional formats, currency and timezone.'),
            'route' => 'super-admin.settings.general',
            'icon' => 'cog-6-tooth',
            'visible' => $isSuperAdmin || $user->hasPermission(Permission::SettingsViewGeneral),
        ],
        [
            'label' => __('Loan Settings'),
            'description' => __('Amount limits, tenure, interest bounds, processing fee and penalties.'),
            'route' => 'super-admin.settings.loans',
            'icon' => 'banknotes',
            'visible' => $isSuperAdmin || $user->hasPermission(Permission::SettingsViewLoan),
        ],
        [
            'label' => __('Interest Distribution'),
            'description' => __('Investor rate, admin commission, company margin and payout rules.'),
            'route' => 'super-admin.settings.interest',
            'icon' => 'chart-pie',
            'visible' => $isSuperAdmin || $user->hasPermission(Permission::SettingsViewInterest),
        ],
        [
            'label' => __('Investor & Deposit'),
            'description' => __('Eligibility, deposit verification and allocation controls.'),
            'route' => 'super-admin.settings.investors',
            'icon' => 'building-library',
            'visible' => $isSuperAdmin,
        ],
        [
            'label' => __('KYC & Verification'),
            'description' => __('Mandatory documents, upload limits and verification rules.'),
            'route' => 'super-admin.settings.kyc',
            'icon' => 'identification',
            'visible' => $isSuperAdmin,
        ],
        [
            'label' => __('User & Permissions'),
            'description' => __('Admin designations, granular permissions and access reviews.'),
            'route' => 'super-admin.users.index',
            'icon' => 'user-group',
            'visible' => $isSuperAdmin,
        ],
        [
            'label' => __('System & Security'),
            'description' => __('Password policy, lockout, sessions, audit trail and backups.'),
            'route' => 'super-admin.settings.security',
            'icon' => 'shield-check',
            'visible' => $isSuperAdmin,
        ],
    ])->filter(fn (array $tile): bool => $tile['visible']);
@endphp

<section class="w-full">
    <x-pages::settings.layout :heading="$pageTitle" :subheading="$pageDescription">
        @if (session('status'))
            <div class="mb-5 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                <flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-emerald-600" />
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- Account summary --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                @if ($user->avatar_path)
                    <img src="{{ route('profile.avatar') }}" alt="{{ $user->name }}" class="size-14 shrink-0 rounded-full object-cover ring-2 ring-white shadow">
                @else
                    <flux:avatar :name="$user->name" :initials="$user->initials()" size="xl" />
                @endif
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-semibold tracking-tight text-slate-900">{{ $user->name }}</h2>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em] {{ $roleBadge }}">
                            {{ $user->role->label() }}
                        </span>
                        @if ($user->designation)
                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-blue-700 ring-1 ring-inset ring-blue-600/20">
                                {{ $user->designation->label() }}
                            </span>
                        @endif
                    </div>
                    <p class="mt-1 truncate text-sm text-slate-500">{{ $user->email }}</p>
                    <dl class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-1.5 text-[11px] text-slate-400">
                        @if ($user->mobile)
                            <div class="flex items-center gap-1.5">
                                <flux:icon.device-phone-mobile class="size-3.5" />
                                <dt class="sr-only">{{ __('Mobile') }}</dt>
                                <dd>{{ $user->mobile }}</dd>
                            </div>
                        @endif
                        <div class="flex items-center gap-1.5">
                            <flux:icon.clock class="size-3.5" />
                            <dt class="sr-only">{{ __('Last sign in') }}</dt>
                            <dd>{{ __('Last sign in :time', ['time' => $user->last_login_at?->diffForHumans() ?? __('not recorded')]) }}</dd>
                        </div>
                    </dl>
                </div>
                <a href="{{ route('profile.edit') }}" wire:navigate
                   class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:text-navy-900">
                    <flux:icon.pencil-square class="size-4" />
                    {{ __('Edit profile') }}
                </a>
            </div>
        </div>

        {{-- Account settings --}}
        <h2 class="mt-8 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Account') }}</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            @foreach ([
                ['label' => __('My Profile'), 'description' => __('Update your name, email, mobile number and photo.'), 'route' => 'profile.edit', 'icon' => 'user'],
                ['label' => __('Security'), 'description' => __('Change your password, two-factor authentication, passkeys and sessions.'), 'route' => 'security.edit', 'icon' => 'shield-check'],
                ['label' => __('Notifications'), 'description' => __('Choose which updates reach you by email and in-app.'), 'route' => 'settings.notifications', 'icon' => 'bell'],
                ['label' => __('Work Preferences'), 'description' => __('Default landing page, date filters and table density.'), 'route' => 'settings.preferences', 'icon' => 'adjustments-horizontal'],
            ] as $tile)
                <a href="{{ route($tile['route']) }}" wire:navigate wire:key="account-{{ $tile['route'] }}"
                   class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-navy-300 hover:shadow-lg hover:shadow-navy-900/5">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-navy-50 text-navy-700 ring-1 ring-navy-600/10 transition group-hover:bg-navy-900 group-hover:text-cyber-400">
                        <flux:icon :name="$tile['icon']" class="size-5" />
                    </span>
                    <span class="mt-4 block text-sm font-semibold text-slate-900">{{ $tile['label'] }}</span>
                    <span class="mt-1 block text-xs leading-relaxed text-slate-500">{{ $tile['description'] }}</span>
                    <span class="mt-4 inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-navy-700 transition group-hover:text-navy-900">
                        {{ __('Manage') }}
                        <flux:icon.arrow-right class="size-3.5 transition group-hover:translate-x-0.5" />
                    </span>
                </a>
            @endforeach
        </div>

        {{-- System settings --}}
        @if ($systemTiles->isNotEmpty())
            <h2 class="mt-8 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                {{ __('System Settings') }}
                @if ($isSuperAdmin)
                    <span class="rounded-full bg-navy-900 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-[0.14em] text-cyber-400">{{ __('Super Admin') }}</span>
                @endif
            </h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                @foreach ($systemTiles as $tile)
                    <a href="{{ route($tile['route']) }}" wire:navigate wire:key="system-{{ $tile['route'] }}"
                       class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-navy-300 hover:shadow-lg hover:shadow-navy-900/5">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-navy-50 text-navy-700 ring-1 ring-navy-600/10 transition group-hover:bg-navy-900 group-hover:text-cyber-400">
                            <flux:icon :name="$tile['icon']" class="size-5" />
                        </span>
                        <span class="mt-4 block text-sm font-semibold text-slate-900">{{ $tile['label'] }}</span>
                        <span class="mt-1 block text-xs leading-relaxed text-slate-500">{{ $tile['description'] }}</span>
                        <span class="mt-4 inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-navy-700 transition group-hover:text-navy-900">
                            {{ __('Manage') }}
                            <flux:icon.arrow-right class="size-3.5 transition group-hover:translate-x-0.5" />
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-pages::settings.layout>
</section>
