@php
    $settingsHome = auth()->user()->isSuperAdmin() ? route('super-admin.settings') : route('admin.settings');
    $inputClass = fn (bool $invalid): string => 'h-11 w-full rounded-xl border bg-white px-4 pe-12 text-sm text-zinc-800 shadow-xs transition placeholder-zinc-400 hover:border-zinc-300 focus:border-navy-600 focus:outline-none focus:ring-4 focus:ring-navy-600/10 '
        .($invalid ? 'border-red-400 hover:border-red-400' : 'border-zinc-200');
    $toggleClass = 'absolute end-1.5 top-1/2 flex size-8 -translate-y-1/2 items-center justify-center rounded-lg text-zinc-400 transition hover:bg-slate-100 hover:text-navy-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-600/30';
@endphp

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    {{-- Card header --}}
    <div class="flex flex-col gap-4 border-b border-slate-100 bg-slate-50/50 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div class="flex items-center gap-3.5">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-navy-900 text-cyber-400 shadow-md shadow-navy-900/20">
                <flux:icon.lock-closed class="size-5" />
            </span>
            <div>
                <h2 class="text-base font-semibold tracking-tight text-slate-900">{{ __('Change password') }}</h2>
                <p class="mt-0.5 text-xs text-slate-500">{{ __('Protect your account with a long, unique password.') }}</p>
            </div>
        </div>
        <div class="inline-flex w-fit items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-[11px] font-medium text-slate-500 ring-1 ring-slate-200">
            <flux:icon.clock class="size-3.5 text-slate-400" />
            {{ __('Last changed') }}
            <span class="font-semibold text-slate-700">{{ auth()->user()->password_changed_at?->diffForHumans() ?? __('not recorded') }}</span>
        </div>
    </div>

    <form method="POST" action="{{ route('security.password.update') }}" autocomplete="off"
          class="grid gap-0 lg:grid-cols-5"
          x-data="{
              showCurrent: false,
              showPassword: false,
              showConfirmation: false,
              password: '',
              get checks() {
                  const p = this.password;
                  return [
                      { label: 'At least 12 characters', met: p.length >= 12 },
                      { label: 'Upper and lower case letters', met: /[a-z]/.test(p) && /[A-Z]/.test(p) },
                      { label: 'At least one number', met: /\d/.test(p) },
                      { label: 'At least one symbol', met: /[^A-Za-z0-9]/.test(p) },
                  ];
              },
              get score() {
                  if (!this.password) return 0;
                  return this.checks.filter((c) => c.met).length + (this.password.length >= 16 ? 1 : 0);
              },
              get strengthLabel() {
                  return ['Too weak', 'Weak', 'Fair', 'Good', 'Strong', 'Excellent'][this.score] ?? 'Too weak';
              },
              get strengthColor() {
                  return ['bg-slate-200', 'bg-red-500', 'bg-amber-500', 'bg-yellow-500', 'bg-lime-500', 'bg-emerald-500'][this.score];
              },
              get strengthText() {
                  return ['text-slate-400', 'text-red-600', 'text-amber-600', 'text-yellow-600', 'text-lime-600', 'text-emerald-600'][this.score];
              },
          }">
        @csrf
        @method('PUT')

        {{-- Form column --}}
        <div class="p-5 sm:p-6 lg:col-span-3">
            <div class="grid gap-5">
                {{-- Current password --}}
                <div>
                    <label for="current_password" class="mb-1.5 block text-xs font-semibold text-slate-600">{{ __('Current password') }}</label>
                    <div class="relative">
                        <input :type="showCurrent ? 'text' : 'password'" name="current_password" id="current_password"
                               required autocomplete="current-password" aria-invalid="{{ $errors->has('current_password') ? 'true' : 'false' }}"
                               class="{{ $inputClass($errors->has('current_password')) }}" placeholder="{{ __('Enter your current password') }}" />
                        <button type="button" class="{{ $toggleClass }}" @click="showCurrent = !showCurrent"
                                :aria-label="showCurrent ? '{{ __('Hide current password') }}' : '{{ __('Show current password') }}'" :aria-pressed="showCurrent">
                            <flux:icon.eye class="size-4" x-show="!showCurrent" />
                            <flux:icon.eye-slash class="size-4" x-show="showCurrent" x-cloak />
                        </button>
                    </div>
                    @error('current_password')
                        <p class="mt-2 flex items-center gap-1.5 text-xs font-medium text-red-600" role="alert">
                            <flux:icon.exclamation-circle class="size-4 shrink-0" />
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="h-px bg-slate-100" aria-hidden="true"></div>

                {{-- New password --}}
                <div>
                    <label for="password" class="mb-1.5 block text-xs font-semibold text-slate-600">{{ __('New password') }}</label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" name="password" id="password"
                               required autocomplete="new-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                               x-model="password" maxlength="255"
                               passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                               class="{{ $inputClass($errors->has('password')) }}" placeholder="{{ __('Enter a new password') }}" />
                        <button type="button" class="{{ $toggleClass }}" @click="showPassword = !showPassword"
                                :aria-label="showPassword ? '{{ __('Hide new password') }}' : '{{ __('Show new password') }}'" :aria-pressed="showPassword">
                            <flux:icon.eye class="size-4" x-show="!showPassword" />
                            <flux:icon.eye-slash class="size-4" x-show="showPassword" x-cloak />
                        </button>
                    </div>

                    {{-- Strength meter --}}
                    <div class="mt-3" x-show="password.length > 0" x-cloak>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ __('Password strength') }}</span>
                            <span class="text-[11px] font-semibold" :class="strengthText" x-text="strengthLabel" role="status"></span>
                        </div>
                        <div class="mt-1.5 flex h-1.5 gap-1" role="progressbar" aria-valuemin="0" aria-valuemax="5" :aria-valuenow="score" aria-label="{{ __('Password strength') }}">
                            @for ($i = 1; $i <= 5; $i++)
                                <span class="h-full flex-1 rounded-full transition-colors duration-300" :class="score >= {{ $i }} ? strengthColor : 'bg-slate-200'"></span>
                            @endfor
                        </div>
                    </div>

                    {{-- Requirements checklist --}}
                    <ul class="mt-3 grid gap-2 text-[11px] text-slate-500 sm:grid-cols-2">
                        <template x-for="(check, index) in checks" :key="index">
                            <li class="flex items-center gap-2">
                                <span class="flex size-4 shrink-0 items-center justify-center rounded-full transition-colors"
                                      :class="check.met ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-300'">
                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="size-3">
                                        <path d="M16.7 6L8.3 14.4 3.3 9.4" />
                                    </svg>
                                </span>
                                <span :class="check.met ? 'font-medium text-emerald-700' : ''" x-text="check.label"></span>
                            </li>
                        </template>
                    </ul>

                    @error('password')
                        <p class="mt-2 flex items-center gap-1.5 text-xs font-medium text-red-600" role="alert">
                            <flux:icon.exclamation-circle class="size-4 shrink-0" />
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Confirm new password --}}
                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-xs font-semibold text-slate-600">{{ __('Confirm new password') }}</label>
                    <div class="relative">
                        <input :type="showConfirmation ? 'text' : 'password'" name="password_confirmation" id="password_confirmation"
                               required autocomplete="new-password" aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}"
                               maxlength="255"
                               class="{{ $inputClass($errors->has('password_confirmation')) }}" placeholder="{{ __('Re-enter the new password') }}" />
                        <button type="button" class="{{ $toggleClass }}" @click="showConfirmation = !showConfirmation"
                                :aria-label="showConfirmation ? '{{ __('Hide password confirmation') }}' : '{{ __('Show password confirmation') }}'" :aria-pressed="showConfirmation">
                            <flux:icon.eye class="size-4" x-show="!showConfirmation" />
                            <flux:icon.eye-slash class="size-4" x-show="showConfirmation" x-cloak />
                        </button>
                    </div>
                    @error('password_confirmation')
                        <p class="mt-2 flex items-center gap-1.5 text-xs font-medium text-red-600" role="alert">
                            <flux:icon.exclamation-circle class="size-4 shrink-0" />
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>

            {{-- Actions --}}
            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <a href="{{ $settingsHome }}" wire:navigate
                   class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-600 shadow-xs transition hover:border-slate-300 hover:text-slate-900">
                    {{ __('Cancel') }}
                </a>
                <button type="submit" data-test="update-password-button"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-navy-900 px-6 text-xs font-semibold text-white shadow-lg shadow-navy-900/25 transition hover:bg-navy-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 focus-visible:ring-offset-2 active:translate-y-px">
                    <flux:icon.lock-closed class="size-4" />
                    {{ __('Save password') }}
                </button>
            </div>
        </div>

        {{-- Aside: guidance --}}
        <aside class="border-t border-slate-100 bg-slate-50/60 p-5 sm:p-6 lg:col-span-2 lg:border-s lg:border-t-0">
            <h3 class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">{{ __('Choosing a strong password') }}</h3>
            <ul class="mt-3 space-y-3 text-xs leading-relaxed text-slate-600">
                <li class="flex items-start gap-2">
                    <flux:icon.shield-check class="mt-0.5 size-4 shrink-0 text-navy-700" />
                    {{ __('Use at least 12 characters. A short, random passphrase beats a complex but short password.') }}
                </li>
                <li class="flex items-start gap-2">
                    <flux:icon.shield-check class="mt-0.5 size-4 shrink-0 text-navy-700" />
                    {{ __('Never reuse a password from another site or service.') }}
                </li>
                <li class="flex items-start gap-2">
                    <flux:icon.shield-check class="mt-0.5 size-4 shrink-0 text-navy-700" />
                    {{ __('Avoid names, birthdays and common dictionary words.') }}
                </li>
                <li class="flex items-start gap-2">
                    <flux:icon.shield-check class="mt-0.5 size-4 shrink-0 text-navy-700" />
                    {{ __('A password manager is the safest way to generate and store unique passwords.') }}
                </li>
            </ul>

            <div class="mt-6 rounded-xl border border-navy-100 bg-white p-4">
                <h3 class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-navy-800">
                    <flux:icon.information-circle class="size-4" />
                    {{ __('After you save') }}
                </h3>
                <ul class="mt-2 list-disc space-y-1.5 ps-4 text-[11px] leading-relaxed text-slate-500">
                    <li>{{ __('You stay signed in on this device.') }}</li>
                    <li>{{ __('All other sessions are signed out immediately.') }}</li>
                    <li>{{ __('The change is recorded in the audit log without the password itself.') }}</li>
                </ul>
            </div>
        </aside>
    </form>
</div>
