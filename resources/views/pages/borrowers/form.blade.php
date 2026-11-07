<?php

use App\Enums\BorrowerStatus;
use App\Enums\KycDocumentType;
use App\Models\Borrower;
use App\Models\Guarantor;
use App\Services\GuarantorService;
use App\Services\Settings\KycSettings;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Borrower')] class extends Component
{
    use WithFileUploads;

    public ?Borrower $borrower = null;

    public string $full_name = '';

    public string $address = '';

    public string $state = '';

    public string $pincode = '';

    public string $occupation = '';

    public string $mobile = '';

    public string $alternate_mobile = '';

    public string $emergency_contact_name = '';

    public string $emergency_contact_number = '';

    public string $bank_name = '';

    public string $bank_account_number = '';

    public string $bank_ifsc = '';

    public string $status = 'active';

    public string $notes = '';

    public $aadhaar = null;

    public $pan = null;

    public $electricity_bill = null;

    public $income_proof = null;

    public $bank_statement = null;

    public $employment_proof = null;

    public bool $also_add_as_guarantor = false;

    public function mount(?Borrower $borrower = null): void
    {
        $this->borrower = $borrower;

        if ($borrower?->exists) {
            $this->fill([
                'full_name' => $borrower->full_name,
                'address' => $borrower->address,
                'state' => (string) $borrower->state,
                'pincode' => (string) $borrower->pincode,
                'occupation' => (string) $borrower->occupation,
                'mobile' => $borrower->mobile,
                'alternate_mobile' => (string) $borrower->alternate_mobile,
                'emergency_contact_name' => $borrower->emergency_contact_name,
                'emergency_contact_number' => $borrower->emergency_contact_number,
                'bank_name' => (string) $borrower->bank_name,
                'bank_account_number' => (string) $borrower->bank_account_number,
                'bank_ifsc' => (string) $borrower->bank_ifsc,
                'status' => $borrower->status->value,
                'notes' => (string) $borrower->notes,
            ]);

            $this->also_add_as_guarantor = Guarantor::query()
                ->where('borrower_id', $borrower->getKey())
                ->exists();
        }
    }

    /**
     * Enabled KYC document slots, driven by the KYC settings screen.
     *
     * @return array<int, array{type: string, property: string, label: string, mandatory: bool}>
     */
    #[Computed]
    public function documentSlots(): array
    {
        $documents = app(KycSettings::class)->documentsFor(Borrower::class);

        $slots = [];

        foreach ($documents as $type => $config) {
            if (! ($config['enabled'] ?? false)) {
                continue;
            }

            $slots[] = [
                'type' => $type,
                'property' => $type,
                'label' => KycDocumentType::tryFrom($type)?->label() ?? $type,
                'mandatory' => (bool) ($config['mandatory'] ?? false),
            ];
        }

        return $slots;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'address' => ['required', 'string', 'min:5', 'max:1000'],
            'state' => ['nullable', 'string', 'min:2', 'max:100'],
            'pincode' => ['nullable', 'regex:/^[1-9][0-9]{5}$/'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'mobile' => ['required', 'digits:10', Rule::unique('borrowers', 'mobile')->ignore($this->borrower?->id)],
            'alternate_mobile' => ['nullable', 'digits:10', 'different:mobile'],
            'emergency_contact_name' => ['required', 'string', 'min:2', 'max:255'],
            'emergency_contact_number' => ['required', 'digits:10'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:40'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::enum(BorrowerStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        $fileRules = app(KycSettings::class)->fileValidationRules();

        foreach ($this->documentSlots as $slot) {
            $rules[$slot['property']] = ['nullable', ...$fileRules];
        }

        return $rules;
    }

    public function save(): void
    {
        $this->full_name = trim($this->full_name);
        $this->address = trim($this->address);
        $this->state = trim($this->state);
        $this->pincode = preg_replace('/\D/', '', $this->pincode) ?? '';
        $this->occupation = trim($this->occupation);
        $this->emergency_contact_name = trim($this->emergency_contact_name);
        $this->mobile = preg_replace('/\D/', '', $this->mobile) ?? '';
        $this->alternate_mobile = preg_replace('/\D/', '', $this->alternate_mobile) ?? '';
        $this->emergency_contact_number = preg_replace('/\D/', '', $this->emergency_contact_number) ?? '';

        $validated = $this->validate();

        foreach (['state', 'pincode', 'occupation'] as $field) {
            if (($validated[$field] ?? null) === '') {
                $validated[$field] = null;
            }
        }

        $borrower = DB::transaction(function () use ($validated): Borrower {
            $borrower = $this->borrower ?? new Borrower;
            $borrower->fill($validated);
            $borrower->created_by ??= auth()->id();
            $borrower->save();

            foreach ($this->documentSlots as $slot) {
                $type = KycDocumentType::tryFrom($slot['type']);

                if ($type === null) {
                    continue;
                }

                $this->storeDocument($borrower, $this->{$slot['property']}, $type, $slot['property']);
            }

            app(GuarantorService::class)->syncBorrowerGuarantor($borrower, $this->also_add_as_guarantor, auth()->user());

            return $borrower;
        });

        $message = $this->also_add_as_guarantor
            ? __('Borrower saved and registered as a guarantor.')
            : __('Borrower saved successfully.');

        Flux::toast(variant: 'success', text: $message);

        $this->redirectRoute('borrowers.show', $borrower, navigate: true);
    }

    private function storeDocument(Borrower $borrower, mixed $file, KycDocumentType $type, string $property): void
    {
        if (! $file) {
            return;
        }

        $settings = app(KycSettings::class);

        $existing = $borrower->kycDocuments()
            ->where('type', $type->value)
            ->orderByDesc('id')
            ->get();

        if (! $settings->reuploadAllowed() && $existing->isNotEmpty()) {
            throw ValidationException::withMessages([
                $property => __('Re-uploading this document is currently disabled. Contact a Super Admin.'),
            ]);
        }

        if ($existing->count() >= $settings->maxUploadCount()) {
            throw ValidationException::withMessages([
                $property => __('The maximum of :count uploads for this document type has been reached.', ['count' => $settings->maxUploadCount()]),
            ]);
        }

        $status = $settings->manualVerification() ? 'pending' : 'verified';

        if (! $settings->reverificationRequired() && $existing->firstWhere('status', 'verified') !== null) {
            // A previously verified document keeps its verified state on replacement.
            $status = 'verified';
        }

        $path = $file->store('kyc/borrowers/'.$borrower->id, 'local');

        $borrower->kycDocuments()->create([
            'type' => $type,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'status' => $status,
            'verified_at' => $status === 'verified' ? now() : null,
            'notes' => $status === 'verified' && ! $settings->manualVerification() ? 'Auto-verified (manual verification disabled)' : null,
        ]);
    }
};
?>

<div class="mx-auto max-w-4xl space-y-5">
    <div>
        <p class="fc-eyebrow">{{ __('Borrower management') }}</p>
        <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ $borrower?->exists ? __('Edit borrower') : __('Add borrower') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('Capture contact details, emergency contacts and KYC documents.') }}</p>
    </div>

    <form wire:submit="save" class="space-y-4">
        <div class="fc-card p-5 sm:p-6">
            <h3 class="text-sm font-semibold text-slate-900">{{ __('Personal details') }}</h3>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="full_name" :label="__('Full name')" placeholder="As per Aadhaar" required minlength="2" maxlength="255" />
                <flux:input wire:model="mobile" :label="__('Mobile number')" placeholder="10-digit mobile" inputmode="numeric" maxlength="10" required />
                <flux:input wire:model="alternate_mobile" :label="__('Alternate mobile')" placeholder="Optional" inputmode="numeric" maxlength="10" />
                <flux:select wire:model="status" :label="__('Status')" required>
                    @foreach (\App\Enums\BorrowerStatus::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </flux:select>

                <div class="sm:col-span-2">
                    <flux:textarea wire:model="address" :label="__('Complete address')" rows="3" placeholder="House, street, area" required minlength="5" maxlength="1000" />
                </div>

                <flux:input wire:model="state" :label="__('State')" placeholder="State" maxlength="100" />
                <flux:input wire:model="pincode" :label="__('PIN code')" placeholder="6-digit PIN" inputmode="numeric" maxlength="6" />
                <div class="sm:col-span-2">
                    <flux:input wire:model="occupation" :label="__('Occupation')" placeholder="Occupation (optional)" maxlength="100" />
                </div>
            </div>
        </div>

        <div class="fc-card p-5 sm:p-6">
            <h3 class="text-sm font-semibold text-slate-900">{{ __('Emergency contact') }}</h3>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="emergency_contact_name" :label="__('Contact name')" required minlength="2" maxlength="255" />
                <flux:input wire:model="emergency_contact_number" :label="__('Contact number')" inputmode="numeric" maxlength="10" required />
            </div>
        </div>

        <div class="fc-card p-5 sm:p-6">
            <h3 class="text-sm font-semibold text-slate-900">{{ __('Bank details') }}</h3>
            <p class="mt-1 text-xs text-slate-400">{{ __('Used for loan disbursement.') }}</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="bank_name" :label="__('Bank name')" maxlength="255" />
                <flux:input wire:model="bank_account_number" :label="__('Account number')" maxlength="40" />
                <flux:input wire:model="bank_ifsc" :label="__('IFSC code')" maxlength="20" />
            </div>
        </div>

        <div class="fc-card p-5 sm:p-6">
            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="also_add_as_guarantor" class="mt-1 rounded border-slate-300 text-navy-900 focus:ring-navy-900/20">
                <span>
                    <span class="block text-sm font-semibold text-slate-900">{{ __('Also Add as Guarantor') }}</span>
                    <span class="mt-0.5 block text-xs text-slate-400">{{ __('Register this borrower as a guarantor using their existing details. No extra information is required.') }}</span>
                </span>
            </label>
        </div>

        <div class="fc-card p-5 sm:p-6">
            <h3 class="text-sm font-semibold text-slate-900">{{ __('KYC documents') }}</h3>
            <p class="mt-1 text-xs text-slate-400">
                {{ __('Allowed formats: :formats up to :size MB. Documents are stored privately and verified before KYC approval.', [
                    'formats' => strtoupper(implode(', ', app(\App\Services\Settings\KycSettings::class)->allowedFormats())),
                    'size' => number_format(app(\App\Services\Settings\KycSettings::class)->maxFileSizeKb() / 1024, 1),
                ]) }}
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                @foreach ($this->documentSlots as $slot)
                    <div wire:key="kyc-{{ $slot['property'] }}">
                        <flux:input wire:model="{{ $slot['property'] }}" type="file"
                            :label="$slot['label'].($slot['mandatory'] ? ' *' : '')"
                            accept="{{ collect(app(\App\Services\Settings\KycSettings::class)->allowedFormats())->map(fn ($f) => '.'.$f)->implode(',') }}" />
                        @error($slot['property']) <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>

            <div wire:loading wire:target="{{ collect($this->documentSlots)->pluck('property')->implode(',') }}" class="mt-3 text-xs text-blue-600">
                {{ __('Uploading…') }}
            </div>
        </div>

        <div class="fc-card p-5 sm:p-6">
            <flux:textarea wire:model="notes" :label="__('Internal notes')" rows="2" placeholder="Optional notes for the operations team" />
        </div>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('borrowers.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900">
                {{ __('Cancel') }}
            </a>
            <flux:button type="submit" variant="primary" class="!rounded-xl">
                {{ $borrower?->exists ? __('Save changes') : __('Create borrower') }}
            </flux:button>
        </div>
    </form>
</div>
