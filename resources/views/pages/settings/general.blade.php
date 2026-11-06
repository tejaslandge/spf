<x-layouts::app :title="__('General Settings')">
    <x-pages::settings.layout :heading="__('General Settings')" :subheading="__('Company identity, contact details and regional formats used across statements, receipts and official documents.')">
        @if (session('status'))
            <div class="mb-5 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                <flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-emerald-600" />
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('super-admin.settings.general.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Company profile --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.building-office-2 class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Company profile') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Shown on EMI receipts, investor statements and KYC documents.') }}</p>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-5 sm:flex-row sm:items-start">
                    <div class="shrink-0">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ __('Company logo') }}" class="size-20 rounded-2xl border border-slate-200 bg-white object-contain p-1.5 shadow-sm">
                        @else
                            <div class="flex size-20 items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 text-slate-300">
                                <flux:icon.photo class="size-7" />
                            </div>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1 space-y-4">
                        <div>
                            <label class="text-xs font-medium text-slate-700" for="company_logo">{{ __('Company logo') }}</label>
                            <input type="file" id="company_logo" name="company_logo" accept=".jpg,.jpeg,.png,.webp"
                                   class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                            <p class="mt-1.5 text-[11px] text-slate-400">{{ __('JPG, PNG or WebP up to 2 MB. Used in the sidebar and on documents.') }}</p>
                            @error('company_logo') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>
                        @if ($logoUrl)
                            <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                                <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-navy-700 focus:ring-navy-600">
                                {{ __('Remove the current logo') }}
                            </label>
                        @endif
                    </div>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <flux:input name="company_name" :label="__('Company name')" :value="old('company_name', $company['company.name'])" required autocomplete="organization" maxlength="120" />
                        @error('company_name') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input name="company_legal_name" :label="__('Company legal name')" :value="old('company_legal_name', $company['company.legal_name'])" maxlength="160" />
                        @error('company_legal_name') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input name="company_registration_number" :label="__('Registration number')" :value="old('company_registration_number', $company['company.registration_number'])" maxlength="60" />
                        @error('company_registration_number') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input name="company_website" :label="__('Website URL')" :value="old('company_website', $company['company.website'])" type="url" maxlength="160" placeholder="https://example.com" />
                        @error('company_website') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Contact details --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.phone class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Contact details') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Used on customer communication and support flows.') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <flux:input name="company_email" :label="__('Company email')" :value="old('company_email', $company['company.email'])" type="email" maxlength="160" />
                        @error('company_email') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input name="company_phone" :label="__('Company phone')" :value="old('company_phone', $company['company.phone'])" type="tel" maxlength="20" placeholder="+91 90000 00000" />
                        @error('company_phone') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input name="company_support_email" :label="__('Support email')" :value="old('company_support_email', $company['company.support_email'])" type="email" maxlength="160" />
                        @error('company_support_email') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input name="company_support_mobile" :label="__('Support mobile number')" :value="old('company_support_mobile', $company['company.support_mobile'])" type="tel" maxlength="20" placeholder="+91 90000 00000" />
                        @error('company_support_mobile') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Address --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.map-pin class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Registered address') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Printed on official receipts and statements.') }}</p>
                    </div>
                </div>

                <div class="mt-5 space-y-5">
                    <div>
                        <flux:textarea name="company_address" :label="__('Company address')" rows="2" maxlength="255"
                            placeholder="{{ __('Registered office address') }}">{{ old('company_address', $company['company.address']) }}</flux:textarea>
                        @error('company_address') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-5 sm:grid-cols-3">
                        <div>
                            <flux:input name="company_city" :label="__('City')" :value="old('company_city', $company['company.city'])" maxlength="80" />
                            @error('company_city') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <flux:input name="company_state" :label="__('State')" :value="old('company_state', $company['company.state'])" maxlength="80" />
                            @error('company_state') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <flux:input name="company_pincode" :label="__('PIN code')" :value="old('company_pincode', $company['company.pincode'])" inputmode="numeric" maxlength="10" />
                            @error('company_pincode') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Regional settings --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.globe-alt class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Regional settings') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Applied application-wide to money, dates, times and reporting periods.') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-3">
                    <div>
                        <flux:input name="company_currency_code" :label="__('Currency code')" :value="old('company_currency_code', $company['company.currency_code'])" maxlength="3" placeholder="INR" required />
                        @error('company_currency_code') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input name="company_currency_symbol" :label="__('Currency symbol')" :value="old('company_currency_symbol', $company['company.currency_symbol'])" maxlength="5" placeholder="₹" required />
                        @error('company_currency_symbol') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:select name="company_timezone" :label="__('Timezone')" required>
                            @foreach (['Asia/Kolkata', 'Asia/Dubai', 'Asia/Singapore', 'Europe/London', 'America/New_York', 'UTC'] as $timezone)
                                <option value="{{ $timezone }}" @selected(old('company_timezone', $company['company.timezone']) === $timezone)>{{ $timezone }}</option>
                            @endforeach
                        </flux:select>
                        @error('company_timezone') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:select name="company_date_format" :label="__('Date format')" required>
                            @foreach (['d M Y' => now()->format(\App\Support\Regional::dateFormat()), 'd/m/Y' => now()->format('d/m/Y'), 'm/d/Y' => now()->format('m/d/Y'), 'Y-m-d' => now()->format('Y-m-d')] as $format => $example)
                                <option value="{{ $format }}" @selected(old('company_date_format', $company['company.date_format']) === $format)>{{ $example }}</option>
                            @endforeach
                        </flux:select>
                        @error('company_date_format') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:select name="company_time_format" :label="__('Time format')" required>
                            @foreach (['H:i' => now()->format('H:i'), 'h:i A' => now()->format('h:i A')] as $format => $example)
                                <option value="{{ $format }}" @selected(old('company_time_format', $company['company.time_format']) === $format)>{{ $example }}</option>
                            @endforeach
                        </flux:select>
                        @error('company_time_format') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:select name="company_financial_year_start_month" :label="__('Financial year starts in')" required>
                            @foreach (range(1, 12) as $month)
                                <option value="{{ $month }}" @selected((int) old('company_financial_year_start_month', $company['company.financial_year_start_month']) === $month)>
                                    {{ \Illuminate\Support\Carbon::create()->month($month)->format('F') }}
                                </option>
                            @endforeach
                        </flux:select>
                        @error('company_financial_year_start_month') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Reason --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <flux:textarea name="reason" :label="__('Reason for this change (optional)')" rows="2" maxlength="500"
                    placeholder="{{ __('Example: updated registered office address') }}">{{ old('reason') }}</flux:textarea>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <a href="{{ route('super-admin.settings') }}" wire:navigate
                   class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900">
                    {{ __('Cancel') }}
                </a>
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-navy-900 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-navy-600 focus-visible:ring-offset-2">
                    <flux:icon.check class="size-4" />
                    {{ __('Save settings') }}
                </button>
            </div>
        </form>

        <p class="mt-4 text-[11px] leading-relaxed text-slate-400">
            {{ __('Changes are recorded in the audit log with your user role and IP address.') }}
        </p>
    </x-pages::settings.layout>
</x-layouts::app>
