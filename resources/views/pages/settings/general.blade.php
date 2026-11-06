<x-layouts::app :title="__('General Settings')">
    <x-pages::settings.layout :heading="__('General Settings')" :subheading="__('Company identity used on statements, receipts and official documents.')">
        @if (session('status'))
            <div class="mb-5 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                <flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-emerald-600" />
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('super-admin.settings.general.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

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

                <div class="mt-5 space-y-5">
                    <div>
                        <flux:input
                            name="company_name"
                            :label="__('Company name')"
                            :value="old('company_name', $companyName)"
                            required
                            autocomplete="organization"
                            maxlength="120"
                        />
                        @error('company_name')
                            <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:textarea
                            name="company_address"
                            :label="__('Company address')"
                            rows="2"
                            maxlength="255"
                            placeholder="{{ __('Registered office address') }}"
                        >{{ old('company_address', $companyAddress) }}</flux:textarea>
                        @error('company_address')
                            <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input
                            name="company_phone"
                            :label="__('Company phone')"
                            type="tel"
                            :value="old('company_phone', $companyPhone)"
                            autocomplete="tel"
                            maxlength="20"
                            placeholder="+91 90000 00000"
                        />
                        @error('company_phone')
                            <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
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
            {{ __('Changes are recorded in the audit log with your user role and IP address. Only Super Admins can modify these settings.') }}
        </p>
    </x-pages::settings.layout>
</x-layouts::app>
