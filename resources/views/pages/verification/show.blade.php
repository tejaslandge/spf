<?php

use App\Enums\KycStatus;
use App\Models\Borrower;
use App\Models\Investor;
use App\Models\KycDocument;
use App\Models\KycVerificationHistory;
use App\Services\KycVerificationService;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Verification detail')] class extends Component
{
    public string $type = 'borrower';

    public int $subject = 0;

    public ?int $selectedDocId = null;

    public bool $revealed = false;

    public bool $showRejectModal = false;

    public int $rejectingDocId = 0;

    public string $rejectionReason = '';

    public string $overallNote = '';

    public string $approveNote = '';

    public bool $showApproveConfirm = false;

    public bool $showRejectOverallModal = false;

    public string $overallRejectionReason = '';

    public function mount(string $type, int $subject): void
    {
        $this->type = $type;
        $this->subject = $subject;
    }

    #[Computed]
    public function subjectModel()
    {
        $model = $this->type === 'investor'
            ? Investor::query()->withCount('kycDocuments')->findOrFail($this->subject)
            : Borrower::query()->withCount('kycDocuments')->findOrFail($this->subject);

        $type = $this->type === 'investor' ? Investor::class : Borrower::class;
        $model->setAttribute('verification_status', KycDocument::overallStatusFor($type, (int) $model->getKey()));

        return $model;
    }

    #[Computed]
    public function documents()
    {
        $type = $this->type === 'investor' ? Investor::class : Borrower::class;

        return KycDocument::query()
            ->where('documentable_type', $type)
            ->where('documentable_id', $this->subject)
            ->with('verifiedBy')
            ->latest()
            ->get();
    }

    #[Computed]
    public function mandatoryCheck(): array
    {
        $type = $this->type === 'investor' ? Investor::class : Borrower::class;
        $mandatory = KycDocument::mandatoryTypesFor($type);
        $docs = $this->documents;

        $checks = [];
        foreach ($mandatory as $t) {
            $latest = $docs->where('type', $t)->sortByDesc('created_at')->first();
            $checks[$t] = $latest ? $latest->status->value : 'missing';
        }

        $pending = collect($checks)->filter(fn ($s) => $s === 'pending' || $s === 'missing')->count();
        $rejected = collect($checks)->filter(fn ($s) => $s === 'rejected')->count();

        return [
            'checks' => $checks,
            'pending' => $pending,
            'rejected' => $rejected,
            'allVerified' => $pending === 0 && $rejected === 0,
        ];
    }

    #[Computed]
    public function selectedDocument(): ?KycDocument
    {
        return $this->documents->firstWhere('id', $this->selectedDocId) ?? $this->documents->first();
    }

    #[Computed]
    public function history()
    {
        $type = $this->type === 'investor' ? Investor::class : Borrower::class;

        return KycVerificationHistory::query()
            ->where('subject_type', $type)
            ->where('subject_id', $this->subject)
            ->with(['document', 'actedBy'])
            ->latest()
            ->limit(20)
            ->get();
    }

    public function selectDoc(int $id): void
    {
        $this->selectedDocId = $id;
        $this->revealed = false;
        unset($this->selectedDocument);
    }

    public function toggleReveal(): void
    {
        $this->revealed = !$this->revealed;
    }

    public function verifyDoc(int $docId, KycVerificationService $svc): void
    {
        $doc = KycDocument::query()->findOrFail($docId);

        try {
            $svc->setDocumentStatus($doc, KycStatus::Verified, null, auth()->user());
            unset($this->documents, $this->selectedDocument, $this->subjectModel, $this->mandatoryCheck, $this->history);
            Flux::toast(variant: 'success', text: __('Document verified.'));
        } catch (ValidationException $e) {
            $this->addError('document', $e->getMessage());
        }
    }

    public function openRejectDoc(int $docId): void
    {
        $this->rejectingDocId = $docId;
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public function confirmRejectDoc(KycVerificationService $svc): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $doc = KycDocument::query()->findOrFail($this->rejectingDocId);

        try {
            $svc->setDocumentStatus($doc, KycStatus::Rejected, $this->rejectionReason, auth()->user());
            $this->showRejectModal = false;
            unset($this->documents, $this->selectedDocument, $this->subjectModel, $this->mandatoryCheck, $this->history);
            Flux::toast(variant: 'danger', text: __('Document rejected.'));
        } catch (ValidationException $e) {
            $this->addError('rejectionReason', $e->getMessage());
        }
    }

    public function openApprove(): void
    {
        $this->approveNote = '';
        $this->showApproveConfirm = true;
    }

    public function confirmApprove(KycVerificationService $svc): void
    {
        $type = $this->type === 'investor' ? Investor::class : Borrower::class;

        try {
            $svc->approveSubject($type, $this->subject, auth()->user(), $this->approveNote ?: null);
            $this->showApproveConfirm = false;
            unset($this->subjectModel, $this->mandatoryCheck, $this->history);
            Flux::toast(variant: 'success', text: __('Verification approved.'));
        } catch (\InvalidArgumentException $e) {
            $this->addError('approveNote', $e->getMessage());
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function openRejectOverall(): void
    {
        $this->overallRejectionReason = '';
        $this->showRejectOverallModal = true;
    }

    public function confirmRejectOverall(KycVerificationService $svc): void
    {
        $this->validate([
            'overallRejectionReason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $type = $this->type === 'investor' ? Investor::class : Borrower::class;
        $svc->rejectSubject($type, $this->subject, $this->overallRejectionReason, auth()->user());
        $this->showRejectOverallModal = false;
        unset($this->subjectModel, $this->mandatoryCheck, $this->history);
        Flux::toast(variant: 'danger', text: __('Verification rejected.'));
    }

    public function saveChanges(): void
    {
        unset($this->documents, $this->selectedDocument, $this->subjectModel, $this->mandatoryCheck, $this->history);
        Flux::toast(variant: 'success', text: __('Changes saved.'));
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ $type === 'investor' ? route('super-admin.verification.investors') : route('super-admin.verification.borrowers') }}" class="hover:text-blue-600">{{ __('Verification') }}</a>
        <flux:icon.chevron-right class="size-3" />
        <span class="text-slate-600">{{ $type === 'investor' ? $this->subjectModel->investor_code : $this->subjectModel->borrower_code }}</span>
    </div>

    <div class="fc-card p-5 sm:p-6">
        <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
            <div class="flex items-center gap-4">
                <flux:avatar :name="$type === 'investor' ? $this->subjectModel->name : $this->subjectModel->full_name" :initials="str($type === 'investor' ? $this->subjectModel->name : $this->subjectModel->full_name)->substr(0, 2)" class="size-14 text-base" />
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-semibold tracking-tight">{{ $type === 'investor' ? $this->subjectModel->name : $this->subjectModel->full_name }}</h2>
                        <x-status-badge :status="$this->subjectModel->verification_status" />
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600 ring-1 ring-inset ring-slate-500/20">{{ $type === 'investor' ? __('Investor') : __('Borrower') }}</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-400">{{ $type === 'investor' ? $this->subjectModel->investor_code : $this->subjectModel->borrower_code }} · {{ __('Registered :date', ['date' => $this->subjectModel->created_at->format('d M Y')]) }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="saveChanges" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm hover:border-slate-300 hover:text-slate-900">{{ __('Save Changes') }}</button>
                <button type="button" wire:click="openRejectOverall" class="rounded-xl border border-rose-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">{{ __('Reject Verification') }}</button>
                <button type="button" wire:click="openApprove" @disabled(!$this->mandatoryCheck['allVerified']) class="rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800 disabled:cursor-not-allowed disabled:opacity-50">{{ __('Approve Verification') }}</button>
            </div>
        </div>
        @if (!$this->mandatoryCheck['allVerified'])
            <p class="mt-3 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">{{ __('Approval is blocked until every mandatory document is verified. Pending/missing: :pending · Rejected: :rejected', ['pending' => $this->mandatoryCheck['pending'], 'rejected' => $this->mandatoryCheck['rejected']]) }}</p>
        @endif
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_420px]">
        <div class="space-y-4">
            <div class="fc-card p-5">
                <h3 class="text-sm font-semibold">{{ __('User Information') }}</h3>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs text-slate-400">{{ __('Full Name') }}</dt><dd class="font-medium text-slate-800">{{ $type === 'investor' ? $this->subjectModel->name : $this->subjectModel->full_name }}</dd></div>
                    <div><dt class="text-xs text-slate-400">{{ __('Mobile Number') }}</dt><dd class="font-medium text-slate-800">{{ $this->subjectModel->mobile }}</dd></div>
                    <div><dt class="text-xs text-slate-400">{{ __('Email') }}</dt><dd class="font-medium text-slate-800">{{ $type === 'investor' ? ($this->subjectModel->email ?: '—') : '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-400">{{ __('Date of Birth') }}</dt><dd class="font-medium text-slate-800">—</dd></div>
                    <div><dt class="text-xs text-slate-400">{{ __('User Type') }}</dt><dd class="font-medium text-slate-800">{{ $type === 'investor' ? __('Investor') : __('Borrower') }}</dd></div>
                    <div><dt class="text-xs text-slate-400">{{ __('Registration Date') }}</dt><dd class="font-medium text-slate-800">{{ $this->subjectModel->created_at->format('d M Y') }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-slate-400">{{ __('Address') }}</dt><dd class="font-medium leading-relaxed text-slate-800">{{ $this->subjectModel->address }}</dd></div>
                    <div><dt class="text-xs text-slate-400">{{ __('Current Verification Status') }}</dt><dd class="font-medium text-slate-800"><x-status-badge :status="$this->subjectModel->verification_status" /></dd></div>
                </dl>
            </div>

            <div class="fc-card p-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold">{{ __('Document List') }}</h3>
                    <span class="text-[11px] text-slate-400">{{ $this->documents->count() }} {{ __('uploaded') }}</span>
                </div>
                <ul class="mt-3 space-y-2">
                    @forelse ($this->documents as $document)
                        <li class="cursor-pointer rounded-xl border p-3 transition {{ $this->selectedDocument?->id === $document->id ? 'border-blue-300 bg-blue-50/50' : 'border-slate-200 hover:border-slate-300' }}" wire:click="selectDoc({{ $document->id }})">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-slate-800">{{ $document->type->label() }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-400">{{ $document->created_at->format('d M Y') }} · {{ $document->original_name }}</p>
                                </div>
                                <x-status-badge :status="$document->status" />
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <a href="{{ route('documents.preview', $document) }}" target="_blank" class="text-[11px] font-semibold text-blue-600 hover:text-blue-700" onclick="event.stopPropagation()">{{ __('Preview') }}</a>
                                <a href="{{ route('documents.download', $document) }}" class="text-[11px] font-semibold text-slate-500 hover:text-slate-700" onclick="event.stopPropagation()">{{ __('Download') }}</a>
                                <button type="button" wire:click.stop="verifyDoc({{ $document->id }})" class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-700">{{ __('Verify') }}</button>
                                <button type="button" wire:click.stop="openRejectDoc({{ $document->id }})" class="text-[11px] font-semibold text-rose-600 hover:text-rose-700">{{ __('Reject') }}</button>
                            </div>
                            @if ($document->status === \App\Enums\KycStatus::Rejected && $document->notes)
                                <p class="mt-2 rounded-lg bg-rose-50 p-2 text-[11px] text-rose-700">{{ $document->notes }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">{{ __('No KYC documents uploaded.') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="fc-card p-5">
                <h3 class="text-sm font-semibold">{{ __('Mandatory document check') }}</h3>
                <ul class="mt-3 space-y-2 text-xs">
                    @foreach ($this->mandatoryCheck['checks'] as $t => $s)
                        <li class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2">
                            <span>{{ \App\Enums\KycDocumentType::from($t)->label() }}</span>
                            <span class="font-semibold {{ $s === 'verified' ? 'text-emerald-700' : ($s === 'rejected' ? 'text-rose-700' : 'text-amber-700') }}">{{ $s === 'missing' ? __('Missing') : ucfirst($s) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="fc-card p-5">
                <h3 class="text-sm font-semibold">{{ __('Verification History') }}</h3>
                <ul class="mt-3 space-y-2">
                    @forelse ($this->history as $h)
                        <li class="rounded-lg border border-slate-100 px-3 py-2 text-xs">
                            <span class="font-semibold text-slate-700">{{ $h->action }}</span>
                            <span class="text-slate-400">· {{ $h->created_at->format('d M Y H:i') }} · {{ $h->actedBy?->name ?? __('System') }}</span>
                            @if ($h->rejection_reason) <p class="mt-1 text-rose-600">{{ $h->rejection_reason }}</p> @endif
                        </li>
                    @empty
                        <li class="text-xs text-slate-400">{{ __('No verification actions recorded yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="space-y-4">
            @if ($this->selectedDocument)
                <div class="fc-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div>
                            <h3 class="text-sm font-semibold">{{ $this->selectedDocument->type->label() }}</h3>
                            <p class="text-[11px] text-slate-400">{{ $this->selectedDocument->original_name }} · {{ $this->selectedDocument->created_at->format('d M Y') }}</p>
                        </div>
                        <x-status-badge :status="$this->selectedDocument->status" />
                    </div>
                    <div class="p-5">
                        <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50" id="kyc-preview">
                            @if ($this->selectedDocument->isImage())
                                <img src="{{ route('documents.preview', $this->selectedDocument) }}" alt="{{ $this->selectedDocument->type->label() }}" class="max-h-[480px] w-full object-contain transition-transform duration-200" id="kyc-preview-img">
                            @elseif ($this->selectedDocument->isPdf())
                                <iframe src="{{ route('documents.preview', $this->selectedDocument) }}" class="h-[480px] w-full" title="{{ $this->selectedDocument->type->label() }}"></iframe>
                            @else
                                <div class="flex h-48 items-center justify-center text-xs text-slate-400">{{ __('Preview not available for this file type.') }}</div>
                            @endif
                        </div>
                        @if ($this->selectedDocument->isImage())
                            <div class="mt-2 flex items-center gap-2 text-[11px]">
                                <button type="button" onclick="document.getElementById('kyc-preview-img').style.transform='scale(1.4)'" class="rounded-lg border border-slate-200 px-2 py-1 font-semibold text-slate-600">{{ __('Zoom +') }}</button>
                                <button type="button" onclick="document.getElementById('kyc-preview-img').style.transform='scale(1)'" class="rounded-lg border border-slate-200 px-2 py-1 font-semibold text-slate-600">{{ __('Reset') }}</button>
                            </div>
                        @endif

                        <dl class="mt-4 space-y-2.5 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Document Name') }}</dt><dd class="font-medium text-slate-800">{{ $this->selectedDocument->original_name }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Document Type') }}</dt><dd class="font-medium text-slate-800">{{ $this->selectedDocument->type->label() }}</dd></div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-400">{{ __('Document Number') }}</dt>
                                <dd class="flex items-center gap-2">
                                    <span class="font-mono font-medium text-slate-800">{{ $revealed ? ($this->selectedDocument->number ?: '—') : $this->selectedDocument->maskedNumber() }}</span>
                                    <button type="button" wire:click="toggleReveal" class="text-[11px] font-semibold text-blue-600">{{ $revealed ? __('Hide') : __('Reveal') }}</button>
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Upload Date') }}</dt><dd class="font-medium text-slate-800">{{ $this->selectedDocument->created_at->format('d M Y') }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Status') }}</dt><dd><x-status-badge :status="$this->selectedDocument->status" /></dd></div>
                            @if ($this->selectedDocument->notes)
                                <div class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600"><span class="font-semibold">{{ __('Rejection reason:') }}</span> {{ $this->selectedDocument->notes }}</div>
                            @endif
                        </dl>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('documents.preview', $this->selectedDocument) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white">{{ __('Preview Document') }}</a>
                            <button type="button" wire:click="verifyDoc({{ $this->selectedDocument->id }})" class="rounded-xl border border-emerald-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-emerald-700">{{ __('Verify') }}</button>
                            <button type="button" wire:click="openRejectDoc({{ $this->selectedDocument->id }})" class="rounded-xl border border-rose-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-rose-700">{{ __('Reject') }}</button>
                        </div>
                    </div>
                </div>
            @else
                <div class="fc-card p-8 text-center text-sm text-slate-400">{{ __('Select a document to preview.') }}</div>
            @endif
        </div>
    </div>

    @if ($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showRejectModal', false)"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <h3 class="text-base font-semibold">{{ __('Reject document') }}</h3>
                <form wire:submit="confirmRejectDoc" class="mt-4 space-y-3">
                    <flux:textarea wire:model="rejectionReason" :label="__('Rejection reason (mandatory)')" rows="3" required minlength="3" maxlength="2000" />
                    @error('rejectionReason') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showRejectModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl bg-rose-700">{{ __('Reject') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showApproveConfirm)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showApproveConfirm', false)"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <h3 class="text-base font-semibold">{{ __('Approve verification?') }}</h3>
                <p class="mt-1 text-xs text-slate-400">{{ __('All mandatory documents are verified. This action is recorded in the audit history.') }}</p>
                <form wire:submit="confirmApprove" class="mt-4 space-y-3">
                    <flux:textarea wire:model="approveNote" :label="__('Notes (optional)')" rows="2" maxlength="2000" />
                    @error('approveNote') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showApproveConfirm', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Confirm approval') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showRejectOverallModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showRejectOverallModal', false)"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <h3 class="text-base font-semibold">{{ __('Reject verification') }}</h3>
                <form wire:submit="confirmRejectOverall" class="mt-4 space-y-3">
                    <flux:textarea wire:model="overallRejectionReason" :label="__('Rejection reason (mandatory)')" rows="3" required minlength="3" maxlength="2000" />
                    @error('overallRejectionReason') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showRejectOverallModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl bg-rose-700">{{ __('Reject') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
