<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @include('partials.head', ['title' => __('Confirm password')])
    <style>
        @keyframes cp-draw { to { stroke-dashoffset: 0; } }
        @keyframes cp-pop {
            from { transform: scale(.82); }
            to { transform: scale(1); }
        }
        @keyframes cp-pulse {
            0% { transform: scale(.94); opacity: .55; }
            70%, 100% { transform: scale(1.22); opacity: 0; }
        }
        @keyframes cp-spin { to { transform: rotate(360deg); } }
        @keyframes cp-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
        @keyframes cp-fade-up {
            from { transform: translateY(10px); }
            to { transform: translateY(0); }
        }
        @keyframes cp-twinkle {
            0%, 100% { opacity: .25; transform: scale(.75); }
            50% { opacity: 1; transform: scale(1.15); }
        }
        @keyframes cp-border {
            0% { background-position: 0% 50%; }
            100% { background-position: 200% 50%; }
        }
        @keyframes cp-sheen {
            0% { transform: translateX(-120%); }
            100% { transform: translateX(220%); }
        }
        .cp-draw-shield {
            stroke-dasharray: 340;
            stroke-dashoffset: 340;
            animation: cp-draw 1.8s cubic-bezier(.22, 1, .36, 1) .15s forwards;
        }
        .cp-draw-shackle {
            stroke-dasharray: 80;
            stroke-dashoffset: 80;
            animation: cp-draw .9s cubic-bezier(.22, 1, .36, 1) .9s forwards;
        }
        .cp-pop {
            transform-box: fill-box;
            transform-origin: center;
            animation: cp-pop .7s cubic-bezier(.34, 1.56, .64, 1) 1.15s both;
        }
        .cp-pulse {
            transform-box: view-box;
            transform-origin: 50% 50%;
            animation: cp-pulse 3.6s ease-out infinite;
        }
        .cp-spin {
            transform-box: view-box;
            transform-origin: 50% 50%;
            animation: cp-spin 44s linear infinite;
        }
        .cp-float { animation: cp-float 6s ease-in-out infinite; }
        .cp-fade-up { animation: cp-fade-up .6s cubic-bezier(.22, 1, .36, 1) both; }
        .cp-twinkle {
            transform-box: fill-box;
            transform-origin: center;
            animation: cp-twinkle 3s ease-in-out infinite;
        }
        .cp-border {
            background-image: linear-gradient(110deg, #a7f3d0, #fde68a, #6ee7b7, #d1fae5, #a7f3d0);
            background-size: 220% 220%;
            animation: cp-border 9s linear infinite;
        }
        .cp-sheen::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(100deg, transparent 20%, rgba(255, 255, 255, .35) 50%, transparent 80%);
            transform: translateX(-120%);
            animation: cp-sheen 4.5s ease-in-out 1.2s infinite;
        }
        @media (prefers-reduced-motion: reduce) {
            .cp-draw-shield, .cp-draw-shackle, .cp-pop, .cp-pulse, .cp-spin,
            .cp-float, .cp-fade-up, .cp-twinkle, .cp-border, .cp-sheen::after {
                animation: none !important;
            }
            .cp-draw-shield, .cp-draw-shackle { stroke-dashoffset: 0 !important; }
            .cp-pop, .cp-fade-up { transform: none !important; }
            .cp-sheen::after { display: none; }
        }
    </style>
</head>
<body class="relative min-h-svh overflow-x-hidden bg-slate-50 text-zinc-900 antialiased selection:bg-emerald-500/20">

    {{-- Static ambience --}}
    <div class="pointer-events-none fixed inset-0 -z-10">
        <div class="absolute inset-0 bg-[radial-gradient(60%_45%_at_50%_-10%,rgba(16,185,129,0.10),transparent_65%),radial-gradient(45%_40%_at_100%_10%,rgba(251,191,36,0.10),transparent_70%),radial-gradient(50%_50%_at_0%_100%,rgba(15,23,42,0.06),transparent_70%)]"></div>
        <div class="absolute inset-0 opacity-[0.35] [background-image:radial-gradient(circle_at_1px_1px,rgba(100,116,139,0.18)_1px,transparent_0)] [background-size:26px_26px]"></div>
        <div class="absolute -left-24 top-1/3 size-72 rounded-full bg-emerald-300/20 blur-3xl"></div>
        <div class="absolute -right-20 bottom-0 size-80 rounded-full bg-amber-200/30 blur-3xl"></div>
    </div>

    <div class="relative flex min-h-svh flex-col">

        {{-- Top bar --}}
        <header class="flex items-center justify-between px-5 py-3 sm:px-8 sm:py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="SPF Finance & Capital home">
                <svg class="size-9 shrink-0" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect width="44" height="44" rx="13" fill="#0b1b33" />
                    <rect x="0.75" y="0.75" width="42.5" height="42.5" rx="12.25" stroke="#34d399" stroke-opacity="0.35" stroke-width="1.5" />
                    <path d="M9.5 22.5 16.5 16l5 3.5L32 9.5" stroke="#fbbf24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M26.5 9.5H32V15" stroke="#fbbf24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" />
                    <rect x="10" y="25" width="5" height="8.5" rx="1.6" fill="#34d399" fill-opacity="0.55" />
                    <rect x="17.5" y="20" width="5" height="13.5" rx="1.6" fill="#34d399" fill-opacity="0.8" />
                    <rect x="25" y="14" width="5" height="19.5" rx="1.6" fill="#a7f3d0" />
                </svg>
                <span class="leading-none">
                    <span class="block text-lg font-semibold tracking-tight text-navy-900">SPF</span>
                    <span class="mt-1 block text-[8px] font-semibold uppercase tracking-[0.24em] text-slate-500">{{ __('Finance & Capital') }}</span>
                </span>
            </a>

            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard') }}" class="hidden items-center gap-1.5 text-sm font-medium text-zinc-500 transition hover:text-navy-900 sm:inline-flex">
                    <flux:icon.arrow-left class="size-4" />
                    {{ __('Back to dashboard') }}
                </a>
                <span class="hidden h-4 w-px bg-zinc-200 sm:block"></span>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-600/20 bg-white px-2.5 py-1 text-[11px] font-semibold text-emerald-700 shadow-xs">
                    <flux:icon.lock-closed class="size-3" />
                    256-bit SSL
                </span>
            </div>
        </header>

        {{-- Centered card --}}
        <main class="relative flex flex-1 items-center justify-center px-4 py-3 sm:px-6 sm:py-5">

            {{-- Orbit decoration around the card --}}
            <div class="pointer-events-none absolute left-1/2 top-1/2 -z-0 -translate-x-1/2 -translate-y-1/2" aria-hidden="true">
                <div class="cp-spin size-[520px] rounded-full border border-dashed border-slate-300/60 sm:size-[580px]"></div>
                <span class="cp-twinkle absolute left-1/2 top-0 size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-emerald-400"></span>
                <span class="cp-twinkle absolute left-0 top-1/2 size-1.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-amber-400" style="animation-delay: .8s"></span>
                <span class="cp-twinkle absolute bottom-0 left-2/3 size-1.5 rounded-full bg-emerald-500" style="animation-delay: 1.4s"></span>
                <span class="cp-twinkle absolute right-0 top-1/3 size-2 translate-x-1/2 rounded-full bg-amber-300" style="animation-delay: 2s"></span>
            </div>

            <div class="cp-fade-up relative z-10 w-full max-w-[430px]">

                {{-- Animated gradient border --}}
                <div class="cp-border rounded-[26px] p-[1.5px] shadow-2xl shadow-navy-900/[0.08]">
                    <div class="relative overflow-hidden rounded-[25px] bg-white/95 p-4 backdrop-blur sm:p-6">

                        {{-- Sheen sweep across the card --}}
                        <div class="cp-sheen pointer-events-none absolute inset-0 overflow-hidden rounded-[25px]"></div>

                        {{-- Emblem --}}
                        <div class="relative mx-auto size-[68px] sm:size-[76px]">
                            <span class="absolute inset-0 rounded-full bg-emerald-400/10 blur-md"></span>
                            <svg viewBox="0 0 120 120" fill="none" class="relative size-[68px] sm:size-[76px]" role="img" aria-label="{{ __('Animated security shield with a padlock') }}">
                                <defs>
                                    <linearGradient id="cp-stroke" x1="24" y1="14" x2="96" y2="108" gradientUnits="userSpaceOnUse">
                                        <stop stop-color="#34d399" />
                                        <stop offset="1" stop-color="#fbbf24" />
                                    </linearGradient>
                                </defs>
                                <circle class="cp-pulse" cx="60" cy="60" r="44" stroke="#34d399" stroke-width="1.2" />
                                <path d="M60 12 98 26v27c0 23-16 39-38 49-22-10-38-26-38-49V26Z" fill="#ecfdf5" stroke="url(#cp-stroke)" stroke-opacity="0.3" stroke-width="2.2" stroke-linejoin="round" />
                                <path class="cp-draw-shield" d="M60 12 98 26v27c0 23-16 39-38 49-22-10-38-26-38-49V26Z" fill="none" stroke="url(#cp-stroke)" stroke-width="2.2" stroke-linejoin="round" />
                                <g class="cp-pop">
                                    <path d="M48 58v-6a12 12 0 0 1 24 0v6" stroke="#059669" stroke-opacity="0.3" stroke-width="4" stroke-linecap="round" />
                                    <path class="cp-draw-shackle" d="M48 58v-6a12 12 0 0 1 24 0v6" stroke="#059669" stroke-width="4" stroke-linecap="round" />
                                    <rect x="40" y="56" width="40" height="31" rx="9" fill="#0b1b33" />
                                    <circle cx="60" cy="69" r="4" fill="#fbbf24" />
                                    <path d="M60 73v7" stroke="#fbbf24" stroke-width="3" stroke-linecap="round" />
                                </g>
                                <g class="cp-pop" style="animation-delay: 1.5s">
                                    <circle cx="90" cy="80" r="12" fill="#34d399" />
                                    <path d="m84.5 80 3.8 3.8 7.2-7.4" stroke="#06281f" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                                </g>
                            </svg>
                        </div>

                        {{-- Heading --}}
                        <div class="mt-2 text-center sm:mt-3">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-emerald-700">{{ __('Secure area') }}</p>
                            <h1 class="mt-1 text-xl font-semibold tracking-tight text-navy-900 sm:text-[22px]">{{ __('Confirm your password') }}</h1>
                            <p class="mx-auto mt-1.5 max-w-[300px] text-[13px] leading-relaxed text-zinc-500">
                                {{ __('Re-enter your password to continue to this protected area.') }}
                            </p>
                        </div>

                        <x-auth-session-status class="mt-3 text-center" :status="session('status')" />

                        {{-- Passkey --}}
                        <div class="mt-4 sm:mt-5">
                            <x-passkey-verify
                                options-route="passkey.confirm-options"
                                submit-route="passkey.confirm"
                                :label="__('Confirm with passkey')"
                                :loading-label="__('Confirming...')"
                                :separator="__('Or confirm with password')"
                            />
                        </div>

                        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-3.5 sm:gap-4">
                            @csrf

                            <div class="relative">
                                <div class="pointer-events-none absolute -inset-px rounded-xl bg-gradient-to-r from-emerald-400/0 via-emerald-400/10 to-amber-300/0 blur-[2px]"></div>
                                <flux:input
                                    name="password"
                                    :label="__('Password')"
                                    type="password"
                                    required
                                    autofocus
                                    autocomplete="current-password"
                                    :placeholder="__('Enter your current password')"
                                    icon="key"
                                    viewable
                                />
                            </div>

                            <button
                                type="submit"
                                data-test="confirm-password-button"
                                class="group relative inline-flex h-11 w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-emerald-600 text-sm font-semibold text-white shadow-lg shadow-emerald-600/25 transition hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 active:scale-[0.99]"
                            >
                                <span class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/25 to-transparent transition duration-700 group-hover:translate-x-full"></span>
                                <flux:icon.lock-closed class="relative size-4" />
                                <span class="relative">{{ __('Confirm & continue') }}</span>
                                <flux:icon.arrow-right class="relative size-4 transition group-hover:translate-x-0.5" />
                            </button>

                            <p class="flex items-start justify-center gap-1.5 text-center text-[11px] leading-relaxed text-zinc-400">
                                <flux:icon.shield-check class="mt-px size-3.5 shrink-0 text-emerald-600" />
                                <span>{{ __('Protected session · audit logged.') }}</span>
                            </p>
                        </form>
                    </div>
                </div>

                {{-- Trust row --}}
                <div class="cp-fade-up mt-4 hidden gap-2 sm:grid sm:grid-cols-3" style="animation-delay: .12s">
                    @foreach ([
                        ['icon' => 'lock-closed', 'label' => __('Encrypted')],
                        ['icon' => 'shield-check', 'label' => __('Verified')],
                        ['icon' => 'document-text', 'label' => __('Audited')],
                    ] as $badge)
                        <div class="flex items-center justify-center gap-1.5 rounded-xl border border-slate-200/80 bg-white/80 px-2 py-2 text-[11px] font-medium text-zinc-600 backdrop-blur">
                            <x-dynamic-component :component="'flux::icon.'.$badge['icon']" class="size-3.5 text-emerald-600" />
                            {{ $badge['label'] }}
                        </div>
                    @endforeach
                </div>
            </div>
        </main>

        <footer class="flex items-center justify-between px-5 py-2.5 text-[11px] text-zinc-400 sm:px-8 sm:py-3">
            <span>&copy; {{ date('Y') }} SPF Finance & Capital Pvt. Ltd.</span>
            <span class="hidden sm:inline">{{ __('Nagpur · Maharashtra · India') }}</span>
        </footer>
    </div>

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>
</html>
