<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('partials.head', ['title' => __('SPF — Loans built on real capital')])
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased selection:bg-emerald-500/20">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-navy-900 focus:px-4 focus:py-2 focus:text-sm font-medium focus:text-white">
            {{ __('Skip to content') }}
        </a>

        <header class="sticky top-0 z-50 border-b border-zinc-200/80 bg-white/85 backdrop-blur-xl">
            <div class="mx-auto flex h-[72px] max-w-7xl items-center justify-between gap-6 px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="SPF Finance & Capital home">
                    <svg class="size-10 shrink-0 drop-shadow-sm" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect width="44" height="44" rx="13" fill="#0b1b33" />
                        <rect x="0.75" y="0.75" width="42.5" height="42.5" rx="12.25" stroke="#34d399" stroke-opacity="0.35" stroke-width="1.5" />
                        <path d="M9.5 22.5 16.5 16l5 3.5L32 9.5" stroke="#fbbf24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M26.5 9.5H32V15" stroke="#fbbf24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" />
                        <rect x="10" y="25" width="5" height="8.5" rx="1.6" fill="#34d399" fill-opacity="0.55" />
                        <rect x="17.5" y="20" width="5" height="13.5" rx="1.6" fill="#34d399" fill-opacity="0.8" />
                        <rect x="25" y="14" width="5" height="19.5" rx="1.6" fill="#a7f3d0" />
                    </svg>
                    <span class="leading-none">
                        <span class="block text-xl font-semibold tracking-tight text-navy-900">SPF</span>
                        <span class="mt-1 block text-[9px] font-semibold uppercase tracking-[0.24em] text-slate-500">{{ __('Finance & Capital') }}</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-8 text-sm font-medium text-zinc-600 md:flex">
                    <a href="#loans" class="transition hover:text-navy-900">{{ __('Loan products') }}</a>
                    <a href="#how-it-works" class="transition hover:text-navy-900">{{ __('How it works') }}</a>
                    <a href="#why-spf" class="transition hover:text-navy-900">{{ __('Why SPF') }}</a>
                    <a href="#contact" class="transition hover:text-navy-900">{{ __('Contact') }}</a>
                </nav>

                <div class="hidden items-center gap-3 md:flex">
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                            {{ __('Go to dashboard') }}
                            <flux:icon.arrow-right class="size-4" />
                        </a>
                    @else
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-zinc-700 transition hover:text-navy-900">
                                {{ __('Partner login') }}
                            </a>
                        @endif

                        <a href="#contact" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-600/25 transition hover:bg-emerald-500">
                            {{ __('Apply now') }}
                            <flux:icon.arrow-right class="size-4" />
                        </a>
                    @endauth
                </div>

                <details class="group relative md:hidden">
                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-lg border border-zinc-200 text-zinc-700">
                        <flux:icon.bars-2 class="size-5 group-open:hidden" />
                        <flux:icon.x-mark class="hidden size-5 group-open:block" />
                        <span class="sr-only">{{ __('Toggle navigation') }}</span>
                    </summary>

                    <div class="absolute end-0 top-12 w-60 rounded-xl border border-zinc-200 bg-white p-2 shadow-xl" onclick="if (event.target.closest('a')) this.parentElement.open = false">
                        <a href="#loans" class="block rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">{{ __('Loan products') }}</a>
                        <a href="#how-it-works" class="block rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">{{ __('How it works') }}</a>
                        <a href="#why-spf" class="block rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">{{ __('Why SPF') }}</a>
                        <a href="#contact" class="block rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">{{ __('Contact') }}</a>

                        <div class="mt-2 border-t border-zinc-200 pt-2">
                            @auth
                                <a href="{{ route('dashboard') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-emerald-700">{{ __('Go to dashboard') }}</a>
                            @else
                                @if (Route::has('login'))
                                    <a href="{{ route('login') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50">{{ __('Partner login') }}</a>
                                @endif

                                <a href="#contact" class="block rounded-lg px-3 py-2 text-sm font-semibold text-emerald-700">{{ __('Apply now') }}</a>
                            @endauth
                        </div>
                    </div>
                </details>
            </div>
        </header>

        <main id="main">
            {{-- Hero --}}
            <section class="relative overflow-hidden">
                <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(60%_50%_at_40%_-10%,rgba(16,185,129,0.14),transparent_70%),radial-gradient(35%_40%_at_100%_0%,rgba(251,191,36,0.10),transparent_70%)]"></div>
                <div class="pointer-events-none absolute -start-32 top-64 -z-10 size-96 rounded-full bg-emerald-400/10 blur-3xl"></div>

                <div class="mx-auto grid max-w-7xl items-center gap-16 px-6 pb-20 pt-16 lg:grid-cols-[1.05fr_1fr] lg:gap-12 lg:px-8 lg:pb-28 lg:pt-24">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full border border-emerald-600/20 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                            <span class="relative flex size-1.5">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-500 opacity-75"></span>
                                <span class="relative inline-flex size-1.5 rounded-full bg-emerald-500"></span>
                            </span>
                            {{ __('Deposits-first lending · Built for India') }}
                        </div>

                        <h1 class="mt-6 text-5xl font-semibold tracking-tight text-balance text-navy-900 sm:text-6xl">
                            {{ __('Credit that moves') }}
                            <span class="text-emerald-600">{{ __('India forward.') }}</span>
                        </h1>

                        <p class="mt-6 max-w-xl text-lg leading-relaxed text-zinc-600">
                            {{ __('SPF brings personal, business and secured loans together on one transparent platform — funded entirely by cleared investor capital, with every rupee accounted for before disbursal.') }}
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <a href="#loans" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-600/25 transition hover:bg-emerald-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                                {{ __('Explore loan products') }}
                                <flux:icon.arrow-right class="size-4" />
                            </a>

                            <a href="#how-it-works" class="inline-flex items-center justify-center gap-2 rounded-xl border border-zinc-200 bg-white px-6 py-3 text-sm font-semibold text-zinc-800 shadow-sm transition hover:border-zinc-300 hover:bg-zinc-50">
                                <flux:icon.play-circle class="size-4" />
                                {{ __('See how it works') }}
                            </a>
                        </div>

                        <dl class="mt-10 grid max-w-lg grid-cols-3 gap-6 border-t border-zinc-200 pt-6">
                            <div>
                                <dt class="text-xs font-medium text-zinc-500">{{ __('Capital facilitated') }}</dt>
                                <dd class="mt-1 text-xl font-semibold tracking-tight text-navy-900">₹250 Cr+</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-zinc-500">{{ __('Families & businesses') }}</dt>
                                <dd class="mt-1 text-xl font-semibold tracking-tight text-navy-900">12,000+</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-zinc-500">{{ __('Fully funded first') }}</dt>
                                <dd class="mt-1 text-xl font-semibold tracking-tight text-navy-900">100%</dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Portfolio preview --}}
                    <div class="relative mx-auto w-full max-w-lg lg:max-w-none">
                        <div class="absolute inset-x-6 -bottom-6 -z-10 h-40 rounded-3xl bg-emerald-500/10 blur-2xl"></div>

                        <div class="overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-2xl shadow-navy-900/10">
                            <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="size-2.5 rounded-full bg-rose-400"></span>
                                    <span class="size-2.5 rounded-full bg-amber-400"></span>
                                    <span class="size-2.5 rounded-full bg-emerald-400"></span>
                                </div>
                                <div class="flex items-center gap-1.5 rounded-md bg-zinc-100 px-2 py-1 text-[11px] font-medium text-zinc-500">
                                    <flux:icon.lock-closed class="size-3" />
                                    spf.finance/portfolio
                                </div>
                                <flux:icon.ellipsis-horizontal class="size-4 text-zinc-400" />
                            </div>

                            <div class="space-y-5 p-5 sm:p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-medium text-zinc-500">{{ __('Active loan book') }}</p>
                                        <p class="mt-1 text-3xl font-semibold tracking-tight tabular-nums text-navy-900">₹24.8 Cr</p>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 px-2.5 py-1.5 text-[11px] font-semibold text-zinc-600">
                                        {{ __('This quarter') }}
                                        <flux:icon.chevron-down class="size-3" />
                                    </span>
                                </div>

                                <div class="flex items-center gap-2 text-xs">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700">
                                        <flux:icon.arrow-trending-up class="size-3" />
                                        +12.4%
                                    </span>
                                    <span class="text-zinc-500">{{ __('vs. last quarter') }}</span>
                                </div>

                                <div class="relative -mx-1">
                                    <svg viewBox="0 0 400 120" fill="none" class="h-24 w-full" preserveAspectRatio="none" aria-hidden="true">
                                        <defs>
                                            <linearGradient id="spf-book-fill" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="#10b981" stop-opacity="0.28" />
                                                <stop offset="100%" stop-color="#10b981" stop-opacity="0" />
                                            </linearGradient>
                                        </defs>
                                        <path d="M0 96C24 92 34 74 58 76C82 78 92 96 116 88C140 80 148 44 172 46C196 48 206 70 230 62C254 54 262 26 286 24C310 22 322 44 346 36C370 28 382 10 400 6V120H0Z" fill="url(#spf-book-fill)" />
                                        <path d="M0 96C24 92 34 74 58 76C82 78 92 96 116 88C140 80 148 44 172 46C196 48 206 70 230 62C254 54 262 26 286 24C310 22 322 44 346 36C370 28 382 10 400 6" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" />
                                    </svg>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-xl bg-zinc-50 p-3">
                                        <div class="flex items-center gap-1.5 text-[11px] font-medium text-zinc-500">
                                            <flux:icon.arrow-down-left class="size-3.5 text-emerald-500" />
                                            {{ __('EMI collected') }}
                                        </div>
                                        <p class="mt-1 text-sm font-semibold tabular-nums text-navy-900">₹1.84 Cr</p>
                                    </div>
                                    <div class="rounded-xl bg-zinc-50 p-3">
                                        <div class="flex items-center gap-1.5 text-[11px] font-medium text-zinc-500">
                                            <flux:icon.arrow-up-right class="size-3.5 text-amber-500" />
                                            {{ __('Disbursed') }}
                                        </div>
                                        <p class="mt-1 text-sm font-semibold tabular-nums text-navy-900">₹96.4 L</p>
                                    </div>
                                </div>

                                <div>
                                    <div class="mb-2 flex items-center justify-between">
                                        <p class="text-xs font-semibold text-zinc-700">{{ __('Recent activity') }}</p>
                                        <span class="text-[11px] font-medium text-emerald-600">{{ __('View all') }}</span>
                                    </div>

                                    @php
                                        $activity = [
                                            ['name' => 'Rohan Mehta', 'meta' => __('EMI received · Personal loan'), 'amount' => '+₹8,450', 'positive' => true, 'icon' => 'arrow-down-tray'],
                                            ['name' => 'Ananya Textiles', 'meta' => __('Loan disbursed · Business loan'), 'amount' => '-₹12,00,000', 'positive' => false, 'icon' => 'banknotes'],
                                            ['name' => 'Investor deposit', 'meta' => __('Cleared · NEFT'), 'amount' => '+₹25,00,000', 'positive' => true, 'icon' => 'building-library'],
                                        ];
                                    @endphp

                                    <ul class="divide-y divide-zinc-100">
                                        @foreach ($activity as $item)
                                            <li class="flex items-center gap-3 py-2.5">
                                                <span @class([
                                                    'flex size-8 shrink-0 items-center justify-center rounded-lg',
                                                    'bg-emerald-50 text-emerald-600' => $item['positive'],
                                                    'bg-amber-50 text-amber-600' => ! $item['positive'],
                                                ])>
                                                    <x-dynamic-component :component="'flux::icon.'.$item['icon']" class="size-4" />
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-xs font-medium text-zinc-800">{{ $item['name'] }}</span>
                                                    <span class="block truncate text-[11px] text-zinc-500">{{ $item['meta'] }}</span>
                                                </span>
                                                <span @class([
                                                    'text-xs font-semibold tabular-nums',
                                                    'text-emerald-600' => $item['positive'],
                                                    'text-zinc-700' => ! $item['positive'],
                                                ])>{{ $item['amount'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- Trust strip --}}
                <div class="border-y border-zinc-200/70 bg-zinc-50/70">
                    <div class="mx-auto grid max-w-7xl gap-6 px-6 py-7 sm:grid-cols-2 lg:grid-cols-4 lg:px-8">
                        @foreach ([
                            ['icon' => 'banknotes', 'title' => __('Cleared capital only'), 'copy' => __('No lending against pending funds')],
                            ['icon' => 'shield-check', 'title' => __('100% funded first'), 'copy' => __('No partial disbursements, ever')],
                            ['icon' => 'document-text', 'title' => __('Transparent terms'), 'copy' => __('Every fee disclosed upfront')],
                            ['icon' => 'lock-closed', 'title' => __('Immutable records'), 'copy' => __('Audited at every step')],
                        ] as $item)
                            <div class="flex items-center gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-emerald-600 ring-1 ring-zinc-200">
                                    <x-dynamic-component :component="'flux::icon.'.$item['icon']" class="size-4" />
                                </span>
                                <span>
                                    <span class="block text-xs font-semibold text-navy-900">{{ $item['title'] }}</span>
                                    <span class="block text-[11px] text-zinc-500">{{ $item['copy'] }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Loan products --}}
            <section id="loans" class="scroll-mt-24 py-20 lg:py-28">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="max-w-2xl">
                        <p class="text-sm font-semibold text-emerald-600">{{ __('Loan products') }}</p>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-balance text-navy-900 sm:text-4xl">
                            {{ __('The right loan for every milestone.') }}
                        </h2>
                        <p class="mt-4 text-lg leading-relaxed text-zinc-600">
                            {{ __('From a wedding at home to a new production line, SPF offers lending products built around how India earns, builds and grows.') }}
                        </p>
                    </div>

                    <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @php
                            $products = [
                                ['icon' => 'user-group', 'name' => __('Personal loan'), 'copy' => __('For weddings, education, travel or a medical need — with a simple EMI plan.'), 'limit' => __('Up to ₹10 lakh'), 'rate' => __('From 11.50% p.a.'), 'tenure' => __('12–60 months'), 'tone' => 'emerald'],
                                ['icon' => 'briefcase', 'name' => __('Business loan'), 'copy' => __('Working capital, new equipment or expansion — without giving up ownership.'), 'limit' => __('Up to ₹50 lakh'), 'rate' => __('From 13.00% p.a.'), 'tenure' => __('12–48 months'), 'tone' => 'blue'],
                                ['icon' => 'home-modern', 'name' => __('Home loan'), 'copy' => __('Buy your first home, build on your plot or renovate the one you have.'), 'limit' => __('Up to ₹1 crore'), 'rate' => __('From 8.75% p.a.'), 'tenure' => __('Up to 20 years'), 'tone' => 'amber'],
                                ['icon' => 'building-office-2', 'name' => __('Loan against property'), 'copy' => __('Unlock the value of residential or commercial property for a large expense.'), 'limit' => __('Up to ₹75 lakh'), 'rate' => __('From 10.25% p.a.'), 'tenure' => __('Up to 15 years'), 'tone' => 'emerald'],
                                ['icon' => 'currency-rupee', 'name' => __('Gold loan'), 'copy' => __('Quick funds against your gold, with transparent valuation and secure storage.'), 'limit' => __('Up to ₹25 lakh'), 'rate' => __('From 9.50% p.a.'), 'tenure' => __('3–24 months'), 'tone' => 'amber'],
                                ['icon' => 'academic-cap', 'name' => __('Professional loan'), 'copy' => __('For doctors, chartered accountants and founders investing in their practice.'), 'limit' => __('Up to ₹40 lakh'), 'rate' => __('From 12.25% p.a.'), 'tenure' => __('12–60 months'), 'tone' => 'blue'],
                            ];

                            $toneClasses = [
                                'emerald' => 'bg-emerald-50 text-emerald-600 ring-emerald-600/10',
                                'blue' => 'bg-blue-50 text-blue-600 ring-blue-600/10',
                                'amber' => 'bg-amber-50 text-amber-600 ring-amber-600/10',
                            ];
                        @endphp

                        @foreach ($products as $product)
                            <div class="group flex flex-col rounded-2xl border border-zinc-200 bg-white p-6 transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-xl hover:shadow-emerald-900/5">
                                <span class="flex size-11 items-center justify-center rounded-xl ring-1 {{ $toneClasses[$product['tone']] }}">
                                    <x-dynamic-component :component="'flux::icon.'.$product['icon']" class="size-5" />
                                </span>

                                <h3 class="mt-5 text-base font-semibold text-navy-900">{{ $product['name'] }}</h3>
                                <p class="mt-2 flex-1 text-sm leading-relaxed text-zinc-600">{{ $product['copy'] }}</p>

                                <dl class="mt-5 grid grid-cols-2 gap-3 rounded-xl bg-zinc-50 p-3.5">
                                    <div>
                                        <dt class="text-[10px] font-medium uppercase tracking-wide text-zinc-400">{{ __('Limit') }}</dt>
                                        <dd class="mt-0.5 text-xs font-semibold text-navy-900">{{ $product['limit'] }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-medium uppercase tracking-wide text-zinc-400">{{ __('Rate') }}</dt>
                                        <dd class="mt-0.5 text-xs font-semibold text-navy-900">{{ $product['rate'] }}</dd>
                                    </div>
                                    <div class="col-span-2 border-t border-zinc-200/70 pt-2.5">
                                        <dt class="text-[10px] font-medium uppercase tracking-wide text-zinc-400">{{ __('Tenure') }}</dt>
                                        <dd class="mt-0.5 text-xs font-semibold text-navy-900">{{ $product['tenure'] }}</dd>
                                    </div>
                                </dl>

                                <a href="mailto:loans@spf.finance?subject={{ urlencode($product['name'].' enquiry') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700 transition group-hover:gap-2.5">
                                    {{ __('Apply for this loan') }}
                                    <flux:icon.arrow-right class="size-4" />
                                </a>
                            </div>
                        @endforeach
                    </div>

                    <p class="mt-8 flex items-start gap-2 text-xs leading-relaxed text-zinc-500">
                        <flux:icon.information-circle class="mt-0.5 size-4 shrink-0" />
                        {{ __('Interest rates shown are indicative and depend on credit assessment, loan tenure and profile. All loans are subject to document verification and applicable terms.') }}
                    </p>
                </div>
            </section>

            {{-- How it works --}}
            <section id="how-it-works" class="scroll-mt-24 border-y border-zinc-200/70 bg-zinc-50/70 py-20 lg:py-28">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <p class="text-sm font-semibold text-emerald-600">{{ __('How it works') }}</p>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-balance text-navy-900 sm:text-4xl">
                            {{ __('From application to disbursal, in four clear steps.') }}
                        </h2>
                        <p class="mt-4 text-lg leading-relaxed text-zinc-600">
                            {{ __('No hidden stages, no surprise deductions. You can see exactly where your loan is — and what happens next.') }}
                        </p>
                    </div>

                    <div class="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        @foreach ([
                            ['step' => '01', 'icon' => 'document-text', 'title' => __('Apply in minutes'), 'copy' => __('Tell us what you need and share basic KYC details. No paperwork marathon.')],
                            ['step' => '02', 'icon' => 'calculator', 'title' => __('Get a clear offer'), 'copy' => __('Receive a personalised amount, rate and EMI schedule with every charge disclosed.')],
                            ['step' => '03', 'icon' => 'shield-check', 'title' => __('Capital verified'), 'copy' => __('We match your loan with cleared investor capital and reserve 100% before approval.')],
                            ['step' => '04', 'icon' => 'banknotes', 'title' => __('Money in your account'), 'copy' => __('Once fully funded and approved, the amount is paid to your verified bank account.')],
                        ] as $step)
                            <div class="relative rounded-2xl border border-zinc-200 bg-white p-6">
                                <span class="absolute end-5 top-5 text-2xl font-semibold tabular-nums text-zinc-100">{{ $step['step'] }}</span>
                                <span class="flex size-10 items-center justify-center rounded-xl bg-navy-900 text-white">
                                    <x-dynamic-component :component="'flux::icon.'.$step['icon']" class="size-5" />
                                </span>
                                <h3 class="mt-5 text-sm font-semibold text-navy-900">{{ $step['title'] }}</h3>
                                <p class="mt-2 text-xs leading-relaxed text-zinc-600">{{ $step['copy'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 flex flex-col items-start justify-between gap-4 rounded-2xl border border-emerald-600/15 bg-emerald-50/70 px-5 py-4 sm:flex-row sm:items-center">
                        <div class="flex items-start gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white text-emerald-600 ring-1 ring-emerald-600/15">
                                <flux:icon.lock-closed class="size-4" />
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-navy-900">{{ __('No partial disbursements. Not even close.') }}</p>
                                <p class="mt-0.5 text-xs leading-relaxed text-zinc-600">{{ __('Every rupee is backed by cleared investor capital before it reaches your account.') }}</p>
                            </div>
                        </div>
                        <a href="#why-spf" class="inline-flex shrink-0 items-center gap-2 text-sm font-semibold text-emerald-700 transition hover:gap-3">
                            {{ __('Why this matters') }}
                            <flux:icon.arrow-right class="size-4" />
                        </a>
                    </div>
                </div>
            </section>

            {{-- Why SPF --}}
            <section id="why-spf" class="scroll-mt-24 py-20 lg:py-28">
                <div class="mx-auto grid max-w-7xl items-center gap-14 px-6 lg:grid-cols-2 lg:gap-20 lg:px-8">
                    <div>
                        <p class="text-sm font-semibold text-emerald-600">{{ __('Why SPF') }}</p>
                        <h2 class="mt-3 text-3xl font-semibold tracking-tight text-balance text-navy-900 sm:text-4xl">
                            {{ __('Lending built on real money, not assumptions.') }}
                        </h2>
                        <p class="mt-4 text-lg leading-relaxed text-zinc-600">
                            {{ __('Most lending problems start with money that is not really there. Our deposits-first model means every loan is funded by cleared capital and tracked against a single, auditable ledger.') }}
                        </p>

                        <ul class="mt-8 space-y-5">
                            @foreach ([
                                ['title' => __('Deposits-first capital model'), 'copy' => __('Loans are funded only from cleared investor deposits — never from pending or assumed money.')],
                                ['title' => __('Exact, loan-wise allocation'), 'copy' => __('Capital is reserved against your loan, rupee for rupee, with no double allocation.')],
                                ['title' => __('Transparent EMI schedules'), 'copy' => __('Flat or reducing-balance interest, fees disclosed upfront and a full repayment calendar.')],
                                ['title' => __('Audited, immutable records'), 'copy' => __('Every deposit, allocation, disbursal and EMI collection is written to an immutable ledger.')],
                            ] as $item)
                                <li class="flex items-start gap-3">
                                    <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white">
                                        <flux:icon.check class="size-3" />
                                    </span>
                                    <span>
                                        <span class="block text-sm font-semibold text-navy-900">{{ $item['title'] }}</span>
                                        <span class="mt-1 block text-sm leading-relaxed text-zinc-600">{{ $item['copy'] }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        <a href="#contact" class="mt-8 inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 transition hover:gap-3">
                            {{ __('Talk to our lending team') }}
                            <flux:icon.arrow-right class="size-4" />
                        </a>
                    </div>

                    <div class="relative">
                        <div class="absolute inset-x-8 -bottom-6 -z-10 h-40 rounded-3xl bg-emerald-500/10 blur-2xl"></div>

                        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl shadow-navy-900/5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-medium text-zinc-500">{{ __('Loan processing center') }}</p>
                                    <p class="mt-1 text-sm font-semibold text-navy-900">LN-000128</p>
                                </div>
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                    <flux:icon.check-badge class="size-3.5" />
                                    {{ __('Fully funded') }}
                                </span>
                            </div>

                            <div class="mt-5 space-y-2.5">
                                @foreach ([
                                    ['label' => __('Application submitted'), 'meta' => __('KYC & income verified')],
                                    ['label' => __('Offer generated'), 'meta' => __('₹5,00,000 · 12.5% p.a.')],
                                    ['label' => __('Borrower accepted'), 'meta' => __('Terms locked as a snapshot')],
                                    ['label' => __('Investor capital verified'), 'meta' => __('Cleared deposits available')],
                                ] as $row)
                                    <div class="flex items-center gap-3 rounded-xl border border-zinc-100 p-3">
                                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                            <flux:icon.check class="size-3.5" />
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-xs font-medium text-navy-900">{{ $row['label'] }}</span>
                                            <span class="block truncate text-[11px] text-zinc-500">{{ $row['meta'] }}</span>
                                        </span>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-4 rounded-xl bg-navy-900 p-4 text-white">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-white/50">{{ __('Funding confirmation') }}</p>
                                        <p class="mt-1 text-sm font-semibold tabular-nums">₹5,00,000 / ₹5,00,000</p>
                                    </div>
                                    <span class="text-xs font-semibold text-emerald-300">100%</span>
                                </div>
                                <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-white/10">
                                    <div class="h-full w-full rounded-full bg-emerald-400"></div>
                                </div>
                                <div class="mt-3 flex items-center gap-2 text-[10px] font-medium text-emerald-300">
                                    <flux:icon.lock-closed class="size-3" />
                                    {{ __('Funding locked · approved for disbursal') }}
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between border-t border-zinc-100 pt-4">
                                <span class="text-[11px] text-zinc-500">{{ __('Disbursal status') }}</span>
                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600">
                                    <flux:icon.check class="size-3.5" />
                                    {{ __('Ready') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- CTA --}}
            <section id="contact" class="scroll-mt-24 py-20 lg:py-24">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div class="relative overflow-hidden rounded-3xl bg-navy-900 px-8 py-14 shadow-2xl sm:px-16">
                        <div class="pointer-events-none absolute -left-24 -top-24 size-72 rounded-full bg-emerald-500/20 blur-3xl"></div>
                        <div class="pointer-events-none absolute -bottom-32 -right-16 size-80 rounded-full bg-amber-400/15 blur-3xl"></div>

                        <div class="relative grid gap-10 lg:grid-cols-[1.1fr_1fr] lg:items-center">
                            <div>
                                <p class="text-sm font-semibold text-emerald-400">{{ __('Let’s get started') }}</p>
                                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-white text-balance sm:text-4xl">
                                    {{ __('Let’s fund your next milestone.') }}
                                </h2>
                                <p class="mt-4 max-w-xl text-base leading-relaxed text-white/65">
                                    {{ __('Tell us what you are planning. Our lending team will help you choose the right product, understand the EMI and walk you through every document.') }}
                                </p>

                                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                                    <a href="mailto:loans@spf.finance?subject=Loan%20application%20enquiry" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-400">
                                        {{ __('Apply for a loan') }}
                                        <flux:icon.arrow-right class="size-4" />
                                    </a>

                                    @if (Route::has('login'))
                                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/15 bg-white/5 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                                            {{ __('Partner login') }}
                                        </a>
                                    @endif
                                </div>
                            </div>

                            <div class="grid gap-3">
                                @foreach ([
                                    ['icon' => 'phone', 'label' => __('Call us'), 'value' => '+91 9172 007 007', 'href' => 'tel:+919172007007'],
                                    ['icon' => 'envelope', 'label' => __('Email'), 'value' => 'loans@spf.finance', 'href' => 'mailto:loans@spf.finance'],
                                    ['icon' => 'map-pin', 'label' => __('Visit us'), 'value' => __('Nagpur, Maharashtra 441111'), 'href' => null],
                                ] as $contact)
                                    <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 p-4">
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-emerald-300">
                                            <x-dynamic-component :component="'flux::icon.'.$contact['icon']" class="size-4" />
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-[10px] font-medium uppercase tracking-wider text-white/40">{{ $contact['label'] }}</span>
                                            @if ($contact['href'])
                                                <a href="{{ $contact['href'] }}" class="block truncate text-sm font-semibold text-white transition hover:text-emerald-300">{{ $contact['value'] }}</a>
                                            @else
                                                <span class="block text-sm font-semibold text-white">{{ $contact['value'] }}</span>
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-zinc-200/70 bg-white py-12">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="lg:col-span-2">
                        <div class="flex items-center gap-3">
                            <svg class="size-9 shrink-0" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <rect width="44" height="44" rx="13" fill="#0b1b33" />
                                <path d="M9.5 22.5 16.5 16l5 3.5L32 9.5" stroke="#fbbf24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M26.5 9.5H32V15" stroke="#fbbf24" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" />
                                <rect x="10" y="25" width="5" height="8.5" rx="1.6" fill="#34d399" fill-opacity="0.55" />
                                <rect x="17.5" y="20" width="5" height="13.5" rx="1.6" fill="#34d399" fill-opacity="0.8" />
                                <rect x="25" y="14" width="5" height="19.5" rx="1.6" fill="#a7f3d0" />
                            </svg>
                            <span class="leading-none">
                                <span class="block text-lg font-semibold tracking-tight text-navy-900">SPF</span>
                                <span class="mt-1 block text-[9px] font-semibold uppercase tracking-[0.24em] text-slate-500">{{ __('Finance & Capital') }}</span>
                            </span>
                        </div>
                        <p class="mt-4 max-w-xs text-sm leading-relaxed text-zinc-500">
                            {{ __('Personal, business and secured lending built on cleared investor capital — transparent from application to closure.') }}
                        </p>
                    </div>

                    @foreach ([
                        __('Loans') => [__('Personal loan'), __('Business loan'), __('Home loan'), __('Loan against property'), __('Gold loan')],
                        __('Company') => [__('About SPF'), __('Why SPF'), __('How it works'), __('Contact')],
                        __('Support') => [__('FAQs'), __('Grievance redressal'), __('Privacy policy'), __('Terms & conditions')],
                    ] as $heading => $links)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">{{ $heading }}</p>
                            <ul class="mt-4 space-y-2.5">
                                @foreach ($links as $link)
                                    <li>
                                        <a href="#contact" class="text-sm text-zinc-600 transition hover:text-navy-900">{{ $link }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>

                <div class="mt-10 border-t border-zinc-200 pt-6">
                    <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                        <p class="text-xs text-zinc-500">
                            &copy; {{ date('Y') }} SPF Finance & Capital Pvt. Ltd. {{ __('All rights reserved.') }}
                        </p>
                        <div class="flex items-center gap-4 text-xs text-zinc-500">
                            <a href="#contact" class="transition hover:text-navy-900">{{ __('Privacy') }}</a>
                            <a href="#contact" class="transition hover:text-navy-900">{{ __('Terms') }}</a>
                            <a href="#contact" class="transition hover:text-navy-900">{{ __('Grievance redressal') }}</a>
                        </div>
                    </div>
                    <p class="mt-4 max-w-3xl text-[11px] leading-relaxed text-zinc-400">
                        {{ __('Loans are subject to credit assessment, document verification and applicable terms. Interest rates, limits and tenures shown are indicative and may vary by profile.') }}
                    </p>
                </div>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
