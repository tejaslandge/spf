<?php

use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Title;
use Livewire\Component;
/* @chisel-passkeys */
use Laravel\Passkeys\Actions\DeletePasskey;
use Livewire\Attributes\Locked;
/* @end-chisel-passkeys */
/* @chisel-2fa */
use Livewire\Attributes\On;
/* @end-chisel-2fa */

new #[Title('Security')] class extends Component {
    /* @chisel-2fa */
    public bool $canManageTwoFactor;

    public bool $twoFactorEnabled;

    public bool $requiresConfirmation;
    /* @end-chisel-2fa */

    /* @chisel-passkeys */
    #[Locked]
    public bool $canManagePasskeys;

    #[Locked]
    public array $passkeys = [];

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingPasskeyId = null;

    #[Locked]
    public string $deletingPasskeyName = '';
    /* @end-chisel-passkeys */

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        /* @chisel-2fa */
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }
        /* @end-chisel-2fa */

        /* @chisel-passkeys */
        $this->canManagePasskeys = Features::canManagePasskeys();

        if ($this->canManagePasskeys) {
            $this->loadPasskeys();
        }
        /* @end-chisel-passkeys */
    }

    /* @chisel-passkeys */
    /**
     * Load the user's passkeys.
     */
    public function loadPasskeys(): void
    {
        $this->passkeys = auth()->user()->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get()
            ->map(fn ($passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'created_at_diff' => $passkey->created_at->diffForHumans(),
                'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
            ])
            ->toArray();
    }

    /**
     * Show the delete confirmation modal.
     */
    public function confirmDelete(int $passkeyId): void
    {
        $passkey = auth()->user()->passkeys()->findOrFail($passkeyId);

        $this->deletingPasskeyId = $passkey->id;
        $this->deletingPasskeyName = $passkey->name;
        $this->showDeleteModal = true;
    }

    /**
     * Delete the passkey.
     */
    public function deletePasskey(DeletePasskey $deletePasskey): void
    {
        if (! $this->deletingPasskeyId) {
            return;
        }

        $passkey = auth()->user()->passkeys()->findOrFail($this->deletingPasskeyId);

        $deletePasskey(auth()->user(), $passkey);

        $this->closeDeleteModal();
        $this->loadPasskeys();
    }

    /**
     * Close the delete confirmation modal.
     */
    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingPasskeyId = null;
        $this->deletingPasskeyName = '';
    }
    /* @end-chisel-passkeys */

    /* @chisel-2fa */
    /**
     * Handle the two-factor authentication enabled event.
     */
    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }
    /* @end-chisel-2fa */
}; ?>

@php
    $securityUser = auth()->user();
    $twoFactorEnabled = $canManageTwoFactor ? $twoFactorEnabled : null;
@endphp

<section class="w-full">
    <x-pages::settings.layout :heading="__('Security')" :subheading="__('Your password, two-factor authentication and passkeys.')">
        @if (session('status'))
            <div class="mb-5 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                <flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-emerald-600" />
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- Account security overview --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <flux:avatar :name="$securityUser->name" :initials="$securityUser->initials()" size="lg" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $securityUser->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $securityUser->email }}</p>
                    </div>
                </div>
                <span class="inline-flex shrink-0 items-center rounded-full {{ $securityUser->isSuperAdmin() ? 'bg-navy-900 text-cyber-400' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200' }} px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em]">
                    {{ $securityUser->role->label() }}
                </span>
            </div>

            <dl class="mt-5 grid grid-cols-1 gap-4 border-t border-slate-100 pt-5 sm:grid-cols-3">
                <div>
                    <dt class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Password') }}</dt>
                    <dd class="mt-1.5 flex items-center gap-1.5 text-xs font-semibold {{ $securityUser->password_changed_at ? 'text-slate-700' : 'text-slate-400' }}">
                        <flux:icon.key class="size-4 text-slate-400" />
                        {{ $securityUser->password_changed_at
                            ? __('Changed :time', ['time' => $securityUser->password_changed_at->diffForHumans()])
                            : __('Not recorded yet') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Two-factor') }}</dt>
                    <dd class="mt-1.5">
                        @if ($twoFactorEnabled === null)
                            <span class="text-xs font-semibold text-slate-400">{{ __('Not available') }}</span>
                        @elseif ($twoFactorEnabled)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-200">
                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                {{ __('Enabled') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800 ring-1 ring-amber-200">
                                <span class="size-1.5 rounded-full bg-amber-500"></span>
                                {{ __('Disabled') }}
                            </span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Passkeys') }}</dt>
                    <dd class="mt-1.5 flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                        <flux:icon.finger-print class="size-4 text-slate-400" />
                        {{ trans_choice(':count passkey registered|:count passkeys registered', $canManagePasskeys ? count($passkeys) : 0, ['count' => $canManagePasskeys ? count($passkeys) : 0]) }}
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Change password --}}
        <div class="mt-6">
            @include('pages.settings.partials.change-password')
        </div>

        {{-- @chisel-2fa --}}
        @if ($canManageTwoFactor)
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                            <flux:icon.device-phone-mobile class="size-4" />
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">{{ __('Two-factor authentication') }}</h2>
                            <p class="text-xs text-slate-500">{{ __('A secure PIN is requested at every sign-in.') }}</p>
                        </div>
                    </div>
                    @if ($twoFactorEnabled)
                        <flux:button variant="danger" wire:click="disable" class="shrink-0">
                            {{ __('Disable 2FA') }}
                        </flux:button>
                    @else
                        <flux:modal.trigger name="two-factor-setup-modal" class="shrink-0">
                            <flux:button variant="primary" wire:click="$dispatch('start-two-factor-setup')">
                                {{ __('Enable 2FA') }}
                            </flux:button>
                        </flux:modal.trigger>
                    @endif
                </div>

                <div class="mt-4 text-xs leading-relaxed text-slate-500" wire:cloak>
                    @if ($twoFactorEnabled)
                        <p>{{ __('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}</p>
                        <div class="mt-4">
                            <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                        </div>
                    @else
                        <p>{{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}</p>
                        <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
                    @endif
                </div>
            </div>
        @endif
        {{-- @end-chisel-2fa --}}

        {{-- @chisel-passkeys --}}
        @if ($canManagePasskeys)
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                            <flux:icon.finger-print class="size-4" />
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">{{ __('Passkeys') }}</h2>
                            <p class="text-xs text-slate-500">{{ __('Passwordless sign-in with your device biometrics.') }}</p>
                        </div>
                    </div>
                    <x-passkey-registration />
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-slate-200" wire:cloak>
                    @forelse ($passkeys as $passkey)
                        <div class="flex items-center justify-between gap-4 p-4 {{ ! $loop->last ? 'border-b border-slate-200' : '' }}">
                            <div class="flex min-w-0 items-center gap-4">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-slate-100">
                                    <flux:icon.key class="size-5 text-slate-500" />
                                </div>
                                <div class="min-w-0 space-y-1">
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        <p class="truncate text-sm font-medium text-slate-900">{{ $passkey['name'] }}</p>
                                        @if ($passkey['authenticator'])
                                            <flux:badge size="sm">{{ $passkey['authenticator'] }}</flux:badge>
                                        @endif
                                    </div>
                                    <p class="text-[11px] text-slate-500">
                                        {{ __('Added :time', ['time' => $passkey['created_at_diff']]) }}
                                        @if ($passkey['last_used_at_diff'])
                                            <span class="mx-1 opacity-50">/</span>
                                            {{ __('Last used :time', ['time' => $passkey['last_used_at_diff']]) }}
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                icon:variant="outline"
                                wire:click="confirmDelete({{ $passkey['id'] }})"
                                class="shrink-0 text-red-500 hover:bg-red-50 hover:text-red-600"
                                aria-label="{{ __('Remove passkey') }}"
                            />
                        </div>
                    @empty
                        <div class="p-8 text-center">
                            <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-slate-100">
                                <flux:icon.finger-print class="size-7 text-slate-400" />
                            </div>
                            <p class="text-sm font-medium text-slate-900">{{ __('No passkeys yet') }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ __('Add a passkey to sign in without a password') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        @endif
        {{-- @end-chisel-passkeys --}}
    </x-pages::settings.layout>

    {{-- @chisel-passkeys --}}
    <flux:modal
        name="delete-passkey-modal"
        class="max-w-md md:min-w-md"
        @close="closeDeleteModal"
        wire:model="showDeleteModal"
    >
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Remove passkey') }}</flux:heading>
                <flux:text>
                    {{ __('Are you sure you want to remove the passkey ":name"? You will no longer be able to use it to sign in.', ['name' => $deletingPasskeyName]) }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button
                    variant="outline"
                    wire:click="closeDeleteModal"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    variant="danger"
                    wire:click="deletePasskey"
                >
                    {{ __('Remove passkey') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
    {{-- @end-chisel-passkeys --}}
</section>
