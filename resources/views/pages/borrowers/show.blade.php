<?php

use App\Enums\KycStatus;
use App\Models\Borrower;
use App\Models\EmiPayment;
use App\Models\KycDocument;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Borrower profile')] class extends Component
{
    public Borrower $borrower;

    public function mount(Borrower $borrower): void
    {
        $this->borrower = $borrower->loadCount('loans');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Loan>
     */
    #[Computed]
    public function loans()
    {
        return $this->borrower->loans()
            ->withCount('payments')
            ->latest()
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, EmiPayment>
     */
    #[Computed]
    public function payments()
    {
        return EmiPayment::query()
            ->with('loan')
            ->whereHas('loan', fn ($query) => $query->where('borrower_id', $this->borrower->id))
            ->latest('payment_date')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function documents()
    {
        return $this->borrower->kycDocuments()->latest()->get();
    }

    #[Computed]
    public function totals(): array
    {
        $loans = $this->borrower->loans();

        return [
            'sanctioned' => (string) (clone $loans)->sum('principal_amount'),
            'outstanding' => (string) (clone $loans)->sum('outstanding_principal'),
            'next_due' => (clone $loans)->where('status', 'active')->orderBy('first_emi_date')->value('first_emi_date'),
        ];
    }

    public function verifyDocument(int $documentId): void
    {
        $document = $this->borrower->kycDocuments()->findOrFail($documentId);

        $document->update([
            'status' => KycStatus::Verified,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        unset($this->documents);
        Flux::toast(variant: 'success', text: __('Document verified.'));
    }

    public function rejectDocument(int $documentId): void
    {
        $document = $this->borrower->kycDocuments()->findOrFail($documentId);

        $document->update([
            'status' => KycStatus::Rejected,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        unset($this->documents);
        Flux::toast(variant: 'danger', text: __('Document rejected.'));
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ route('borrowers.index') }}" class="hover:text-blue-600">{{ __('Borrowers') }}</a>
        <flux:icon.chevron-right class="size-3" />
        <span class="text-slate-600">{{ $borrower->borrower_code }}</span>
    </div>

    <div class="fc-card p-5 sm:p-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div class="flex items-center gap-4">
                <flux:avatar :name="$borrower->full_name" :initials="str($borrower->full_name)->substr(0, 2)" class="size-14 text-base" />
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-semibold tracking-tight">{{ $borrower->full_name }}</h2>
                        <x-status-badge :status="$borrower->status" />
                    </div>
                    <p class="mt-1 text-xs text-slate-400">{{ $borrower->borrower_code }} · {{ __('Joined :date', ['date' => $borrower->created_at->format(\App\Support\Regional::dateFormat())]) }}</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('borrowers.edit', $borrower) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900">
                    <flux:icon.pencil-square class="size-4" />
                    {{ __('Edit') }}
                </a>
                <a href="{{ route('loans.create', ['borrower' => $borrower->id]) }}" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                    <flux:icon.plus class="size-4" />
                    {{ __('New loan') }}
                </a>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="__('Total sanctioned')" :value="\App\Support\Money::format($this->totals['sanctioned'])" :hint="__(':count loans', ['count' => $this->loans->count()])" icon="banknotes" tone="navy" />
        <x-stat-card :label="__('Outstanding principal')" :value="\App\Support\Money::format($this->totals['outstanding'])" :hint="__('Across all loans')" icon="scale" tone="blue" />
        <x-stat-card :label="__('Next EMI')" :value="$this->totals['next_due'] ? \Illuminate\Support\Carbon::parse($this->totals['next_due'])->format(\App\Support\Regional::dateFormat()) : '—'" :hint="__('Earliest active loan')" icon="calendar-days" tone="amber" />
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="space-y-4">
            <div class="fc-card p-5">
                <h3 class="text-sm font-semibold">{{ __('Contact') }}</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Mobile') }}</dt><dd class="font-medium text-slate-800">{{ $borrower->mobile }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Alternate') }}</dt><dd class="font-medium text-slate-800">{{ $borrower->alternate_mobile ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Emergency') }}</dt><dd class="text-end font-medium text-slate-800">{{ $borrower->emergency_contact_name }}<br><span class="text-xs font-normal text-slate-500">{{ $borrower->emergency_contact_number }}</span></dd></div>
                </dl>
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Address') }}</p>
                    <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $borrower->address }}</p>
                </div>
                @if ($borrower->notes)
                    <div class="mt-4 rounded-xl bg-amber-50 p-3 text-xs leading-relaxed text-amber-800">{{ $borrower->notes }}</div>
                @endif
            </div>

            <div class="fc-card p-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold">{{ __('KYC documents') }}</h3>
                    <span class="text-[11px] text-slate-400">{{ $this->documents->count() }} {{ __('uploaded') }}</span>
                </div>

                <ul class="mt-3 space-y-2">
                    @forelse ($this->documents as $document)
                        <li class="rounded-xl border border-slate-200 p-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-slate-800">{{ $document->type->label() }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-400">{{ $document->created_at->format(\App\Support\Regional::dateFormat()) }} · {{ $document->original_name }}</p>
                                </div>
                                <x-status-badge :status="$document->status" />
                            </div>

                            <div class="mt-2 flex items-center gap-2">
                                <a href="{{ route('documents.download', $document) }}" class="text-[11px] font-semibold text-blue-600 hover:text-blue-700">{{ __('Download') }}</a>
                                @if ($document->status === \App\Enums\KycStatus::Pending)
                                    <button type="button" wire:click="verifyDocument({{ $document->id }})" class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-700">{{ __('Verify') }}</button>
                                    <button type="button" wire:click="rejectDocument({{ $document->id }})" class="text-[11px] font-semibold text-rose-600 hover:text-rose-700">{{ __('Reject') }}</button>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">{{ __('No KYC documents uploaded.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="space-y-4 xl:col-span-2">
            <div class="fc-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold">{{ __('Loans') }}</h3>
                    <a href="{{ route('loans.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">{{ __('All loans') }}</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="fc-table-head">
                            <tr>
                                <th class="px-5 py-3">{{ __('Loan') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('Principal') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('EMI') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('Outstanding') }}</th>
                                <th class="px-5 py-3">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($this->loans as $loan)
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="px-5 py-3">
                                        <a href="{{ route('loans.show', $loan) }}" class="font-medium text-blue-600 hover:text-blue-700">{{ $loan->loan_code }}</a>
                                        <p class="text-[11px] text-slate-400">{{ $loan->interest_method->label() }} · {{ $loan->tenure_months }} {{ __('months') }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-slate-700">{{ \App\Support\Money::format($loan->principal_amount) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-slate-700">{{ \App\Support\Money::format($loan->emi_amount) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-end font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($loan->outstanding_principal) }}</td>
                                    <td class="px-5 py-3"><x-status-badge :status="$loan->status" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-xs text-slate-400">{{ __('No loans issued to this borrower yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="fc-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold">{{ __('Recent repayments') }}</h3>
                    <a href="{{ route('collections.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">{{ __('Collections') }}</a>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($this->payments as $payment)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                <flux:icon.arrow-down-left class="size-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold text-slate-800">{{ $payment->receipt_no }} · {{ $payment->loan->loan_code }}</p>
                                <p class="text-[11px] text-slate-400">{{ $payment->payment_date->format(\App\Support\Regional::dateFormat()) }} · {{ $payment->payment_method->label() }}</p>
                            </div>
                            <span class="text-xs font-semibold tabular-nums text-emerald-600">+{{ \App\Support\Money::format($payment->amount) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-xs text-slate-400">{{ __('No repayments recorded yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
