<?php

use App\Enums\KycDocumentType;
use App\Models\Borrower;
use App\Models\Investor;
use App\Models\User;
use App\Services\Settings\KycSettings;
use App\Services\Settings\SettingsService;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('KYC & Verification Settings')] class extends Component
{
    /** @var array<string, array{enabled: bool, mandatory: bool}> */
    public array $borrowerDocuments = [];

    /** @var array<string, array{enabled: bool, mandatory: bool}> */
    public array $investorDocuments = [];

    /** @var array<int, string> */
    public array $allowedFormats = ['jpg', 'jpeg', 'png', 'pdf'];

    public string $maxFileSizeKb = '5120';

    public string $maxUploadCount = '10';

    public bool $reuploadAllowed = true;

    public bool $reverificationRequired = true;

    public bool $manualVerification = true;

    public bool $rejectionReasonRequired = true;

    public bool $mandatoryMustBeVerified = true;

    public string $reason = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin() ?? false, 403);

        $settings = app(SettingsService::class);
        $kyc = app(KycSettings::class);

        $this->borrowerDocuments = $kyc->documentsFor(Borrower::class);
        $this->investorDocuments = $kyc->documentsFor(Investor::class);
        $this->allowedFormats = $kyc->allowedFormats();
        $this->maxFileSizeKb = (string) $kyc->maxFileSizeKb();
        $this->maxUploadCount = (string) $kyc->maxUploadCount();
        $this->reuploadAllowed = $kyc->reuploadAllowed();
        $this->reverificationRequired = $kyc->reverificationRequired();
        $this->manualVerification = $kyc->manualVerification();
        $this->rejectionReasonRequired = $kyc->rejectionReasonRequired();
        $this->mandatoryMustBeVerified = $kyc->mandatoryMustBeVerified();
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin() ?? false, 403);

        $validated = $this->validate([
            'allowedFormats' => ['required', 'array', 'min:1'],
            'allowedFormats.*' => ['string', 'in:jpg,jpeg,png,pdf'],
            'maxFileSizeKb' => ['required', 'integer', 'min:100', 'max:20480'],
            'maxUploadCount' => ['required', 'integer', 'min:1', 'max:50'],
            'reuploadAllowed' => ['boolean'],
            'reverificationRequired' => ['boolean'],
            'manualVerification' => ['boolean'],
            'rejectionReasonRequired' => ['boolean'],
            'mandatoryMustBeVerified' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($this->borrowerDocuments as $type => $config) {
            if (($config['mandatory'] ?? false) && ! ($config['enabled'] ?? false)) {
                $this->addError('borrowerDocuments.'.$type.'.enabled', __('Enabled is required when the document is marked mandatory.'));

                return;
            }
        }

        foreach ($this->investorDocuments as $type => $config) {
            if (($config['mandatory'] ?? false) && ! ($config['enabled'] ?? false)) {
                $this->addError('investorDocuments.'.$type.'.enabled', __('Enabled is required when the document is marked mandatory.'));

                return;
            }
        }

        /** @var User $actor */
        $actor = auth()->user();

        app(SettingsService::class)->putMany([
            'kyc.borrower_documents' => $this->normaliseDocuments($this->borrowerDocuments),
            'kyc.investor_documents' => $this->normaliseDocuments($this->investorDocuments),
            'kyc.allowed_formats' => array_values($validated['allowedFormats']),
            'kyc.max_file_size_kb' => (int) $validated['maxFileSizeKb'],
            'kyc.max_upload_count' => (int) $validated['maxUploadCount'],
            'kyc.reupload_allowed' => $validated['reuploadAllowed'],
            'kyc.reverification_required' => $validated['reverificationRequired'],
            'kyc.manual_verification' => $validated['manualVerification'],
            'kyc.rejection_reason_required' => $validated['rejectionReasonRequired'],
            'kyc.mandatory_must_be_verified' => $validated['mandatoryMustBeVerified'],
        ], $actor, $validated['reason'] ?: null);

        $this->reason = '';

        Flux::toast(variant: 'success', text: __('KYC settings saved. New submissions follow the updated rules; approved records are preserved.'));
    }

    public function documentLabel(string $type): string
    {
        return KycDocumentType::tryFrom($type)?->label() ?? $type;
    }

    /**
     * @param  array<string, array{enabled: bool, mandatory: bool}>  $documents
     * @return array<string, array{enabled: bool, mandatory: bool}>
     */
    private function normaliseDocuments(array $documents): array
    {
        $normalised = [];

        foreach ($documents as $type => $config) {
            $normalised[$type] = [
                'enabled' => (bool) ($config['enabled'] ?? false),
                'mandatory' => (bool) ($config['mandatory'] ?? false),
            ];
        }

        return $normalised;
    }
};
?>

<section class="w-full">
    <x-pages::settings.layout :heading="__('KYC & Verification Settings')" :subheading="__('Which documents are required, how they are validated and how verification decisions are enforced.')">
        <form wire:submit="save" class="space-y-6">
            @foreach ([
                ['key' => 'borrowerDocuments', 'title' => __('Borrower documents'), 'description' => __('Shown on the borrower application form and required before borrower KYC approval.'), 'icon' => 'user'],
                ['key' => 'investorDocuments', 'title' => __('Investor documents'), 'description' => __('Shown on the investor form and required before investor KYC approval.'), 'icon' => 'building-library'],
            ] as $section)
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" wire:key="kyc-section-{{ $section['key'] }}">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                            <flux:icon :name="$section['icon']" class="size-4" />
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">{{ $section['title'] }}</h2>
                            <p class="text-xs text-slate-500">{{ $section['description'] }}</p>
                        </div>
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="fc-table-head">
                                <tr>
                                    <th class="px-4 py-2.5">{{ __('Document type') }}</th>
                                    <th class="px-4 py-2.5 text-center">{{ __('Enabled') }}</th>
                                    <th class="px-4 py-2.5 text-center">{{ __('Mandatory') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($this->{$section['key']} as $type => $config)
                                    <tr wire:key="kyc-doc-{{ $section['key'] }}-{{ $type }}">
                                        <td class="px-4 py-3 text-slate-700">{{ $this->documentLabel($type) }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <flux:switch wire:model="{{ $section['key'] }}.{{ $type }}.enabled" />
                                            @error($section['key'].'.'.$type.'.enabled') <p class="mt-1 text-[11px] font-medium text-red-600">{{ $message }}</p> @enderror
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <flux:switch wire:model="{{ $section['key'] }}.{{ $type }}.mandatory" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            {{-- Upload rules --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.document-arrow-up class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Upload rules') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Applies to new uploads immediately; existing documents stay readable.') }}</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold text-slate-700">{{ __('Allowed file formats') }}</p>
                        <div class="mt-2 flex flex-wrap gap-4">
                            @foreach (['jpg', 'jpeg', 'png', 'pdf'] as $format)
                                <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                                    <input type="checkbox" wire:model="allowedFormats" value="{{ $format }}" class="rounded border-slate-300 text-navy-700 focus:ring-navy-600">
                                    {{ strtoupper($format) }}
                                </label>
                            @endforeach
                        </div>
                        @error('allowedFormats') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="maxFileSizeKb" :label="__('Maximum file size (KB)')" type="number" min="100" max="20480" required />
                        @error('maxFileSizeKb') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:input wire:model="maxUploadCount" :label="__('Maximum uploads per document type')" type="number" min="1" max="50" required />
                        @error('maxUploadCount') <p class="mt-2 text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="space-y-3 pt-1">
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            <flux:switch wire:model="reuploadAllowed" />
                            {{ __('Allow re-upload of an existing document') }}
                        </label>
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            <flux:switch wire:model="reverificationRequired" />
                            {{ __('Re-verification required after a re-upload') }}
                        </label>
                    </div>
                </div>
            </div>

            {{-- Verification rules --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700 ring-1 ring-navy-600/10">
                        <flux:icon.shield-check class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Verification rules') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('How documents move from pending to verified or rejected.') }}</p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <flux:switch wire:model="manualVerification" />
                        {{ __('Manual document verification (documents require a reviewer decision)') }}
                    </label>
                    <p class="ps-12 text-[11px] text-slate-400">{{ __('When manual verification is off, uploaded documents are accepted automatically.') }}</p>

                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <flux:switch wire:model="rejectionReasonRequired" />
                        {{ __('Rejection reason is mandatory') }}
                    </label>
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <flux:switch wire:model="mandatoryMustBeVerified" />
                        {{ __('All mandatory documents must be verified before KYC approval') }}
                    </label>
                </div>

                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] leading-relaxed text-slate-500">
                    {{ __('Changes apply to new submissions. Already approved KYC records, verification history and uploaded files are preserved and never invalidated automatically.') }}
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <flux:textarea wire:model="reason" :label="__('Reason for this change (optional)')" rows="2" maxlength="500"
                    placeholder="{{ __('Example: added income proof as mandatory for high-value loans') }}" />
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-navy-900 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                    <flux:icon.check class="size-4" />
                    {{ __('Save KYC settings') }}
                </button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
