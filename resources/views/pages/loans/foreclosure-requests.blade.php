<?php

use App\Enums\Permission;
use App\Models\ForeclosureRequest;
use App\Services\ForeclosureService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Foreclosure requests')] class extends Component
{
    use WithPagination;

    public string $status = '';

    public string $search = '';

    public ?int $rejectingRequestId = null;

    public string $rejectionReason = '';

    public ?int $approvingRequestId = null;

    public ?int $payingRequestId = null;

    public string $paymentMethod = 'cash';

    public string $paymentReference = '';

    public function mount(): void
    {
        abort_unless($this->canManage(), 403);
    }

    public function canManage(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->canManageForeclosures();
    }

    /**
     * @return LengthAwarePaginator<int, ForeclosureRequest>
     */
    #[Computed]
    public function requests(): LengthAwarePaginator
    {
        return ForeclosureRequest::query()
            ->with(['loan.borrower', 'approvedBy'])
            ->when($this->status !== '', fn ($query) => $query->where('approval_status', $this->status))
            ->when($this->search !== '', function ($query): void {
                $term = '%'.strtolower($this->search).'%';

                $query->whereHas('loan', fn ($query) => $query
                    ->whereRaw('LOWER(loan_code) LIKE ?', [$term])
                    ->orWhereHas('borrower', fn ($query) => $query
                        ->whereRaw('LOWER(full_name) LIKE ?', [$term])));
            })
            ->latest('foreclosure_request_date')
            ->paginate(20);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function totals(): array
    {
        return [
            'pending' => ForeclosureRequest::pending()->count(),
            'approved' => ForeclosureRequest::approved()->count(),
            'rejected' => ForeclosureRequest::rejected()->count(),
            'paid' => ForeclosureRequest::paid()->count(),
        ];
    }

    public function approve(int $id): void
    {
        abort_unless($this->canManage(), 403);

        $this->approvingRequestId = null;

        $request = ForeclosureRequest::query()->findOrFail($id);

        if ($request->approval_status !== 'pending') {
            Flux::toast(variant: 'danger', text: __('Only pending requests can be approved.'));

            return;
        }

        app(ForeclosureService::class)->approveRequest($request, auth()->user());

        unset($this->requests);
        unset($this->totals);

        Flux::toast(variant: 'success', text: __('Foreclosure approved. Loan marked as foreclosed.'));
    }

    #[Computed]
    public function approvingRequest(): ?ForeclosureRequest
    {
        if ($this->approvingRequestId === null) {
            return null;
        }

        return ForeclosureRequest::query()
            ->with(['loan.borrower'])
            ->find($this->approvingRequestId);
    }

    public function openApprove(int $id): void
    {
        abort_unless($this->canManage(), 403);

        $request = ForeclosureRequest::query()->findOrFail($id);

        if ($request->approval_status !== 'pending') {
            Flux::toast(variant: 'danger', text: __('Only pending requests can be approved.'));

            return;
        }

        $this->approvingRequestId = $id;
    }

    public function openReject(int $id): void
    {
        abort_unless($this->canManage(), 403);

        $this->rejectingRequestId = $id;
        $this->rejectionReason = '';
    }

    public function reject(): void
    {
        abort_unless($this->canManage(), 403);

        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $request = ForeclosureRequest::query()->findOrFail($this->rejectingRequestId);

        if ($request->approval_status !== 'pending') {
            Flux::toast(variant: 'danger', text: __('Only pending requests can be rejected.'));
            $this->rejectingRequestId = null;

            return;
        }

        app(ForeclosureService::class)->rejectRequest($request, auth()->user(), $this->rejectionReason);

        $this->rejectingRequestId = null;

        Flux::toast(variant: 'success', text: __('Foreclosure request rejected.'));
    }

    public function openPayment(int $id): void
    {
        abort_unless($this->canManage(), 403);

        $this->payingRequestId = $id;
        $this->paymentMethod = 'cash';
        $this->paymentReference = '';
    }

    public function recordPayment(): void
    {
        abort_unless($this->canManage(), 403);

        $request = ForeclosureRequest::query()->findOrFail($this->payingRequestId);

        if ($request->approval_status !== 'approved' || $request->payment_status !== 'unpaid') {
            Flux::toast(variant: 'danger', text: __('This request is not awaiting payment.'));
            $this->payingRequestId = null;

            return;
        }

        app(ForeclosureService::class)->processPayment(
            $request,
            auth()->user(),
            $this->paymentMethod,
            filled($this->paymentReference) ? $this->paymentReference : null,
        );

        $this->payingRequestId = null;

        Flux::toast(variant: 'success', text: __('Foreclosure payment recorded.'));
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
};
?>

<div class="mx-auto max-w-[1500px] space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Loan management') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Foreclosure requests') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Review, approve and settle foreclosures on active loans. Admin and Super Admin users can manage foreclosure requests.') }}</p>
        </div>

        <a href="{{ route('loans.index') }}" wire:navigate class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900">
            {{ __('← Back to loans') }}
        </a>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($this->totals as $label => $count)
            <div class="fc-card p-4">
                <p class="fc-eyebrow">{{ __(ucfirst($label)) }}</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums">{{ $count }}</p>
            </div>
        @endforeach
    </div>

    <div class="fc-card p-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <label class="relative block w-full max-w-md">
            <flux:icon.magnifying-glass class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
            <input wire:model.live.debounce.400ms="search" type="search" placeholder="{{ __('Search loan or borrower…') }}" aria-label="{{ __('Search foreclosure requests') }}"
                   class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-700 placeholder-slate-400 focus:border-navy-900 focus:outline-none focus:ring-2 focus:ring-navy-900/10">
        </label>

        <div class="flex flex-wrap gap-1.5">
            @foreach (['' => __('All'), 'pending' => __('Pending'), 'approved' => __('Approved'), 'paid' => __('Paid'), 'rejected' => __('Rejected')] as $value => $label)
                <button type="button" wire:click="$set('status', '{{ $value }}')"
                        class="rounded-lg border px-2.5 py-1.5 text-[11px] font-semibold transition {{ $status === $value ? 'border-navy-900 bg-navy-900 text-white' : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="fc-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="fc-table-head">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ __('Loan / Borrower') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Requested') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Accrued interest') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Charges') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Settlement') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->requests as $request)
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <a href="{{ route('loans.show', $request->loan_id) }}" wire:navigate class="font-medium text-blue-600 hover:text-blue-700">{{ $request->loan->loan_code }}</a>
                                <p class="text-[11px] text-slate-400">{{ $request->loan->borrower->full_name }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $request->foreclosure_request_date?->format(\App\Support\Regional::dateFormat()) }}</td>
                            <td class="px-4 py-3 tabular-nums text-slate-600">
                                {{ Money::format($request->accrued_interest) }}
                                @if (Money::compare($request->waived_interest, '0') > 0)
                                    <p class="text-[11px] font-medium text-rose-500">{{ __(':amount waived', ['amount' => Money::format($request->waived_interest)]) }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 tabular-nums text-slate-600">{{ Money::format($request->foreclosure_charges) }}</td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums text-navy-800">{{ Money::format($request->total_settlement) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold {{ match ($request->approval_status) { 'pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-emerald-50 text-emerald-700', 'rejected' => 'bg-rose-50 text-rose-700', default => 'bg-slate-100 text-slate-600' } }}">
                                    {{ __(ucfirst($request->approval_status)) }}
                                </span>
                                @if ($request->payment_status === 'paid')
                                    <span class="mt-0.5 inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">{{ __('Paid') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($request->approval_status === 'pending')
                                    <div class="flex justify-end gap-2">
                                        <button type="button" wire:click="openApprove({{ $request->id }})" class="rounded-lg bg-navy-900 px-2.5 py-1.5 text-[11px] font-semibold text-white hover:bg-navy-800">
                                            {{ __('Approve') }}
                                        </button>
                                        <button type="button" wire:click="openReject({{ $request->id }})" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:border-rose-200 hover:text-rose-600">
                                            {{ __('Reject') }}
                                        </button>
                                    </div>
                                @elseif ($request->approval_status === 'approved' && $request->payment_status === 'unpaid')
                                    <button type="button" wire:click="openPayment({{ $request->id }})" class="rounded-lg bg-navy-900 px-2.5 py-1.5 text-[11px] font-semibold text-white hover:bg-navy-800">
                                        {{ __('Record payment') }}
                                    </button>
                                @else
                                    <span class="text-[11px] text-slate-400">{{ __('—') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">{{ __('No foreclosure requests found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->requests->hasPages())
            <div class="border-t border-slate-100 px-4 py-3">{{ $this->requests->links() }}</div>
        @endif
    </div>

    @if ($this->approvingRequest !== null)
        @php($approval = $this->approvingRequest)

        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('approvingRequestId', null)"></div>

            <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 ring-1 ring-amber-100">
                            <flux:icon.exclamation-triangle class="size-5" />
                        </span>
                        <div>
                            <h3 class="text-base font-semibold">{{ __('Approve foreclosure request') }}</h3>
                            <p class="mt-0.5 text-xs text-slate-400">{{ __('The loan will be marked as foreclosed and the settlement recorded as a payment.') }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="$set('approvingRequestId', null)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                        <flux:icon.x-mark class="size-4" />
                    </button>
                </div>

                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">{{ __('Loan / borrower') }}</p>
                            <p class="mt-1 text-sm font-semibold text-slate-800">{{ $approval->loan->loan_code }}</p>
                            <p class="text-xs text-slate-500">{{ $approval->loan->borrower->full_name }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">{{ __('Effective date') }}</p>
                            <p class="mt-1 text-sm font-medium text-slate-700">{{ $approval->effective_foreclosure_date?->format(\App\Support\Regional::dateFormat()) }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-slate-200 p-4">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">{{ __('Settlement breakdown') }}</p>

                    <dl class="mt-3 space-y-1.5 text-xs">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">{{ __('Outstanding principal') }}</dt>
                            <dd class="font-medium tabular-nums text-slate-700">{{ Money::format($approval->outstanding_principal) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">{{ __('Accrued interest') }}</dt>
                            <dd class="font-medium tabular-nums text-slate-700">{{ Money::format($approval->accrued_interest) }}</dd>
                        </div>
                        @if (Money::compare($approval->waived_interest, '0') > 0)
                            <div class="flex justify-between text-rose-600">
                                <dt>{{ __('Accrued interest waived') }}</dt>
                                <dd class="font-medium tabular-nums">− {{ Money::format($approval->waived_interest) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <dt class="text-slate-500">{{ __('Other outstanding dues') }}</dt>
                            <dd class="font-medium tabular-nums text-slate-700">{{ Money::format($approval->other_outstanding_dues) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">{{ __('Foreclosure charges') }}</dt>
                            <dd class="font-medium tabular-nums text-slate-700">{{ Money::format($approval->foreclosure_charges) }}</dd>
                        </div>
                        @if (Money::compare($approval->eligible_adjustments, '0') > 0)
                            <div class="flex justify-between">
                                <dt class="text-slate-500">{{ __('Eligible adjustments') }}</dt>
                                <dd class="font-medium tabular-nums text-slate-700">− {{ Money::format($approval->eligible_adjustments) }}</dd>
                            </div>
                        @endif
                    </dl>

                    <div class="mt-3 flex items-center justify-between border-t border-slate-200 pt-3">
                        <p class="text-xs font-semibold text-slate-700">{{ __('Total settlement') }}</p>
                        <p class="text-lg font-semibold tabular-nums text-navy-900">{{ Money::format($approval->total_settlement) }}</p>
                    </div>
                </div>

                <p class="mt-4 flex gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-[11px] leading-relaxed text-amber-700">
                    <flux:icon.exclamation-triangle class="mt-px size-4 shrink-0" />
                    <span>{{ __('This action cannot be undone. Approving closes the loan and creates a foreclosure settlement receipt.') }}</span>
                </p>

                <div class="mt-4 flex items-center justify-end gap-2">
                    <button type="button" wire:click="$set('approvingRequestId', null)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                    <flux:button wire:click="approve({{ $approval->id }})" variant="primary" class="!rounded-xl">{{ __('Approve & foreclose') }}</flux:button>
                </div>
            </div>
        </div>
    @endif

    @if ($rejectingRequestId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('rejectingRequestId', null)"></div>

            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold">{{ __('Reject foreclosure request') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">{{ __('The requester will be notified of the rejection.') }}</p>
                    </div>
                    <button type="button" wire:click="$set('rejectingRequestId', null)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                        <flux:icon.x-mark class="size-4" />
                    </button>
                </div>

                <form wire:submit="reject" class="mt-4 space-y-3">
                    <flux:textarea wire:model="rejectionReason" :label="__('Rejection reason')" rows="3" maxlength="1000" required />
                    @error('rejectionReason') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('rejectingRequestId', null)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="danger" class="!rounded-xl">{{ __('Reject request') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($payingRequestId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('payingRequestId', null)"></div>

            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold">{{ __('Record foreclosure payment') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">{{ __('Creates an EMI payment receipt for the settlement amount.') }}</p>
                    </div>
                    <button type="button" wire:click="$set('payingRequestId', null)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                        <flux:icon.x-mark class="size-4" />
                    </button>
                </div>

                <form wire:submit="recordPayment" class="mt-4 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <flux:select wire:model="paymentMethod" :label="__('Mode')" required>
                            <option value="cash">{{ __('Cash') }}</option>
                            <option value="upi">{{ __('UPI') }}</option>
                            <option value="bank_transfer">{{ __('Bank transfer') }}</option>
                            <option value="cheque">{{ __('Cheque') }}</option>
                            <option value="other">{{ __('Other') }}</option>
                        </flux:select>
                        <flux:input wire:model="paymentReference" :label="__('Reference')" placeholder="{{ __('Optional receipt/reference no') }}" maxlength="255" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('payingRequestId', null)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Record payment') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
