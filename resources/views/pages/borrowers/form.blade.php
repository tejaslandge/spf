<?php

use App\Enums\BorrowerStatus;
use App\Enums\KycDocumentType;
use App\Models\Borrower;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Borrower')] class extends Component
{
    use WithFileUploads;

    public ?Borrower $borrower = null;

    public string $full_name = '';

    public string $address = '';

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

    public function mount(?Borrower $borrower = null): void
    {
        $this->borrower = $borrower;

        if ($borrower?->exists) {
            $this->fill([
                'full_name' => $borrower->full_name,
                'address' => $borrower->address,
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
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'address' => ['required', 'string', 'min:5', 'max:1000'],
            'mobile' => ['required', 'digits:10', Rule::unique('borrowers', 'mobile')->ignore($this->borrower?->id)],
            'alternate_mobile' => ['nullable', 'digits:10', 'different:mobile'],
            'emergency_contact_name' => ['required', 'string', 'min:2', 'max:255'],
            'emergency_contact_number' => ['required', 'digits:10'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:40'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::enum(BorrowerStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'aadhaar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'pan' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'electricity_bill' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function save(): void
    {
        $this->full_name = trim($this->full_name);
        $this->address = trim($this->address);
        $this->emergency_contact_name = trim($this->emergency_contact_name);
        $this->mobile = preg_replace('/\D/', '', $this->mobile) ?? '';
        $this->alternate_mobile = preg_replace('/\D/', '', $this->alternate_mobile) ?? '';
        $this->emergency_contact_number = preg_replace('/\D/', '', $this->emergency_contact_number) ?? '';

        $validated = $this->validate();

        $borrower = DB::transaction(function () use ($validated): Borrower {
            $borrower = $this->borrower ?? new Borrower;
            $borrower->fill($validated);
            $borrower->created_by ??= auth()->id();
            $borrower->save();

            $this->storeDocument($borrower, $this->aadhaar, KycDocumentType::Aadhaar);
            $this->storeDocument($borrower, $this->pan, KycDocumentType::Pan);
            $this->storeDocument($borrower, $this->electricity_bill, KycDocumentType::ElectricityBill);

            return $borrower;
        });

        Flux::toast(variant: 'success', text: __('Borrower saved successfully.'));

        $this->redirectRoute('borrowers.show', $borrower, navigate: true);
    }

    private function storeDocument(Borrower $borrower, mixed $file, KycDocumentType $type): void
    {
        if (! $file) {
            return;
        }

        $path = $file->store('kyc/borrowers/'.$borrower->id, 'local');

        $borrower->kycDocuments()->create([
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
                    <flux:textarea wire:model="address" :label="__('Complete address')" rows="3" placeholder="House, street, city, state, PIN" required minlength="5" maxlength="1000" />
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
            <h3 class="text-sm font-semibold text-slate-900">{{ __('KYC documents') }}</h3>
            <p class="mt-1 text-xs text-slate-400">{{ __('JPG, PNG or PDF up to 5 MB. Documents are stored privately.') }}</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="aadhaar" type="file" :label="__('Aadhaar card')" accept=".jpg,.jpeg,.png,.pdf" />
                <flux:input wire:model="pan" type="file" :label="__('PAN card')" accept=".jpg,.jpeg,.png,.pdf" />
                <flux:input wire:model="electricity_bill" type="file" :label="__('Electricity bill')" accept=".jpg,.jpeg,.png,.pdf" />
            </div>

            <div wire:loading wire:target="aadhaar,pan,electricity_bill" class="mt-3 text-xs text-blue-600">
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
