<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    @include('partials.head', ['title' => __('Log in')])
    <style>
        @keyframes spf-draw { to { stroke-dashoffset: 0; } }
        @keyframes spf-float { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-14px) } }
        @keyframes spf-float-soft { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-8px) } }
        @keyframes spf-bar { from { transform: scaleY(0); opacity: 0 } to { transform: scaleY(1); opacity: 1 } }
        @keyframes spf-fade-up { from { opacity: 0; transform: translateY(14px) } to { opacity: 1; transform: translateY(0) } }
        @keyframes spf-pulse-dot { 0%,100% { opacity: 1; transform: scale(1) } 50% { opacity: .55; transform: scale(.82) } }
        @keyframes spf-spin-slow { to { transform: rotate(360deg) } }
        @keyframes spf-shimmer { to { background-position: 200% center; } }
        @keyframes spf-wiggle { 0%,100% { transform: rotate(-2deg) } 50% { transform: rotate(2deg) } }
        .spf-draw-line { stroke-dasharray: 620; stroke-dashoffset: 620; animation: spf-draw 2.6s cubic-bezier(.22,1,.36,1) .3s forwards; }
        .spf-draw-line-soft { stroke-dasharray: 620; stroke-dashoffset: 620; animation: spf-draw 2.8s ease .7s forwards; opacity: .35; }
        .spf-fade-up { animation: spf-fade-up .7s cubic-bezier(.22,1,.36,1) both; }
        .spf-float { animation: spf-float 7s ease-in-out infinite; }
        .spf-float-soft { animation: spf-float-soft 6s ease-in-out infinite; }
        .spf-bar { transform-origin: bottom; animation: spf-bar .9s cubic-bezier(.22,1,.36,1) both; }
        .spf-spin-slow { animation: spf-spin-slow 22s linear infinite; transform-origin: center; }
        @media (prefers-reduced-motion: reduce) {
            .spf-draw-line, .spf-draw-line-soft, .spf-float, .spf-float-soft, .spf-bar, .spf-fade-up, .spf-spin-slow { animation: none !important; }
            .spf-draw-line, .spf-draw-line-soft { stroke-dashoffset: 0 !important; }
            .spf-bar { transform: scaleY(1) !important; opacity: 1 !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-white text-zinc-900 antialiased selection:bg-emerald-500/20">
    <div class="grid min-h-screen lg:grid-cols-[1.05fr_.95fr]">

        {{-- Left brand / animated SVG panel --}}
        <aside class="relative hidden overflow-hidden bg-navy-900 lg:flex lg:flex-col">
            {{-- background gradients --}}
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(70%_50%_at_20%_-10%,rgba(16,185,129,0.22),transparent_65%),radial-gradient(50%_40%_at_100%_10%,rgba(251,191,36,0.12),transparent_70%),radial-gradient(60%_60%_at_50%_110%,rgba(68,103,156,0.28),transparent_70%)]"></div>
            <div class="pointer-events-none absolute -left-24 top-1/3 size-72 rounded-full bg-emerald-500/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -right-20 bottom-10 size-80 rounded-full bg-amber-400/10 blur-3xl"></div>

            {{-- dotted grid SVG --}}
            <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-[0.13]" aria-hidden="true">
                <defs>
                    <pattern id="spf-dots" width="28" height="28" patternUnits="userSpaceOnUse">
                        <circle cx="1.5" cy="1.5" r="1.2" fill="#fff" />
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#spf-dots)" />
            </svg>

            {{-- large rotating dashed ring --}}
            <svg class="pointer-events-none absolute -right-32 -top-28 size-[520px] text-white/10 spf-spin-slow" viewBox="0 0 200 200" fill="none" aria-hidden="true">
                <circle cx="100" cy="100" r="96" stroke="currentColor" stroke-width="1" stroke-dasharray="6 10" />
                <circle cx="100" cy="100" r="72" stroke="currentColor" stroke-width="1" stroke-dasharray="4 8" opacity="0.7" />
                <circle cx="100" cy="100" r="48" stroke="currentColor" stroke-width="1" opacity="0.4" />
            </svg>

            {{-- top logo --}}
            <div class="relative z-10 flex items-center justify-between px-10 pt-8">
                <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="{{ \App\Support\Branding::name() }} home">
                    <x-app-logo-icon class="size-11 shrink-0 drop-shadow-lg" />
                    <span class="leading-none">
                        <span class="block text-xl font-semibold tracking-tight text-white">{{ \App\Support\Branding::name() }}</span>
                        <span class="mt-1 block text-[9px] font-semibold uppercase tracking-[0.24em] text-white/50">{{ __('Finance & Capital') }}</span>
                    </span>
                </a>
                <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-[11px] font-medium text-white/80 backdrop-blur">
                    <span class="relative flex size-1.5">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex size-1.5 rounded-full bg-emerald-400"></span>
                    </span>
                    {{ __('Secure partner portal') }}
                </span>
            </div>

            {{-- headline --}}
            <div class="relative z-10 px-10 pt-14">
                <p class="spf-fade-up text-xs font-semibold uppercase tracking-[0.18em] text-emerald-300" style="animation-delay:.05s">{{ __('Deposits-first lending') }}</p>
                <h1 class="spf-fade-up mt-4 max-w-md text-4xl font-semibold leading-[1.08] tracking-tight text-white sm:text-[2.75rem]" style="animation-delay:.12s">
                    {{ __('Credit that moves') }}<br>
                    <span class="bg-gradient-to-r from-emerald-300 via-emerald-200 to-amber-200 bg-clip-text text-transparent">{{ __('India forward.') }}</span>
                </h1>
                <p class="spf-fade-up mt-4 max-w-md text-[15px] leading-relaxed text-white/65" style="animation-delay:.2s">
                    {{ __('Sign in to manage your loan book, verify cleared capital and track every rupee — transparent from application to closure.') }}
                </p>
            </div>

            {{-- animated portfolio card --}}
            <div class="relative z-10 mx-10 mt-10 spf-float">
                <div class="overflow-hidden rounded-2xl border border-white/10 bg-white/[0.07] shadow-2xl shadow-black/30 backdrop-blur-xl">
                    <div class="flex items-center justify-between border-b border-white/10 px-5 py-4">
                        <div class="flex items-center gap-2">
                            <span class="size-2.5 rounded-full bg-rose-400/90"></span>
                            <span class="size-2.5 rounded-full bg-amber-400/90"></span>
                            <span class="size-2.5 rounded-full bg-emerald-400/90"></span>
                        </div>
                        <div class="flex items-center gap-1.5 rounded-md bg-white/10 px-2 py-1 text-[11px] font-medium text-white/60">
                            <flux:icon.lock-closed class="size-3" />
                            spf.finance/portfolio
                        </div>
                        <flux:icon.ellipsis-horizontal class="size-4 text-white/40" />
                    </div>

                    <div class="space-y-4 p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-medium text-white/50">{{ __('Active loan book') }}</p>
                                <p class="mt-1 text-[28px] font-semibold leading-none tracking-tight tabular-nums text-white">₹24.8 Cr</p>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-lg border border-white/10 bg-white/5 px-2.5 py-1.5 text-[11px] font-semibold text-white/70">
                                {{ __('This quarter') }}
                                <flux:icon.chevron-down class="size-3" />
                            </span>
                        </div>

                        <div class="flex items-center gap-2 text-xs">
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-400/15 px-2 py-0.5 font-semibold text-emerald-300 ring-1 ring-inset ring-emerald-400/20">
                                <flux:icon.arrow-trending-up class="size-3" />
                                +12.4%
                            </span>
                            <span class="text-white/50">{{ __('vs. last quarter') }}</span>
                        </div>

                        {{-- animated chart + bars SVG --}}
                        <div class="relative rounded-xl bg-white/[0.04] p-3 ring-1 ring-white/5">
                            <svg viewBox="0 0 420 150" fill="none" class="h-28 w-full" preserveAspectRatio="none" aria-hidden="true">
                                <defs>
                                    <linearGradient id="spf-login-fill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#34d399" stop-opacity="0.32" />
                                        <stop offset="100%" stop-color="#34d399" stop-opacity="0" />
                                    </linearGradient>
                                    <linearGradient id="spf-login-stroke" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#34d399" />
                                        <stop offset="100%" stop-color="#fbbf24" />
                                    </linearGradient>
                                </defs>

                                {{-- baseline grid --}}
                                <g stroke="#ffffff" stroke-opacity="0.08" stroke-width="1">
                                    <line x1="0" y1="36" x2="420" y2="36" />
                                    <line x1="0" y1="72" x2="420" y2="72" />
                                    <line x1="0" y1="108" x2="420" y2="108" />
                                </g>

                                {{-- area fill --}}
                                <path d="M0 118 C28 114 38 92 68 94 C98 96 108 112 140 104 C172 96 182 58 212 60 C242 62 254 84 284 76 C314 68 324 36 354 32 C384 28 396 46 420 34 V150 H0 Z" fill="url(#spf-login-fill)" />
                                {{-- soft ghost line --}}
                                <path class="spf-draw-line-soft" d="M0 118 C28 114 38 92 68 94 C98 96 108 112 140 104 C172 96 182 58 212 60 C242 62 254 84 284 76 C314 68 324 36 354 32 C384 28 396 46 420 34" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" />
                                {{-- main line --}}
                                <path class="spf-draw-line" d="M0 118 C28 114 38 92 68 94 C98 96 108 112 140 104 C172 96 182 58 212 60 C242 62 254 84 284 76 C314 68 324 36 354 32 C384 28 396 46 420 34" stroke="url(#spf-login-stroke)" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round" />

                                {{-- end dot --}}
                                <g style="animation: spf-fade-up .6s ease 2.2s both">
                                    <circle cx="420" cy="34" r="10" fill="#fbbf24" fill-opacity="0.18" />
                                    <circle cx="420" cy="34" r="4.5" fill="#fbbf24" />
                                    <circle cx="420" cy="34" r="9" stroke="#fbbf24" stroke-opacity="0.4" />
                                </g>
                                <g style="animation: spf-fade-up .6s ease 1.6s both">
                                    <circle cx="284" cy="76" r="5" fill="#0b1b33" stroke="#34d399" stroke-width="2" />
                                </g>

                                {{-- animated bars --}}
                                <g>
                                    <rect class="spf-bar" x="18" y="118" width="16" height="22" rx="4" fill="#34d399" fill-opacity="0.35" style="animation-delay:.9s" />
                                    <rect class="spf-bar" x="48" y="106" width="16" height="34" rx="4" fill="#34d399" fill-opacity="0.55" style="animation-delay:1.0s" />
                                    <rect class="spf-bar" x="78" y="92" width="16" height="48" rx="4" fill="#34d399" fill-opacity="0.75" style="animation-delay:1.1s" />
                                    <rect class="spf-bar" x="108" y="84" width="16" height="56" rx="4" fill="#a7f3d0" style="animation-delay:1.2s" />
                                    <rect class="spf-bar" x="138" y="72" width="16" height="68" rx="4" fill="#34d399" fill-opacity="0.5" style="animation-delay:1.3s" />
                                </g>

                                {{-- floating coin sparkles --}}
                                <g fill="#fbbf24" opacity="0.95">
                                    <path d="M52 26 l1.8 4.2 4.2 1.8 -4.2 1.8 L52 38 l-1.8 -4.2 -4.2 -1.8 4.2 -1.8z" style="animation: spf-pulse-dot 2.8s ease-in-out infinite" />
                                    <path d="M330 18 l1.4 3.3 3.3 1.4 -3.3 1.4 -1.4 3.3 -1.4 -3.3 -3.3 -1.4 3.3 -1.4z" style="animation: spf-pulse-dot 3.4s ease-in-out infinite .6s" />
                                    <circle cx="198" cy="22" r="2.4" style="animation: spf-pulse-dot 2.2s ease-in-out infinite .3s" />
                                </g>
                            </svg>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-xl bg-white/[0.06] p-3 ring-1 ring-white/5">
                                <div class="flex items-center gap-1.5 text-[11px] font-medium text-white/50">
                                    <flux:icon.arrow-down-left class="size-3.5 text-emerald-300" />
                                    {{ __('EMI collected') }}
                                </div>
                                <p class="mt-1 text-sm font-semibold tabular-nums text-white">₹1.84 Cr</p>
                            </div>
                            <div class="rounded-xl bg-white/[0.06] p-3 ring-1 ring-white/5">
                                <div class="flex items-center gap-1.5 text-[11px] font-medium text-white/50">
                                    <flux:icon.arrow-up-right class="size-3.5 text-amber-300" />
                                    {{ __('Disbursed') }}
                                </div>
                                <p class="mt-1 text-sm font-semibold tabular-nums text-white">₹96.4 L</p>
                            </div>
                        </div>

                        {{-- recent activity mini list --}}
                        <div class="rounded-xl bg-white/[0.04] p-3 ring-1 ring-white/5">
                            <p class="text-[11px] font-semibold text-white/70">{{ __('Recent activity') }}</p>
                            <ul class="mt-2 divide-y divide-white/5">
                                <li class="flex items-center gap-3 py-2">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-emerald-400/15 text-emerald-300"><flux:icon.arrow-down-tray class="size-3.5" /></span>
                                    <span class="min-w-0 flex-1"><span class="block truncate text-[11px] font-medium text-white/90">Rohan Mehta</span><span class="block truncate text-[10px] text-white/45">{{ __('EMI received · Personal loan') }}</span></span>
                                    <span class="text-[11px] font-semibold tabular-nums text-emerald-300">+₹8,450</span>
                                </li>
                                <li class="flex items-center gap-3 py-2">
                                    <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-amber-400/15 text-amber-300"><flux:icon.banknotes class="size-3.5" /></span>
                                    <span class="min-w-0 flex-1"><span class="block truncate text-[11px] font-medium text-white/90">Ananya Textiles</span><span class="block truncate text-[10px] text-white/45">{{ __('Loan disbursed · Business loan') }}</span></span>
                                    <span class="text-[11px] font-semibold tabular-nums text-white/80">-₹12.0 L</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- floating badges over card --}}
                <div class="absolute -right-3 -top-4 spf-float-soft">
                    <div class="flex items-center gap-2 rounded-xl border border-white/10 bg-white px-3 py-2 shadow-xl" style="animation-delay:.4s">
                        <span class="flex size-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-600"><flux:icon.check class="size-3.5" /></span>
                        <span><span class="block text-[11px] font-semibold leading-none text-navy-900">{{ __('Fully funded') }}</span><span class="mt-0.5 block text-[10px] text-zinc-500">{{ __('100% verified capital') }}</span></span>
                    </div>
                </div>
                <div class="absolute -left-2 bottom-6 spf-float-soft" style="animation-delay:.8s">
                    <div class="flex items-center gap-2 rounded-xl border border-white/10 bg-navy-950/80 px-3 py-2 shadow-xl backdrop-blur">
                        <span class="flex size-6 items-center justify-center rounded-full bg-amber-400/15 text-amber-300"><flux:icon.shield-check class="size-3.5" /></span>
                        <span><span class="block text-[11px] font-semibold leading-none text-white">{{ __('Audited ledger') }}</span><span class="mt-0.5 block text-[10px] text-white/50">{{ __('Immutable records') }}</span></span>
                    </div>
                </div>
            </div>

            {{-- stats + trust --}}
            <div class="relative z-10 mx-10 mt-8 grid grid-cols-3 gap-4 border-t border-white/10 pt-6">
                <div>
                    <p class="text-lg font-semibold tracking-tight text-white">₹250 Cr+</p>
                    <p class="mt-0.5 text-[11px] text-white/50">{{ __('Capital facilitated') }}</p>
                </div>
                <div>
                    <p class="text-lg font-semibold tracking-tight text-white">12,000+</p>
                    <p class="mt-0.5 text-[11px] text-white/50">{{ __('Families & businesses') }}</p>
                </div>
                <div>
                    <p class="text-lg font-semibold tracking-tight text-white">100%</p>
                    <p class="mt-0.5 text-[11px] text-white/50">{{ __('Funded first') }}</p>
                </div>
            </div>

            {{-- bottom wave --}}
            <svg class="pointer-events-none absolute bottom-0 left-0 h-24 w-full" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
                <path d="M0,64 C240,110 480,20 720,52 C960,84 1200,30 1440,64 L1440,120 L0,120 Z" fill="#ffffff" fill-opacity="0.06" />
                <path d="M0,80 C260,120 520,40 780,68 C1040,96 1240,50 1440,80 L1440,120 L0,120 Z" fill="#ffffff" fill-opacity="0.08" />
            </svg>
        </aside>

        {{-- Right form panel --}}
        <main class="relative flex flex-col bg-zinc-50 lg:bg-white">
            <div class="pointer-events-none absolute inset-0 lg:hidden bg-[radial-gradient(60%_40%_at_50%_0%,rgba(16,185,129,0.08),transparent_70%)]"></div>

            {{-- top bar --}}
            <div class="relative flex items-center justify-between px-6 py-5 lg:px-10">
                <a href="{{ route('home') }}" class="flex items-center gap-3 lg:hidden">
                    <x-app-logo-icon class="size-9 shrink-0" />
                    <span class="leading-none">
                        <span class="block text-lg font-semibold tracking-tight text-navy-900">{{ \App\Support\Branding::name() }}</span>
                        <span class="mt-1 block text-[8px] font-semibold uppercase tracking-[0.24em] text-slate-500">{{ __('Finance & Capital') }}</span>
                    </span>
                </a>
                <span class="hidden lg:block text-xs font-medium text-zinc-400">{{ __('Partner portal · SPF Finance & Capital') }}</span>

                <div class="flex items-center gap-3">
                    <a href="{{ route('home') }}" class="hidden items-center gap-1.5 text-sm font-medium text-zinc-600 transition hover:text-navy-900 sm:inline-flex">
                        <flux:icon.arrow-left class="size-4" />
                        {{ __('Back to site') }}
                    </a>
                    <span class="hidden h-4 w-px bg-zinc-200 sm:block"></span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-600/20 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">
                        <flux:icon.lock-closed class="size-3" />
                        256-bit SSL
                    </span>
                </div>
            </div>

            {{-- centered card --}}
            <div class="relative flex flex-1 items-center justify-center px-6 py-10 lg:px-10">
                <div class="w-full max-w-[420px]">
                    <div class="spf-fade-up rounded-3xl border border-zinc-200/90 bg-white p-7 shadow-xl shadow-navy-900/[0.06] sm:p-8">
                        {{-- header --}}
                        <div class="text-center">
                            <div class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-navy-900 text-white shadow-lg shadow-navy-900/20">
                                <flux:icon.shield-check class="size-6" />
                            </div>
                            <h2 class="mt-5 text-2xl font-semibold tracking-tight text-navy-900">{{ __('Welcome back') }}</h2>
                            <p class="mt-2 text-sm leading-relaxed text-zinc-500">{{ __('Enter your registered mobile number. We will send a one-time code to verify it is you.') }}</p>
                        </div>

                        <x-auth-session-status class="mt-4 text-center" :status="session('status')" />

                        @if ($errors->any())
                            <div class="mt-5 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">
                                <flux:icon.exclamation-triangle class="mt-0.5 size-4 shrink-0" />
                                <ul class="list-inside list-disc space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form id="login-form" method="POST" action="{{ route('login.store') }}" class="mt-6 flex flex-col gap-5" novalidate>
                            @csrf

                            <flux:input
                                name="mobile"
                                :label="__('Mobile number')"
                                :value="old('mobile')"
                                type="tel"
                                inputmode="numeric"
                                required
                                autofocus
                                autocomplete="tel-national"
                                placeholder="9876543210"
                                maxlength="10"
                                icon="device-phone-mobile"
                            />

                            <div class="flex items-center justify-between gap-4">
                                <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />
                                <span class="text-[11px] text-zinc-400">{{ __('Sessions expire in 30 min') }}</span>
                            </div>

                            <button
                                type="submit"
                                data-test="login-button"
                                class="group relative inline-flex h-11 w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-emerald-600 text-sm font-semibold text-white shadow-lg shadow-emerald-600/25 transition hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 active:scale-[0.99]"
                            >
                                <span class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/20 to-transparent transition duration-700 group-hover:translate-x-full"></span>
                                <span class="relative" data-login-label>{{ __('Send verification code') }}</span>
                                <flux:icon.arrow-right class="relative size-4 transition group-hover:translate-x-0.5" />
                            </button>

                            <div class="flex items-center gap-2 text-[11px] text-zinc-400">
                                <flux:icon.shield-check class="size-3.5 shrink-0 text-emerald-600" />
                                <span>{{ __('Protected by encryption. Never share your verification code.') }}</span>
                            </div>
                        </form>

                        <div class="mt-6 border-t border-zinc-100 pt-5 text-center text-sm text-zinc-500">
                            <span>{{ __('Need access? Contact your administrator.') }}</span>
                        </div>
                    </div>

                    {{-- trust row under card --}}
                    <div class="mt-5 grid grid-cols-3 gap-2.5">
                        @foreach ([
                            ['icon' => 'lock-closed', 'label' => __('Encrypted')],
                            ['icon' => 'shield-check', 'label' => __('Audited')],
                            ['icon' => 'document-text', 'label' => __('Transparent')],
                        ] as $badge)
                            <div class="flex items-center justify-center gap-1.5 rounded-xl border border-zinc-200 bg-white px-2 py-2.5 text-[11px] font-medium text-zinc-600">
                                <x-dynamic-component :component="'flux::icon.'.$badge['icon']" class="size-3.5 text-emerald-600" />
                                {{ $badge['label'] }}
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-6 text-center text-[11px] leading-relaxed text-zinc-400">
                        {{ __('By continuing you agree to our Terms & Privacy Policy. This portal is for Authorized Partners only.') }}
                    </p>
                </div>
            </div>

            <div class="relative flex items-center justify-between px-6 py-4 text-[11px] text-zinc-400 lg:px-10">
                <span>&copy; {{ date('Y') }} SPF Finance & Capital Pvt. Ltd.</span>
                <span class="hidden sm:inline">{{ __('Nagpur · Maharashtra · India') }}</span>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('login-form')?.addEventListener('submit', function () {
            const button = this.querySelector('button[type="submit"]');
            const label = this.querySelector('[data-login-label]');

            if (button) {
                button.disabled = true;
                button.classList.add('cursor-wait', 'opacity-70');
            }

            if (label) {
                label.textContent = @js(__('Sending code…'));
            }
        });
    </script>

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>
</html>
