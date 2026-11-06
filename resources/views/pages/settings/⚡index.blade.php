<?php

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
        : __('Manage your profile and account security.');
    $roleBadge = $isSuperAdmin
        ? 'bg-navy-900 text-cyber-400'
        : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200';
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
                <flux:avatar :name="$user->name" :initials="$user->initials()" size="xl" />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-semibold tracking-tight text-slate-900">{{ $user->name }}</h2>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em] {{ $roleBadge }}">
                            {{ $user->role->label() }}
                        </span>
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
            <a href="{{ route('profile.edit') }}" wire:navigate
               class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-navy-300 hover:shadow-lg hover:shadow-navy-900/5">
                <span class="flex size-11 items-center justify-center rounded-xl bg-navy-50 text-navy-700 ring-1 ring-navy-600/10 transition group-hover:bg-navy-900 group-hover:text-cyber-400">
                    <flux:icon.user class="size-5" />
                </span>
                <span class="mt-4 block text-sm font-semibold text-slate-900">{{ __('My Profile') }}</span>
                <span class="mt-1 block text-xs leading-relaxed text-slate-500">{{ __('Update your name, email address and mobile number.') }}</span>
                <span class="mt-4 inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-navy-700 transition group-hover:text-navy-900">
                    {{ __('Manage') }}
                    <flux:icon.arrow-right class="size-3.5 transition group-hover:translate-x-0.5" />
                </span>
            </a>

            <a href="{{ route('security.edit') }}" wire:navigate
               class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-navy-300 hover:shadow-lg hover:shadow-navy-900/5">
                <span class="flex size-11 items-center justify-center rounded-xl bg-navy-50 text-navy-700 ring-1 ring-navy-600/10 transition group-hover:bg-navy-900 group-hover:text-cyber-400">
                    <flux:icon.shield-check class="size-5" />
                </span>
                <span class="mt-4 block text-sm font-semibold text-slate-900">{{ __('Security') }}</span>
                <span class="mt-1 block text-xs leading-relaxed text-slate-500">{{ __('Change your password, two-factor authentication and passkeys.') }}</span>
                <span class="mt-4 block text-[11px] text-slate-400">
                    {{ __('Last password change:') }}
                    <span class="font-medium text-slate-600">{{ $user->password_changed_at?->diffForHumans() ?? __('not recorded') }}</span>
                </span>
                <span class="mt-2 inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-navy-700 transition group-hover:text-navy-900">
                    {{ __('Manage') }}
                    <flux:icon.arrow-right class="size-3.5 transition group-hover:translate-x-0.5" />
                </span>
            </a>
        </div>

        {{-- System settings (Super Admin only) --}}
        @if ($isSuperAdmin)
            <h2 class="mt-8 flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                {{ __('System Settings') }}
                <span class="rounded-full bg-navy-900 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-[0.14em] text-cyber-400">{{ __('Super Admin') }}</span>
            </h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <a href="{{ route('super-admin.settings.general') }}" wire:navigate
                   class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-navy-300 hover:shadow-lg hover:shadow-navy-900/5">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-navy-50 text-navy-700 ring-1 ring-navy-600/10 transition group-hover:bg-navy-900 group-hover:text-cyber-400">
                        <flux:icon.cog-6-tooth class="size-5" />
                    </span>
                    <span class="mt-4 block text-sm font-semibold text-slate-900">{{ __('General Settings') }}</span>
                    <span class="mt-1 block text-xs leading-relaxed text-slate-500">{{ __('Company name, address and contact details used across statements and receipts.') }}</span>
                    <span class="mt-4 inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-navy-700 transition group-hover:text-navy-900">
                        {{ __('Manage') }}
                        <flux:icon.arrow-right class="size-3.5 transition group-hover:translate-x-0.5" />
                    </span>
                </a>

                <a href="{{ route('distribution.settings') }}" wire:navigate
                   class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-navy-300 hover:shadow-lg hover:shadow-navy-900/5">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-navy-50 text-navy-700 ring-1 ring-navy-600/10 transition group-hover:bg-navy-900 group-hover:text-cyber-400">
                        <flux:icon.chart-pie class="size-5" />
                    </span>
                    <span class="mt-4 block text-sm font-semibold text-slate-900">{{ __('Interest Settings') }}</span>
                    <span class="mt-1 block text-xs leading-relaxed text-slate-500">{{ __('Investor interest configuration, distribution rules and beneficiary shares.') }}</span>
                    <span class="mt-4 inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-navy-700 transition group-hover:text-navy-900">
                        {{ __('Manage') }}
                        <flux:icon.arrow-right class="size-3.5 transition group-hover:translate-x-0.5" />
                    </span>
                </a>
            </div>
        @endif
    </x-pages::settings.layout>
</section>
