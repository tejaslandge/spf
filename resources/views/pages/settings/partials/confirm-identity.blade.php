@php
    $confirmUser = auth()->user();
@endphp

{{-- Inline confirmation so the user never leaves the Security page. --}}
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="grid lg:grid-cols-[0.82fr_1.18fr]">

        {{-- Animated security panel --}}
        <aside class="relative flex flex-col justify-center overflow-hidden bg-navy-900 p-6 text-white lg:p-8">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(70%_55%_at_15%_0%,rgba(16,185,129,0.22),transparent_65%),radial-gradient(55%_45%_at_100%_100%,rgba(251,191,36,0.12),transparent_70%)]"></div>

            <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-[0.12]" aria-hidden="true">
                <defs>
                    <pattern id="sc-dots" width="26" height="26" patternUnits="userSpaceOnUse">
                        <circle cx="1.5" cy="1.5" r="1.1" fill="#fff" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#sc-dots)" />
            </svg>

            <div class="pointer-events-none absolute -right-20 -top-16 size-64 animate-[spin_50s_linear_infinite]" aria-hidden="true">
                <svg viewBox="0 0 200 200" fill="none" class="size-full">
                    <circle cx="100" cy="100" r="94" stroke="#ffffff" stroke-opacity="0.16" stroke-width="1" stroke-dasharray="4 10" />
                    <circle cx="100" cy="100" r="70" stroke="#34d399" stroke-opacity="0.3" stroke-width="1" stroke-dasharray="2 8" />
                </svg>
            </div>

            <div class="relative">
                {{-- Animated emblem --}}
                <div class="relative mx-auto size-20 lg:mx-0">
                    <span class="absolute inset-0 animate-ping rounded-full bg-emerald-400/20" style="animation-duration: 3.4s"></span>
                    <span class="absolute inset-2 animate-ping rounded-full bg-emerald-400/10" style="animation-duration: 3.4s; animation-delay: 1.4s"></span>
                    <svg viewBox="0 0 120 120" fill="none" class="relative size-full drop-shadow-lg" role="img" aria-label="{{ __('Security shield with a padlock') }}">
                        <defs>
                            <linearGradient id="sc-stroke" x1="24" y1="14" x2="96" y2="108" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#34d399" />
                                <stop offset="1" stop-color="#fbbf24" />
                            </linearGradient>
                        </defs>
                        <path d="M60 12 98 26v27c0 23-16 39-38 49-22-10-38-26-38-49V26Z" fill="#ffffff" fill-opacity="0.08" stroke="url(#sc-stroke)" stroke-width="2.2" stroke-linejoin="round" />
                        <path d="M48 58v-6a12 12 0 0 1 24 0v6" stroke="#a7f3d0" stroke-width="4" stroke-linecap="round" />
                        <rect x="40" y="56" width="40" height="31" rx="9" fill="#071426" stroke="#34d399" stroke-opacity="0.6" />
                        <circle cx="60" cy="69" r="4" fill="#fbbf24" />
                        <path d="M60 73v7" stroke="#fbbf24" stroke-width="3" stroke-linecap="round" />
                        <circle cx="90" cy="80" r="12" fill="#34d399" />
                        <path d="m84.5 80 3.8 3.8 7.2-7.4" stroke="#06281f" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>

                <p class="mt-4 text-center text-[10px] font-semibold uppercase tracking-[0.2em] text-emerald-300 lg:text-start">{{ __('Identity check') }}</p>
                <h2 class="mt-1.5 text-center text-xl font-semibold tracking-tight text-white lg:text-start">
                    {{ __('Confirm it') }}<span class="text-emerald-300">{{ __("'s really you") }}</span>
                </h2>
                <p class="mt-2 hidden text-[13px] leading-relaxed text-white/60 sm:block">
                    {{ __('This page controls your password, two-factor authentication and signed-in devices. Re-enter your password to unlock it.') }}
                </p>

                <ul class="mt-5 hidden space-y-2.5 lg:block">
                    @foreach ([
                        ['icon' => 'lock-closed', 'label' => __('Password protected')],
                        ['icon' => 'shield-check', 'label' => __('Encrypted in transit')],
                        ['icon' => 'document-magnifying-glass', 'label' => __('Confirmation attempts are audit logged')],
                    ] as $point)
                        <li class="flex items-center gap-2.5 text-xs text-white/65">
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-lg border border-emerald-400/20 bg-emerald-400/10 text-emerald-300">
                                <x-dynamic-component :component="'flux::icon.'.$point['icon']" class="size-3.5" />
                            </span>
                            {{ $point['label'] }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>

        {{-- Confirmation form --}}
        <div class="p-6 sm:p-8">
            <div class="flex items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                    <flux:icon.shield-check class="size-5" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('Confirm your identity') }}</h2>
                    <p class="truncate text-xs text-slate-500">{{ __('Signed in as :email', ['email' => $confirmUser->email]) }}</p>
                </div>
            </div>

            <p class="mt-4 text-sm leading-relaxed text-slate-500">
                {{ __('Re-enter your password to unlock the security controls on this page. You will stay right here.') }}
            </p>

            <form wire:submit="confirmIdentity" class="mt-6 space-y-5">
                <flux:input
                    wire:model="confirmPassword"
                    name="confirmPassword"
                    :label="__('Password')"
                    type="password"
                    required
                    autofocus
                    autocomplete="current-password"
                    :placeholder="__('Enter your current password')"
                    icon="key"
                    viewable
                />

                <div class="space-y-3 border-t border-slate-100 pt-5">
                    <button type="submit" wire:loading.attr="disabled" wire:target="confirmIdentity"
                            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-navy-900 px-5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-70">
                        <flux:icon.lock-closed class="size-4" />
                        <span wire:loading.remove wire:target="confirmIdentity">{{ __('Confirm & continue') }}</span>
                        <span wire:loading wire:target="confirmIdentity">{{ __('Confirming…') }}</span>
                    </button>

                    <p class="flex items-start justify-center gap-1.5 text-center text-[11px] leading-relaxed text-slate-400">
                        <flux:icon.information-circle class="mt-px size-3.5 shrink-0 text-slate-400" />
                        <span>{{ __('Your password is checked securely and is never stored by this page.') }}</span>
                    </p>
                </div>
            </form>
        </div>
    </div>
</div>
