<?php

use App\Enums\RelationshipType;
use App\Models\Guarantor;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Guarantor details')] class extends Component
{
    public Guarantor $guarantor;

    public function mount(Guarantor $guarantor): void
    {
        $this->guarantor = $guarantor->load(['loans.borrower']);
    }

    #[Computed]
    public function loans()
    {
        return $this->guarantor->loans;
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ route('guarantors.index') }}" class="hover:text-blue-600">{{ __('Guarantors') }}</a>
        <flux:icon.chevron-right class="size-3" />
        <span class="text-slate-600">{{ $guarantor->full_name }}</span>
    </div>

    <div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        {{ __('Guarantor profile details cannot be edited from this page. To update guarantor information, please navigate to the Borrower page and edit the corresponding borrower\'s profile.') }}
    </div>

    <div class="fc-card p-5 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <flux:avatar :name="$guarantor->full_name" :initials="str($guarantor->full_name)->substr(0, 2)" class="size-14 text-base" />
                <div>
                    <h2 class="text-xl font-semibold tracking-tight">{{ $guarantor->full_name }}</h2>
                    <p class="mt-1 text-xs text-slate-400">{{ $guarantor->mobile_number }} · {{ __('Added :date', ['date' => $guarantor->created_at->format(\App\Support\Regional::dateFormat())]) }}</p>
                </div>
            </div>

            @if ($guarantor->borrower_id)
                <a href="{{ route('borrowers.edit', $guarantor->borrower_id) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:border-slate-300 hover:text-slate-900">
                    {{ __('Edit borrower profile') }}
                </a>
            @endif
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="space-y-4">
            <div class="fc-card p-5">
                <h3 class="text-sm font-semibold">{{ __('Contact & identity') }}</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Mobile') }}</dt><dd class="font-medium text-slate-800">{{ $guarantor->mobile_number }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Alternate') }}</dt><dd class="font-medium text-slate-800">{{ $guarantor->alternate_mobile ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Email') }}</dt><dd class="truncate font-medium text-slate-800">{{ $guarantor->email ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Occupation') }}</dt><dd class="font-medium text-slate-800">{{ $guarantor->occupation ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Identity') }}</dt><dd class="font-medium text-slate-800">{{ $guarantor->identity_document_type?->label() ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Document') }}</dt><dd class="font-medium text-slate-800">{{ $guarantor->maskedIdentityDocumentNumber() ?: '—' }}</dd></div>
                </dl>
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Address') }}</p>
                    <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $guarantor->address }}@if ($guarantor->city || $guarantor->state || $guarantor->pincode), {{ collect([$guarantor->city, $guarantor->state])->filter()->implode(', ') }}@if ($guarantor->pincode) - {{ $guarantor->pincode }}@endif @endif</p>
                </div>
                @if ($guarantor->notes)
                    <div class="mt-4 rounded-xl bg-amber-50 p-3 text-xs leading-relaxed text-amber-800">{{ $guarantor->notes }}</div>
                @endif
            </div>
        </div>

        <div class="xl:col-span-2">
            <div class="fc-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold">{{ __('Linked loans') }}</h3>
                    <span class="text-xs text-slate-400">{{ $this->loans->count() }} {{ __('loans') }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="fc-table-head">
                            <tr>
                                <th class="px-5 py-3">{{ __('Loan') }}</th>
                                <th class="px-5 py-3">{{ __('Borrower') }}</th>
                                <th class="px-5 py-3">{{ __('Relationship (this loan)') }}</th>
                                <th class="px-5 py-3">{{ __('Status') }}</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($this->loans as $loan)
                                @php
                                    $pivotRelationship = $loan->pivot?->relationship;
                                    $pivotCustom = $loan->pivot?->custom_relationship;
                                    $pivotLabel = $pivotRelationship === \App\Enums\RelationshipType::Other->value
                                        ? ($pivotCustom ?: \App\Enums\RelationshipType::Other->label())
                                        : (\App\Enums\RelationshipType::tryFrom((string) $pivotRelationship)?->label() ?? '—');
                                @endphp
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="px-5 py-3 font-medium text-slate-800">{{ $loan->loan_code }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $loan->borrower->full_name }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $pivotLabel }}</td>
                                    <td class="px-5 py-3"><x-status-badge :status="$loan->status" /></td>
                                    <td class="px-5 py-3 text-end">
                                        <a href="{{ route('loans.show', $loan) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700">{{ __('View loan') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-xs text-slate-400">{{ __('This guarantor is not linked to any loans yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
