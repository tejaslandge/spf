<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @include('partials.head', ['title' => __('Verify your mobile')])
    <style>
        .otp-digit {
            caret-color: #0b1b33;
        }
        .otp-digit:focus {
            outline: none;
            border-color: #facc15;
            box-shadow: 0 0 0 4px rgb(250 204 21 / 0.18);
        }
        @media (prefers-reduced-motion: reduce) {
            .otp-orbit { animation: none !important; }
        }
    </style>
</head>
<body class="relative min-h-svh overflow-x-hidden bg-navy-950 text-zinc-900 antialiased">

    {{-- Ambient background --}}
    <div class="pointer-events-none fixed inset-0">
        <div class="absolute inset-0 bg-[radial-gradient(70%_55%_at_15%_0%,rgba(36,62,102,0.65),transparent_65%),radial-gradient(55%_45%_at_100%_10%,rgba(250,204,21,0.10),transparent_70%),radial-gradient(60%_60%_at_50%_110%,rgba(16,185,129,0.10),transparent_70%)]"></div>
        <svg class="absolute inset-0 h-full w-full opacity-[0.12]" aria-hidden="true">
            <defs>
                <pattern id="otp-dots" width="28" height="28" patternUnits="userSpaceOnUse">
                    <circle cx="1.4" cy="1.4" r="1.1" fill="#ffffff" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#otp-dots)" />
        </svg>
        <div class="otp-orbit absolute -left-24 top-1/4 size-72 rounded-full bg-cyber-400/10 blur-3xl"></div>
        <div class="otp-orbit absolute -right-24 bottom-0 size-80 rounded-full bg-emerald-400/10 blur-3xl"></div>
    </div>

    <div class="relative flex min-h-svh flex-col">

        {{-- Top bar --}}
        <header class="flex items-center justify-between px-5 py-4 sm:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="{{ \App\Support\Branding::name() }} home">
                <x-app-logo-icon class="size-10 shrink-0" />
                <span class="leading-none">
                    <span class="block text-base font-semibold tracking-tight text-white">{{ \App\Support\Branding::name() }}</span>
                    <span class="mt-1 block text-[8px] font-semibold uppercase tracking-[0.24em] text-white/45">{{ __('Finance & Capital') }}</span>
                </span>
            </a>

            <span class="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-2.5 py-1 text-[11px] font-semibold text-white/75 backdrop-blur">
                <flux:icon.lock-closed class="size-3 text-cyber-300" />
                {{ __('Secure verification') }}
            </span>
        </header>

        {{-- Card --}}
        <main class="flex flex-1 items-center justify-center px-4 py-6 sm:px-6">
            <div class="w-full max-w-[460px]">

                <div class="overflow-hidden rounded-3xl border border-white/10 bg-white shadow-2xl shadow-black/40">
                    <div class="h-1 w-full bg-gradient-to-r from-cyber-400 via-emerald-400 to-cyber-300"></div>

                    <div class="p-6 sm:p-8">

                        {{-- Steps --}}
                        <ol class="flex items-center gap-2 text-[11px] font-semibold" aria-label="{{ __('Sign-in progress') }}">
                            <li class="flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700 ring-1 ring-emerald-200">
                                <flux:icon.check-circle class="size-3.5" />
                                {{ __('Password') }}
                            </li>
                            <li class="h-px flex-1 bg-zinc-200" aria-hidden="true"></li>
                            <li class="flex items-center gap-1.5 rounded-full bg-navy-900 px-2.5 py-1 text-white">
                                <flux:icon.device-phone-mobile class="size-3.5 text-cyber-300" />
                                {{ __('Mobile OTP') }}
                            </li>
                        </ol>

                        {{-- Emblem --}}
                        <div class="mt-6 flex items-start gap-4">
                            <span class="relative flex size-12 shrink-0 items-center justify-center rounded-2xl bg-navy-900 text-cyber-300 shadow-lg shadow-navy-900/25">
                                <span class="absolute inset-0 rounded-2xl ring-1 ring-inset ring-white/10"></span>
                                <flux:icon.device-phone-mobile class="size-6" />
                            </span>
                            <div class="min-w-0">
                                <h1 class="text-xl font-semibold tracking-tight text-navy-900 sm:text-2xl">{{ __('Verify your mobile number') }}</h1>
                                <p class="mt-1 text-sm leading-relaxed text-zinc-500">
                                    {{ __('We sent a 6-digit code to the number registered on your account.') }}
                                </p>
                            </div>
                        </div>

                        {{-- Masked number --}}
                        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <span class="flex size-8 items-center justify-center rounded-lg bg-white text-navy-700 ring-1 ring-slate-200">
                                    <flux:icon.signal class="size-4" />
                                </span>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Registered mobile') }}</p>
                                    <p class="font-mono text-sm font-semibold tracking-wide text-navy-900">{{ $maskedPhone }}</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full bg-white px-2.5 py-1 text-[11px] font-medium text-slate-500 ring-1 ring-slate-200">
                                <flux:icon.clock class="size-3" />
                                <span data-expiry-label>{{ gmdate('i:s', max(0, (int) $expiresIn)) }}</span>
                            </span>
                        </div>

                        {{-- Status / errors --}}
                        <x-auth-session-status class="mt-4" :status="session('status')" />

                        @if ($errors->any())
                            <div class="mt-4 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-3 text-sm text-rose-700" role="alert">
                                <flux:icon.exclamation-triangle class="mt-0.5 size-4 shrink-0" />
                                <ul class="space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- OTP form --}}
                        <form id="otp-form" method="POST" action="{{ route('login.otp.verify') }}" class="mt-5" novalidate>
                            @csrf
                            <input type="hidden" name="code" id="otp-code" value="{{ old('code') }}">

                            <fieldset>
                                <legend class="sr-only">{{ __('Six-digit verification code') }}</legend>
                                <div class="grid grid-cols-6 gap-2 sm:gap-2.5" data-otp-group>
                                    @for ($index = 0; $index < 6; $index++)
                                        <input
                                            type="text"
                                            inputmode="numeric"
                                            pattern="[0-9]*"
                                            maxlength="1"
                                            data-otp-digit
                                            aria-label="{{ __('Digit :number', ['number' => $index + 1]) }}"
                                            @if ($index === 0) autofocus autocomplete="one-time-code" @else autocomplete="off" @endif
                                            class="otp-digit aspect-square w-full rounded-xl border border-slate-200 bg-white text-center text-xl font-semibold text-navy-900 shadow-sm transition placeholder:text-slate-300"
                                        >
                                    @endfor
                                </div>
                            </fieldset>

                            <button
                                type="submit"
                                id="verify-button"
                                data-test="verify-otp-button"
                                class="group relative mt-5 inline-flex h-11 w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-navy-900 text-sm font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy-700 disabled:cursor-wait disabled:opacity-70"
                            >
                                <span class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/20 to-transparent transition duration-700 group-hover:translate-x-full"></span>
                                <flux:icon.shield-check class="relative size-4 text-cyber-300" />
                                <span class="relative" data-verify-label>{{ __('Verify & continue') }}</span>
                                <flux:icon.arrow-right class="relative size-4 transition group-hover:translate-x-0.5" />
                            </button>
                        </form>

                        {{-- Resend / cancel --}}
                        <div class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <form id="resend-form" method="POST" action="{{ route('login.otp.resend') }}" class="contents">
                                @csrf
                                <button
                                    type="submit"
                                    id="resend-button"
                                    data-resend-in="{{ (int) $resendIn }}"
                                    @disabled($resendIn > 0)
                                    class="inline-flex items-center justify-center gap-1.5 text-xs font-semibold text-navy-800 transition hover:text-emerald-700 disabled:cursor-not-allowed disabled:text-slate-400"
                                >
                                    <flux:icon.arrow-path class="size-3.5" />
                                    <span data-resend-label>
                                        @if ($resendIn > 0)
                                            {{ __('Resend code in :seconds s', ['seconds' => (int) $resendIn]) }}
                                        @else
                                            {{ __('Resend code') }}
                                        @endif
                                    </span>
                                </button>
                            </form>

                            <form method="POST" action="{{ route('login.otp.cancel') }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 text-xs font-semibold text-slate-500 transition hover:text-navy-900">
                                    <flux:icon.arrow-left class="size-3.5" />
                                    {{ __('Change login') }}
                                </button>
                            </form>
                        </div>

                        <p class="mt-4 flex items-start gap-1.5 rounded-xl bg-slate-50 px-3 py-2.5 text-[11px] leading-relaxed text-slate-500">
                            <flux:icon.shield-check class="mt-px size-3.5 shrink-0 text-emerald-600" />
                            <span>{{ __('The code is sent only to the mobile number registered with this account. It expires in 5 minutes.') }}</span>
                        </p>
                    </div>
                </div>

                <p class="mt-5 text-center text-[11px] leading-relaxed text-white/40">
                    {{ __('Did not receive the code? Check your network, then resend after the cooldown.') }}
                </p>
            </div>
        </main>

        <footer class="flex items-center justify-between px-5 py-3 text-[11px] text-white/35 sm:px-8">
            <span>&copy; {{ date('Y') }} SPF Finance & Capital Pvt. Ltd.</span>
            <span class="hidden sm:inline">{{ __('Nagpur · Maharashtra · India') }}</span>
        </footer>
    </div>

    <script>
        (() => {
            const form = document.getElementById('otp-form');
            const digitInputs = Array.from(document.querySelectorAll('[data-otp-digit]'));
            const hidden = document.getElementById('otp-code');
            const verifyButton = document.getElementById('verify-button');
            const verifyLabel = document.querySelector('[data-verify-label]');

            if (!form || digitInputs.length === 0 || !hidden) {
                return;
            }

            const syncCode = () => {
                hidden.value = digitInputs.map((input) => input.value).join('');

                if (verifyButton) {
                    verifyButton.disabled = hidden.value.length !== 6;
                }
            };

            digitInputs.forEach((input, index) => {
                input.addEventListener('input', () => {
                    input.value = input.value.replace(/\D/g, '').slice(-1);

                    if (input.value !== '' && index < digitInputs.length - 1) {
                        digitInputs[index + 1].focus();
                    }

                    syncCode();
                });

                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Backspace' && input.value === '' && index > 0) {
                        digitInputs[index - 1].focus();
                    }

                    if (event.key === 'ArrowLeft' && index > 0) {
                        digitInputs[index - 1].focus();
                    }

                    if (event.key === 'ArrowRight' && index < digitInputs.length - 1) {
                        digitInputs[index + 1].focus();
                    }
                });

                input.addEventListener('paste', (event) => {
                    event.preventDefault();

                    const pasted = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6);

                    if (pasted === '') {
                        return;
                    }

                    digitInputs.forEach((box, boxIndex) => {
                        box.value = pasted[boxIndex] ?? '';
                    });

                    digitInputs[Math.min(pasted.length, 6) - 1]?.focus();
                    syncCode();
                });
            });

            // Seed the boxes when the browser restores an old value.
            if (hidden.value) {
                hidden.value.split('').slice(0, 6).forEach((digit, index) => {
                    digitInputs[index].value = digit;
                });
            }

            syncCode();

            form.addEventListener('submit', () => {
                if (verifyButton) {
                    verifyButton.disabled = true;
                }

                if (verifyLabel) {
                    verifyLabel.textContent = @js(__('Verifying…'));
                }
            });

            // Resend cooldown.
            const resendButton = document.getElementById('resend-button');
            const resendLabel = document.querySelector('[data-resend-label]');

            if (resendButton && resendLabel) {
                let remaining = Number.parseInt(resendButton.dataset.resendIn || '0', 10);

                const renderResend = () => {
                    if (remaining > 0) {
                        resendButton.disabled = true;
                        resendLabel.textContent = @js(__('Resend code in :seconds s')).replace(':seconds', String(remaining));
                    } else {
                        resendButton.disabled = false;
                        resendLabel.textContent = @js(__('Resend code'));
                    }
                };

                renderResend();

                window.setInterval(() => {
                    if (remaining <= 0) {
                        return;
                    }

                    remaining -= 1;
                    renderResend();
                }, 1000);

                document.getElementById('resend-form')?.addEventListener('submit', () => {
                    resendButton.disabled = true;
                    resendLabel.textContent = @js(__('Sending…'));
                });
            }

            // Challenge expiry countdown.
            const expiryLabel = document.querySelector('[data-expiry-label]');
            let expiresIn = {{ (int) $expiresIn }};

            if (expiryLabel) {
                window.setInterval(() => {
                    if (expiresIn <= 0) {
                        expiryLabel.textContent = '00:00';
                        return;
                    }

                    expiresIn -= 1;
                    const minutes = String(Math.floor(expiresIn / 60)).padStart(2, '0');
                    const seconds = String(expiresIn % 60).padStart(2, '0');
                    expiryLabel.textContent = `${minutes}:${seconds}`;
                }, 1000);
            }
        })();
    </script>

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>
</html>
