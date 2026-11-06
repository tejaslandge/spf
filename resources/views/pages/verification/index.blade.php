<?php

use App\Enums\KycStatus;
use App\Models\Borrower;
use App\Models\Investor;
use App\Models\KycDocument;
use App\Services\DepositAllocationService;
use App\Services\KycVerificationService;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Verification')] class extends Component
{
    use WithPagination;

    public string $mode = 'borrower';

    public string $search = '';

    public string $status = '';

    public string $date = '';

    public function mount(?string $type = null): void
    {
        if ($type !== null) {
            $this->mode = $type;
        } else {
            $path = request()->path();
            $this->mode = str_contains($path, 'investor') ? 'investor' : 'borrower';
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingDate(): void
    {
        $this->resetPage();
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
        $this->reset(['search', 'status', 'date']);
        $this->resetPage();
        $this->redirectRoute($mode === 'investor' ? 'super-admin.verification.investors' : 'super-admin.verification.borrowers', navigate: true);
    }

    #[Computed]
    public function records()
    {
        $query = $this->mode === 'investor'
            ? Investor::query()->withCount('kycDocuments')
            : Borrower::query()->withCount('kycDocuments');

        if ($this->search !== '') {
            $query->search($this->search);
        }

        if ($this->date !== '') {
            $query->whereDate('created_at', $this->date);
        }

        $rows = $query->latest()->paginate(12);

        $mode = $this->mode;
        $status = $this->status;

        $collection = $rows->getCollection()->map(function ($row) use ($mode) {
            $type = $mode === 'investor' ? Investor::class : Borrower::class;
            $row->setAttribute('verification_status', KycDocument::overallStatusFor($type, (int) $row->getKey()));

            return $row;
        });

        if ($status !== '') {
            $collection = $collection->filter(fn ($row) => $row->verification_status->value === $status)->values();
        }

        $rows->setCollection($collection);

        return $rows;
    }

    #[Computed]
    public function stats(): array
    {
        $type = $this->mode === 'investor' ? Investor::class : Borrower::class;
        $ids = ($this->mode === 'investor' ? Investor::query() : Borrower::query())->pluck('id')->all();

        $pending = 0;
        $verified = 0;
        $rejected = 0;

        foreach ($ids as $id) {
            $s = KycDocument::overallStatusFor($type, (int) $id);
            match ($s) {
                KycStatus::Pending => $pending++,
                KycStatus::Verified => $verified++,
                KycStatus::Rejected => $rejected++,
            };
        }

        return [
            'total' => count($ids),
            'pending' => $pending,
            'verified' => $verified,
            'rejected' => $rejected,
        ];
    }

    public function verifyDoc(int $docId, KycVerificationService $svc): void
    {
        $doc = KycDocument::query()->findOrFail($docId);

        try {
            $svc->setDocumentStatus($doc, KycStatus::Verified, null, auth()->user());
            unset($this->records, $this->stats);
            Flux::toast(variant: 'success', text: __('Document verified.'));
        } catch (ValidationException $e) {
            $this->addError('document', $e->getMessage());
        }
    }

    public function rejectDoc(int $docId): void
    {
        Flux::modal(__('Reject document'))->show();
        $this->rejectingDocId = $docId;
        $this->rejectionReason = '';
        $this->showRejectModal = true;
    }

    public int $rejectingDocId = 0;

    public string $rejectionReason = '';

    public bool $showRejectModal = false;

    public function confirmReject(KycVerificationService $svc): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $doc = KycDocument::query()->findOrFail($this->rejectingDocId);

        try {
            $svc->setDocumentStatus($doc, KycStatus::Rejected, $this->rejectionReason, auth()->user());
            $this->showRejectModal = false;
            unset($this->records, $this->stats);
            Flux::toast(variant: 'danger', text: __('Document rejected.'));
        } catch (ValidationException $e) {
            $this->addError('rejectionReason', $e->getMessage());
        }
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Verification Management') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('KYC Document Verification') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Super Admin only — verify borrower and investor documents, approve or reject overall.',) }}</p>
        </div>
        <div class="flex gap-2">
            <button type="button" wire:click="setMode('borrower')" class="{{ $mode === 'borrower' ? 'bg-navy-900 text-white' : 'bg-white text-slate-600 border border-slate-200' }} rounded-xl px-3.5 py-2.5 text-xs font-semibold">{{ __('Borrower Verification') }}</button>
            <button type="button" wire:click="setMode('investor')" class="{{ $mode === 'investor' ? 'bg-navy-900 text-white' : 'bg-white text-slate-600 border border-slate-200' }} rounded-xl px-3.5 py-2.5 text-xs font-semibold">{{ __('Investor Verification') }}</button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card :label="__('Total Applications')" :value="$this->stats['total']" icon="document-text" tone="navy" />
        <x-stat-card :label="__('Pending Verification')" :value="$this->stats['pending']" icon="clock" tone="amber" />
        <x-stat-card :label="__('Verified')" :value="$this->stats['verified']" icon="check-circle" tone="emerald" />
        <x-stat-card :label="__('Rejected')" :value="$this->stats['rejected']" icon="x-circle" tone="rose" />
    </div>

    <div class="fc-card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="relative w-full lg:max-w-xs">
                <flux:icon.magnifying-glass class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search Name, Mobile or ID…') }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pe-3 ps-9 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="status" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none">
                    <option value="">{{ __('All statuses') }}</option>
                    <option value="pending">{{ __('Pending') }}</option>
                    <option value="verified">{{ __('Verified') }}</option>
                    <option value="rejected">{{ __('Rejected') }}</option>
                </select>
                <input type="date" wire:model.live="date" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none" title="{{ __('Submission Date') }}">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="fc-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('ID') }}</th>
                        <th class="px-5 py-3">{{ __('Name') }}</th>
                        <th class="px-5 py-3">{{ __('Mobile') }}</th>
                        <th class="px-5 py-3">{{ __('Email') }}</th>
                        <th class="px-5 py-3">{{ __('Documents') }}</th>
                        <th class="px-5 py-3">{{ __('Verification Status') }}</th>
                        <th class="px-5 py-3">{{ __('Submitted Date') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->records as $row)
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-600">#{{ $row->id }}</td>
                            <td class="px-5 py-3">
                                <span class="font-medium text-slate-800">{{ $mode === 'investor' ? $row->name : $row->full_name }}</span>
                                <p class="text-[11px] text-slate-400">{{ $mode === 'investor' ? $row->investor_code : $row->borrower_code }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $row->mobile }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $mode === 'investor' ? ($row->email ?: '—') : '—' }}</td>
                            <td class="px-5 py-3 text-slate-600">{{ $row->kyc_documents_count }}</td>
                            <td class="px-5 py-3"><x-status-badge :status="$row->verification_status" /></td>
                            <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500">{{ $row->created_at?->format('d M Y') ?? '—' }}</td>
                            <td class="px-5 py-3 text-end">
                                <a href="{{ route('super-admin.verification.show', ['type' => $mode, 'subject' => $row->id]) }}" class="inline-flex items-center gap-1 rounded-lg bg-navy-900 px-3 py-2 text-[11px] font-semibold text-white hover:bg-navy-800">{{ __('View Details') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-14 text-center"><p class="text-sm font-medium text-slate-600">{{ __('No verification applications found.') }}</p><p class="mt-1 text-xs text-slate-400">{{ __('Upload KYC documents on the borrower/investor record to appear here.') }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->records->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $this->records->links() }}</div>
        @endif
    </div>

    @if ($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showRejectModal', false)"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <h3 class="text-base font-semibold">{{ __('Reject document') }}</h3>
                <p class="mt-0.5 text-xs text-slate-400">{{ __('A rejection reason is mandatory and will be stored in the audit history.') }}</p>
                <form wire:submit="confirmReject" class="mt-4 space-y-3">
                    <flux:textarea wire:model="rejectionReason" :label="__('Rejection reason')" rows="3" required minlength="3" maxlength="2000" />
                    @error('rejectionReason') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showRejectModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl bg-rose-700">{{ __('Reject') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
