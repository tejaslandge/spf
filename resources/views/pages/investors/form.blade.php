<?php

use App\Enums\InvestorStatus;
use App\Enums\KycDocumentType;
use App\Models\Investor;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Investor')] class extends Component
{
    use WithFileUploads;

    public ?Investor $investor = null;

    public string $name = '';

    public string $address = '';

    public string $mobile = '';

    public string $email = '';

    public string $pan_number = '';

    public string $aadhaar_number = '';

    public string $bank_name = '';

    public string $bank_account_number = '';

    public string $bank_ifsc = '';

    public string $status = 'active';

    public string $notes = '';

    public $aadhaar = null;

    public $pan = null;

    public function mount(?Investor $investor = null): void
    {
        $this->investor = $investor;

        if ($investor?->exists) {
            $this->fill([
                'name' => $investor->name,
                'address' => $investor->address,
                'mobile' => $investor->mobile,
                'email' => (string) $investor->email,
                'pan_number' => (string) $investor->pan_number,
                'aadhaar_number' => (string) $investor->aadhaar_number,
                'bank_name' => (string) $investor->bank_name,
                'bank_account_number' => (string) $investor->bank_account_number,
                'bank_ifsc' => (string) $investor->bank_ifsc,
                'status' => $investor->status->value,
                'notes' => (string) $investor->notes,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'address' => ['required', 'string', 'min:5', 'max:1000'],
            'mobile' => ['required', 'digits:10', Rule::unique('investors', 'mobile')->ignore($this->investor?->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'pan_number' => [
                'required',
                'string',
                'size:10',
                'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/',
                Rule::unique('investors', 'pan_number')->ignore($this->investor?->id),
            ],
            'aadhaar_number' => [
                'required',
                'digits:12',
                'regex:/^[2-9][0-9]{11}$/',
                Rule::unique('investors', 'aadhaar_number')->ignore($this->investor?->id),
            ],
            'bank_name' => ['required', 'string', 'min:2', 'max:255'],
            'bank_account_number' => ['required', 'string', 'regex:/^[0-9]{9,18}$/'],
            'bank_ifsc' => ['required', 'string', 'size:11', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'status' => ['required', Rule::enum(InvestorStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'aadhaar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'pan' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function save(): void
    {
        $this->name = trim($this->name);
        $this->address = trim($this->address);
        $this->mobile = preg_replace('/\D/', '', $this->mobile) ?? '';
        $this->email = strtolower(trim($this->email));
        $this->pan_number = strtoupper(trim($this->pan_number));
        $this->aadhaar_number = preg_replace('/\D/', '', $this->aadhaar_number) ?? '';
        $this->bank_name = trim($this->bank_name);
        $this->bank_account_number = preg_replace('/\s+/', '', $this->bank_account_number) ?? '';
        $this->bank_ifsc = strtoupper(trim($this->bank_ifsc));

        $validated = $this->validate();

        $investor = DB::transaction(function () use ($validated): Investor {
            $investor = $this->investor ?? new Investor;
            $investor->fill($validated);
            $investor->created_by ??= auth()->id();
            $investor->save();

            $this->storeDocument($investor, $this->aadhaar, KycDocumentType::Aadhaar);
            $this->storeDocument($investor, $this->pan, KycDocumentType::Pan);

            return $investor;
        });

        Flux::toast(variant: 'success', text: __('Investor saved successfully.'));

        $this->redirectRoute('investors.show', $investor, navigate: true);
    }

    private function storeDocument(Investor $investor, mixed $file, KycDocumentType $type): void
    {
        if (! $file) {
            return;
        }

        $path = $file->store('kyc/investors/'.$investor->id, 'local');

        $investor->kycDocuments()->create([
            'type' => $type,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'status' => 'pending',
        ]);
    }
};
?>

<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Investor onboarding') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ $investor?->exists ? __('Edit investor') : __('Add investor') }}</h2>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">{{ __('Investor KYC, bank details and contact information. PAN and Aadhaar are required.') }}</p>
        </div>

        <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-inset ring-slate-200">
            <span class="size-1.5 rounded-full bg-amber-500"></span>
            {{ __('KYC details required') }}
        </span>
    </div>

    <form wire:submit="save" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-4">
            <div id="personal" class="fc-card scroll-mt-24 p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('Personal details') }}</h3>
                    <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-300">{{ __('01') }}</span>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="name" :label="__('Full name')" placeholder="As per PAN card" required minlength="2" maxlength="255" />
                    <flux:input wire:model="mobile" :label="__('Mobile number')" placeholder="10-digit mobile" inputmode="numeric" maxlength="10" required />
                    <flux:input wire:model="email" type="email" :label="__('Email address')" placeholder="Optional" maxlength="255" />
                    <flux:select wire:model="status" :label="__('Status')" required>
                        @foreach (\App\Enums\InvestorStatus::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </flux:select>

                    <div class="sm:col-span-2">
                        <flux:textarea wire:model="address" :label="__('Complete address')" rows="3" required minlength="5" maxlength="1000" placeholder="House, street, city, state, PIN" />
                    </div>
                </div>
            </div>

            <div id="identity" class="fc-card scroll-mt-24 p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('Tax & identity') }}</h3>
                    <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-300">{{ __('02') }}</span>
                </div>
                <p class="mt-1 text-xs text-slate-400">{{ __('PAN and Aadhaar numbers are mandatory for investor KYC.') }}</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="pan_number" :label="__('PAN number')" placeholder="ABCDE1234F" maxlength="10" required class="uppercase" />
                    <flux:input wire:model="aadhaar_number" :label="__('Aadhaar number')" placeholder="12-digit Aadhaar" inputmode="numeric" maxlength="12" required />
                </div>
            </div>

            <div id="bank" class="fc-card scroll-mt-24 p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('Bank details') }}</h3>
                    <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-300">{{ __('03') }}</span>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="bank_name" :label="__('Bank name')" required minlength="2" maxlength="255" />
                    <flux:input wire:model="bank_account_number" :label="__('Account number')" inputmode="numeric" required minlength="9" maxlength="18" />
                    <flux:input wire:model="bank_ifsc" :label="__('IFSC code')" placeholder="HDFC0001234" maxlength="11" required class="uppercase" />
                </div>
            </div>

            <div id="documents" class="fc-card scroll-mt-24 p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-slate-900">{{ __('KYC documents') }}</h3>
                    <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-300">{{ __('04') }}</span>
                </div>
                <p class="mt-1 text-xs text-slate-400">{{ __('Aadhaar and PAN only. JPG, PNG or PDF up to 5 MB. Documents are stored privately.') }}</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="aadhaar" type="file" :label="__('Aadhaar card')" accept=".jpg,.jpeg,.png,.pdf" />
                    <flux:input wire:model="pan" type="file" :label="__('PAN card')" accept=".jpg,.jpeg,.png,.pdf" />
                </div>

                <div wire:loading wire:target="aadhaar,pan" class="mt-3 text-xs text-blue-600">
                    {{ __('Uploading…') }}
                </div>
            </div>

            <div class="fc-card p-5 sm:p-6">
                <flux:textarea wire:model="notes" :label="__('Internal notes')" rows="2" />
            </div>

            <div class="flex items-center justify-end gap-2">
                <a href="{{ route('investors.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900">
                    {{ __('Cancel') }}
                </a>
                <flux:button type="submit" variant="primary" class="!rounded-xl">
                    {{ $investor?->exists ? __('Save changes') : __('Create investor') }}
                </flux:button>
            </div>
        </div>

        <aside class="hidden space-y-4 xl:sticky xl:top-24 xl:block">
            <div class="fc-card p-5">
                <p class="fc-eyebrow">{{ __('Before you save') }}</p>
                <h3 class="mt-1 text-sm font-semibold text-slate-900">{{ __('Application checklist') }}</h3>

                <ul class="mt-4 space-y-3.5">
                    <li>
                        <a href="#personal" class="group flex items-start gap-3">
                            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-600/15">
                                <flux:icon.check class="size-3" />
                            </span>
                            <span>
                                <span class="block text-xs font-medium text-slate-700 group-hover:text-slate-900">{{ __('Contact details') }}</span>
                                <span class="mt-0.5 block text-[11px] leading-relaxed text-slate-400">{{ __('Name, mobile, address and status.') }}</span>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="#identity" class="group flex items-start gap-3">
                            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-600/15">
                                <flux:icon.check class="size-3" />
                            </span>
                            <span>
                                <span class="block text-xs font-medium text-slate-700 group-hover:text-slate-900">{{ __('PAN & Aadhaar') }}</span>
                                <span class="mt-0.5 block text-[11px] leading-relaxed text-slate-400">{{ __('Both numbers are required and must be unique.') }}</span>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="#bank" class="group flex items-start gap-3">
                            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-1 ring-inset ring-emerald-600/15">
                                <flux:icon.check class="size-3" />
                            </span>
                            <span>
                                <span class="block text-xs font-medium text-slate-700 group-hover:text-slate-900">{{ __('Payout account') }}</span>
                                <span class="mt-0.5 block text-[11px] leading-relaxed text-slate-400">{{ __('Bank, account number and IFSC for interest payouts.') }}</span>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="#documents" class="group flex items-start gap-3">
                            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-400 ring-1 ring-inset ring-slate-200">
                                <flux:icon.document-plus class="size-3" />
                            </span>
                            <span>
                                <span class="block text-xs font-medium text-slate-700 group-hover:text-slate-900">{{ __('KYC scans') }}</span>
                                <span class="mt-0.5 block text-[11px] leading-relaxed text-slate-400">{{ __('Optional now — Aadhaar and PAN, up to 5 MB each.') }}</span>
                            </span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="rounded-2xl border border-slate-200/80 bg-slate-100/60 p-5">
                <div class="flex items-center gap-2 text-slate-600">
                    <flux:icon.shield-check class="size-4" />
                    <p class="text-xs font-semibold">{{ __('Private by default') }}</p>
                </div>
                <p class="mt-2 text-[11px] leading-relaxed text-slate-500">{{ __('KYC files are stored on a private disk and only available to authorised staff through signed downloads.') }}</p>
            </div>
        </aside>
    </form>
</div>
