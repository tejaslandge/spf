<?php

use App\Enums\DepositStatus;
use App\Models\DepositStatusHistory;
use App\Models\InvestorDeposit;
use App\Services\DepositAllocationService;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Deposit Allocation')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public ?int $selected = null;

    public bool $showModal = false;

    public string $newStatus = 'pending';

    public string $reason = '';

    public string $notes = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function deposits()
    {
        $q = InvestorDeposit::query()->with('investor');

        if ($this->search !== '') {
            $term = '%'.strtolower($this->search).'%';
            $q->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(transaction_reference) LIKE ?', [$term])
                    ->orWhere('id', is_numeric($this->search) ? (int) $this->search : 0)
                    ->orWhereHas('investor', function ($iq) use ($term) {
                        $iq->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(investor_code) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(mobile) LIKE ?', [$term]);
                    });
            });
        }

        if ($this->status !== '') {
            $q->where('status', $this->status);
        }

        $rows = $q->latest('deposited_at')->latest('id')->paginate(\App\Services\Settings\UserPreferenceService::rowsPerPageFor(auth()->user(), 12));

        $svc = app(DepositAllocationService::class);
        $rows->through(function (InvestorDeposit $d) use ($svc) {
            $t = $svc->totalsFor($d);
            $d->setAttribute('allocated_amount', $t['allocated_amount']);
            $d->setAttribute('available_amount', $t['available_amount']);
            $d->setAttribute('deposit_amount', $t['deposit_amount']);

            return $d;
        });

        return $rows;
    }

    #[Computed]
    public function selectedDeposit(): ?InvestorDeposit
    {
        if ($this->selected === null) {
            return null;
        }

        $d = InvestorDeposit::query()->with(['investor', 'clearedBy'])->find($this->selected);

        if ($d) {
            $t = app(DepositAllocationService::class)->totalsFor($d);
            $d->setAttribute('allocated_amount', $t['allocated_amount']);
            $d->setAttribute('available_amount', $t['available_amount']);
            $d->setAttribute('deposit_amount', $t['deposit_amount']);
        }

        return $d;
    }

    #[Computed]
    public function selectedHistory()
    {
        if ($this->selected === null) {
            return collect();
        }

        return DepositStatusHistory::query()
            ->where('investor_deposit_id', $this->selected)
            ->with('changedBy')
            ->latest()
            ->limit(20)
            ->get();
    }

    public function openModal(int $id): void
    {
        $this->selected = $id;
        $d = $this->selectedDeposit;
        $this->newStatus = $d?->status->value ?? 'pending';
        $this->reason = '';
        $this->notes = '';
        $this->showModal = true;
    }

    public function confirmUpdate(DepositAllocationService $svc): void
    {
        $this->validate([
            'newStatus' => ['required', 'in:pending,cleared,rejected'],
            'reason' => ['required_if:newStatus,rejected', 'nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $deposit = InvestorDeposit::query()->findOrFail($this->selected);

        try {
            $svc->updateStatus(
                $deposit,
                DepositStatus::from($this->newStatus),
                $this->reason ?: null,
                $this->notes ?: null,
                auth()->user(),
            );

            $this->showModal = false;
            unset($this->deposits, $this->selectedDeposit, $this->selectedHistory);
            Flux::toast(variant: 'success', text: __('Deposit status updated.'));
        } catch (ValidationException $e) {
            $this->addError('reason', $e->getMessage());
        }
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div>
        <p class="fc-eyebrow">{{ __('Investor Management') }}</p>
        <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Deposit Allocation') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('Super Admin only — manage cleared / pending / rejected deposit funds. Status changes never create, delete or modify loan allocations.') }}</p>
    </div>

    <div class="fc-card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="relative w-full lg:max-w-xs">
                <flux:icon.magnifying-glass class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search Investor, Deposit ID…') }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pe-3 ps-9 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20">
            </div>
            <select wire:model.live="status" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none">
                <option value="">{{ __('All statuses') }}</option>
                <option value="pending">{{ __('Pending') }}</option>
                <option value="cleared">{{ __('Cleared') }}</option>
                <option value="rejected">{{ __('Rejected') }}</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="fc-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('Deposit ID') }}</th>
                        <th class="px-5 py-3">{{ __('Investor Name') }}</th>
                        <th class="px-5 py-3">{{ __('Investor ID') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Deposit Amount') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Allocated Amount') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Available Amount') }}</th>
                        <th class="px-5 py-3">{{ __('Allocation Status') }}</th>
                        <th class="px-5 py-3">{{ __('Deposit Date') }}</th>
                        <th class="px-5 py-3 text-end">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->deposits as $deposit)
                        <tr class="transition hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-slate-700">#{{ $deposit->id }}<p class="font-sans text-[11px] text-slate-400">{{ $deposit->transaction_reference ?: '—' }}</p></td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <flux:avatar :name="$deposit->investor->name" :initials="str($deposit->investor->name)->substr(0, 2)" size="sm" />
                                    <span class="font-medium text-slate-800">{{ $deposit->investor->name }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500">{{ $deposit->investor->investor_code }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($deposit->deposit_amount ?? $deposit->amount) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end tabular-nums text-slate-600">{{ \App\Support\Money::format($deposit->allocated_amount ?? '0.00') }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-end font-semibold tabular-nums {{ $deposit->status === \App\Enums\DepositStatus::Cleared ? 'text-emerald-700' : 'text-slate-500' }}">{{ \App\Support\Money::format($deposit->available_amount ?? '0.00') }}</td>
                            <td class="px-5 py-3"><x-status-badge :status="$deposit->status" /></td>
                            <td class="whitespace-nowrap px-5 py-3 text-xs text-slate-500">{{ $deposit->deposited_at->format(\App\Support\Regional::dateFormat()) }}</td>
                            <td class="px-5 py-3 text-end">
                                <div class="flex justify-end gap-1">
                                    <button type="button" wire:click="openModal({{ $deposit->id }})" class="text-[11px] font-semibold text-blue-600 hover:text-blue-700">{{ __('View') }}</button>
                                    <button type="button" wire:click="openModal({{ $deposit->id }})" class="text-[11px] font-semibold text-slate-600 hover:text-slate-900">{{ __('Update Status') }}</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-14 text-center"><p class="text-sm font-medium text-slate-600">{{ __('No deposits found.') }}</p><p class="mt-1 text-xs text-slate-400">{{ __('Record a deposit on the investor profile.') }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->deposits->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $this->deposits->links() }}</div>
        @endif
    </div>

    @if ($this->selectedDeposit)
        <div class="fc-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-sm font-semibold">{{ __('Deposit #') }}{{ $this->selectedDeposit->id }} · {{ $this->selectedDeposit->investor->name }}</h3>
                <x-status-badge :status="$this->selectedDeposit->status" />
            </div>
            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                <div><dt class="text-xs text-slate-400">{{ __('Deposit Amount') }}</dt><dd class="font-semibold tabular-nums">{{ \App\Support\Money::format($this->selectedDeposit->deposit_amount) }}</dd></div>
                <div><dt class="text-xs text-slate-400">{{ __('Allocated') }}</dt><dd class="font-semibold tabular-nums">{{ \App\Support\Money::format($this->selectedDeposit->allocated_amount) }}</dd></div>
                <div><dt class="text-xs text-slate-400">{{ __('Available') }}</dt><dd class="font-semibold tabular-nums">{{ \App\Support\Money::format($this->selectedDeposit->available_amount) }}</dd></div>
            </dl>
            <h4 class="mt-4 text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Status history') }}</h4>
            <ul class="mt-2 space-y-2">
                @forelse ($this->selectedHistory as $h)
                    <li class="rounded-lg border border-slate-100 px-3 py-2 text-xs">
                        <span>{{ $h->old_status?->value ?? '—' }} → <strong>{{ $h->new_status->value }}</strong></span>
                        <span class="text-slate-400"> · {{ $h->created_at->format(\App\Support\Regional::dateFormat().' '.\App\Support\Regional::timeFormat()) }} · {{ $h->changedBy?->name ?? __('System') }}</span>
                        @if ($h->rejection_reason) <p class="mt-1 text-rose-600">{{ $h->rejection_reason }}</p> @endif
                        @if ($h->notes) <p class="mt-1 text-slate-500">{{ $h->notes }}</p> @endif
                    </li>
                @empty
                    <li class="text-xs text-slate-400">{{ __('No status changes recorded yet.') }}</li>
                @endforelse
            </ul>
        </div>
    @endif

    @if ($showModal && $this->selectedDeposit)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold">{{ __('Update Deposit Status') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">{{ $this->selectedDeposit->investor->name }} · #{{ $this->selectedDeposit->id }}</p>
                    </div>
                    <button type="button" wire:click="$set('showModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><flux:icon.x-mark class="size-4" /></button>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3 text-sm">
                    <div><dt class="text-xs text-slate-400">{{ __('Investor Name') }}</dt><dd class="font-medium">{{ $this->selectedDeposit->investor->name }}</dd></div>
                    <div><dt class="text-xs text-slate-400">{{ __('Deposit ID') }}</dt><dd class="font-medium">#{{ $this->selectedDeposit->id }}</dd></div>
                    <div><dt class="text-xs text-slate-400">{{ __('Deposit Amount') }}</dt><dd class="font-medium tabular-nums">{{ \App\Support\Money::format($this->selectedDeposit->deposit_amount) }}</dd></div>
                    <div><dt class="text-xs text-slate-400">{{ __('Current Status') }}</dt><dd><x-status-badge :status="$this->selectedDeposit->status" /></dd></div>
                </dl>

                <form wire:submit="confirmUpdate" class="mt-4 space-y-3">
                    <flux:select wire:model="newStatus" :label="__('New Status')" required>
                        <option value="pending">{{ __('Pending') }}</option>
                        <option value="cleared">{{ __('Cleared') }}</option>
                        <option value="rejected">{{ __('Rejected') }}</option>
                    </flux:select>
                    @error('newStatus') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    @if ($newStatus === 'rejected')
                        <flux:textarea wire:model="reason" :label="__('Remarks / Rejection Reason (mandatory)')" rows="3" required minlength="3" maxlength="2000" />
                        @error('reason') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    @else
                        <flux:textarea wire:model="notes" :label="__('Remarks / Rejection Reason')" rows="2" maxlength="2000" />
                    @endif

                    <p class="text-[11px] text-slate-400">{{ __('Every status change is logged. Deposit amount and existing loan allocations are never modified by this action.') }}</p>

                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Confirm Update') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
