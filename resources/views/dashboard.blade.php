<x-layouts::app :title="__('Dashboard')">
    @php
        $loggedInPhone = session('verified_phone') ?: auth()->user()?->mobile;
        $maskedLoggedInPhone = $loggedInPhone ? \App\Support\PhoneNumber::mask($loggedInPhone) : __('Not available');
    @endphp

    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ __('Welcome') }}</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('You are securely signed in with WhatsApp OTP verification.') }}</p>

                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-neutral-800">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Name') }}</dt>
                        <dd class="mt-1 font-medium text-slate-900 dark:text-white">{{ auth()->user()->name }}</dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-neutral-800">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Logged-in phone number') }}</dt>
                        <dd class="mt-1 font-medium text-slate-900 dark:text-white">{{ $maskedLoggedInPhone }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ __('Session') }}</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('Only authenticated users can access this dashboard. You can sign out securely below.') }}</p>

                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-500">
                        {{ __('Log out') }}
                    </button>
                </form>
            </div>
        </div>

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
        </div>
    </div>
</x-layouts::app>
