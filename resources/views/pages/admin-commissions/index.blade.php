<?php

use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use App\Models\AdminCommissionPayout;
use App\Models\AdminCommissionProfile;
use App\Models\User;
use App\Services\AdminCommissionPayoutService;
use App\Services\DistributionReportService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Admin Commissions')] class extends Component
{
    use WithPagination;

    public string $month = '';

    public string $status = '';

    public bool $showProfileModal = false;

    public string $profileUserId = '';

    public string $designation = '';

    public string $commissionRate = '10';

    public string $effectiveFrom = '';

    public bool $profileActive = true;

    public string $profileNotes = '';

    public bool $showProcessModal = false;

    public bool $showRejectModal = false;

    public ?int $payoutId = null;

    public string $payment_reference = '';

    public string $paid_at = '';

    public string $rejection_reason = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->paid_at = now()->format('Y-m-d');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, AdminCommissionProfile>
     */
    #[Computed]
    public function profiles()
    {
        return AdminCommissionProfile::query()
            ->with('user')
            ->orderByDesc('is_active')
            ->orderBy('user_id')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    #[Computed]
    public function admins()
    {
        return User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::SuperAdmin->value])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, AdminCommissionPayout>
     */
    #[Computed]
    public function payouts()
    {
        return AdminCommissionPayout::query()
            ->with(['user', 'approvedBy', 'items.ledger.loan'])
            ->when($this->month !== '', fn ($query) => $query->whereBetween('period_month', [
                CarbonImmutable::parse($this->month.'-01')->startOfMonth()->toDateString(),
                CarbonImmutable::parse($this->month.'-01')->endOfMonth()->toDateString(),
            ]))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->latest('period_month')
            ->latest('id')
            ->paginate(15);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function summary(): array
    {
        return app(DistributionReportService::class)->summary();
    }

    public function openProfileModal(?int $profileId = null): void
    {
        $this->resetValidation();
        $this->profileUserId = '';
        $this->designation = '';
        $this->commissionRate = '10';
        $this->effectiveFrom = now()->format('Y-m-d');
        $this->profileActive = true;
        $this->profileNotes = '';

        if ($profileId !== null) {
            $profile = AdminCommissionProfile::query()->findOrFail($profileId);

            $this->profileUserId = (string) $profile->user_id;
            $this->designation = (string) $profile->designation;
            $this->commissionRate = (string) $profile->commission_rate;
            $this->effectiveFrom = $profile->effective_from->format('Y-m-d');
            $this->profileActive = $profile->is_active;
            $this->profileNotes = (string) $profile->notes;
        }

        $this->showProfileModal = true;
    }

    public function saveProfile(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $validated = $this->validate([
            'profileUserId' => ['required', Rule::exists('users', 'id')],
            'designation' => ['nullable', 'string', 'max:80'],
            'commissionRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'effectiveFrom' => ['required', 'date'],
            'profileActive' => ['boolean'],
            'profileNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        AdminCommissionProfile::query()->updateOrCreate(
            ['user_id' => $validated['profileUserId']],
            [
                'designation' => filled($validated['designation']) ? $validated['designation'] : null,
                'commission_rate' => $validated['commissionRate'],
                'effective_from' => $validated['effectiveFrom'],
                'is_active' => $validated['profileActive'],
                'notes' => filled($validated['profileNotes']) ? $validated['profileNotes'] : null,
                'created_by' => auth()->id(),
            ],
        );

        $this->showProfileModal = false;
        unset($this->profiles);
        Flux::toast(variant: 'success', text: __('Admin commission profile saved.'));
    }

    public function generate(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);

        $this->validate(['month' => ['required', 'date_format:Y-m']]);

        $count = app(AdminCommissionPayoutService::class)->generate(
            CarbonImmutable::parse($this->month.'-01'),
            auth()->user(),
        );

        unset($this->payouts, $this->summary);

        Flux::toast(variant: 'success', text: $count > 0
            ? __('Generated :count admin commission payout(s) from collected interest.', ['count' => $count])
            : __('No unlinked admin commission entitlements found for this period.'));
    }

    public function approve(int $payoutId): void
    {
        $this->runAction($payoutId, 'approve', fn (AdminCommissionPayout $payout) => app(AdminCommissionPayoutService::class)->approve($payout, auth()->user()), __('Commission payout approved.'));
    }

    public function cancel(int $payoutId): void
    {
        $this->runAction($payoutId, 'cancel', fn (AdminCommissionPayout $payout) => app(AdminCommissionPayoutService::class)->cancel($payout, auth()->user()), __('Commission payout cancelled.'));
    }

    public function openProcessModal(int $payoutId): void
    {
        Gate::authorize('process', AdminCommissionPayout::query()->findOrFail($payoutId));

        $this->payoutId = $payoutId;
        $this->payment_reference = '';
        $this->paid_at = now()->format('Y-m-d');
        $this->showProcessModal = true;
    }

    public function process(): void
    {
        $validated = $this->validate([
            'payoutId' => ['required', 'exists:admin_commission_payouts,id'],
            'payment_reference' => ['required', 'string', 'max:255'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $payout = AdminCommissionPayout::query()->findOrFail($validated['payoutId']);
        Gate::authorize('process', $payout);

        try {
            app(AdminCommissionPayoutService::class)->process(
                $payout,
                auth()->user(),
                $validated['payment_reference'],
                $validated['paid_at'],
            );
        } catch (\InvalidArgumentException $exception) {
            $this->addError('payment_reference', $exception->getMessage());

            return;
        }

        $this->showProcessModal = false;
        unset($this->payouts, $this->summary);
        Flux::toast(variant: 'success', text: __('Commission payout processed and posted to the ledger.'));
    }

    public function openRejectModal(int $payoutId): void
    {
        Gate::authorize('reject', AdminCommissionPayout::query()->findOrFail($payoutId));

        $this->payoutId = $payoutId;
        $this->rejection_reason = '';
        $this->showRejectModal = true;
    }

    public function reject(): void
    {
        $validated = $this->validate([
            'payoutId' => ['required', 'exists:admin_commission_payouts,id'],
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $payout = AdminCommissionPayout::query()->findOrFail($validated['payoutId']);
        Gate::authorize('reject', $payout);

        try {
            app(AdminCommissionPayoutService::class)->reject($payout, auth()->user(), $validated['rejection_reason']);
        } catch (\InvalidArgumentException $exception) {
            $this->addError('rejection_reason', $exception->getMessage());

            return;
        }

        $this->showRejectModal = false;
        unset($this->payouts, $this->summary);
        Flux::toast(variant: 'success', text: __('Commission payout rejected. Entitlements are available to regenerate.'));
    }

    private function runAction(int $payoutId, string $ability, callable $action, string $message): void
    {
        $payout = AdminCommissionPayout::query()->findOrFail($payoutId);
        Gate::authorize($ability, $payout);

        try {
            $action($payout);
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->payouts, $this->summary);
        Flux::toast(variant: 'success', text: $message);
    }
};
?>

<div class="mx-auto max-w-[1500px] space-y-5">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="fc-eyebrow">{{ __('Distribution') }}</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('Admin Commissions') }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ __('Commission rates, earned balances and the Super Admin payout workflow for responsible admins.') }}</p>
        </div>

        @if (auth()->user()->isSuperAdmin())
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="openProfileModal" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300">
                    <flux:icon.user-plus class="size-4" />
                    {{ __('Commission profile') }}
                </button>
                <button type="button" wire:click="generate" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                    <flux:icon.arrow-path class="size-4" />
                    {{ __('Generate :month', ['month' => \Carbon\CarbonImmutable::parse($this->month.'-01')->format('M Y')]) }}
                </button>
            </div>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat-card :label="__('Interest collected')" :value="\App\Support\Money::format($this->summary['collected_interest'])" :hint="__('Actual interest only')" icon="receipt-percent" tone="navy" />
        <x-stat-card :label="__('Admin earned')" :value="\App\Support\Money::format($this->summary['admin_earned'])" :hint="__('Lifetime net of reversals')" icon="chart-bar" tone="purple" />
        <x-stat-card :label="__('Pending')" :value="\App\Support\Money::format($this->summary['admin_pending'])" :hint="__('Awaiting Super Admin review')" icon="clock" tone="amber" />
        <x-stat-card :label="__('Approved')" :value="\App\Support\Money::format($this->summary['admin_approved'])" :hint="__('Ready to process')" icon="check-circle" tone="blue" />
        <x-stat-card :label="__('Paid')" :value="\App\Support\Money::format($this->summary['admin_paid'])" :hint="__('Settled to admins')" icon="banknotes" tone="emerald" />
    </div>

    <div class="grid gap-4 xl:grid-cols-[360px_1fr]">
        <div class="fc-card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <p class="fc-eyebrow">{{ __('Configuration') }}</p>
                    <h3 class="mt-1 text-sm font-semibold">{{ __('Commission profiles') }}</h3>
                </div>
                <span class="text-[11px] text-slate-400">{{ $this->profiles->count() }} {{ __('profiles') }}</span>
            </div>

            <ul class="divide-y divide-slate-100">
                @forelse ($this->profiles as $profile)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <flux:avatar :name="$profile->user->name" :initials="str($profile->user->name)->substr(0, 2)" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold text-slate-800">{{ $profile->user->name }}</p>
                            <p class="truncate text-[11px] text-slate-400">{{ $profile->designation ?: $profile->user->role->label() }} · {{ __('effective :date', ['date' => $profile->effective_from->format('d M Y')]) }}</p>
                        </div>
                        <div class="text-end">
                            <p class="text-xs font-semibold tabular-nums text-slate-800">{{ rtrim(rtrim((string) $profile->commission_rate, '0'), '.') }}%</p>
                            <span class="text-[10px] font-semibold {{ $profile->is_active ? 'text-emerald-600' : 'text-slate-400' }}">{{ $profile->is_active ? __('Active') : __('Inactive') }}</span>
                        </div>
                        @if (auth()->user()->isSuperAdmin())
                            <button type="button" wire:click="openProfileModal({{ $profile->id }})" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" title="{{ __('Edit profile') }}">
                                <flux:icon.pencil-square class="size-4" />
                            </button>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-xs text-slate-400">{{ __('No commission profiles. Admins fall back to the global default rate.') }}</li>
                @endforelse
            </ul>
        </div>

        <div class="fc-card overflow-hidden">
            <div class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-[1fr_auto]">
                <input type="month" wire:model.live="month" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none sm:max-w-[180px]">

                <select wire:model.live="status" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-600 focus:border-blue-500 focus:outline-none">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (PayoutStatus::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="fc-table-head">
                        <tr>
                            <th class="px-4 py-3">{{ __('Payout') }}</th>
                            <th class="px-4 py-3">{{ __('Admin') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('Commission') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3">{{ __('Reference') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($this->payouts as $payout)
                            <tr class="transition hover:bg-slate-50/70">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $payout->payout_code }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $payout->period_month->format('M Y') }} · #{{ $payout->batch_no }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-800">{{ $payout->user->name }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $payout->items->pluck('loan.loan_code')->filter()->unique()->join(', ') ?: '—' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-end font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($payout->net_amount) }}</td>
                                <td class="px-4 py-3"><x-status-badge :status="$payout->status" /></td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ $payout->payment_reference ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    @if (auth()->user()->isSuperAdmin())
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            @if ($payout->status === PayoutStatus::Pending)
                                                <button type="button" wire:click="approve({{ $payout->id }})" class="text-[11px] font-semibold text-violet-600 hover:text-violet-700">{{ __('Approve') }}</button>
                                                <button type="button" wire:click="openRejectModal({{ $payout->id }})" class="text-[11px] font-semibold text-rose-600 hover:text-rose-700">{{ __('Reject') }}</button>
                                            @elseif ($payout->status === PayoutStatus::Approved)
                                                <button type="button" wire:click="openProcessModal({{ $payout->id }})" class="text-[11px] font-semibold text-emerald-600 hover:text-emerald-700">{{ __('Process') }}</button>
                                                <button type="button" wire:click="openRejectModal({{ $payout->id }})" class="text-[11px] font-semibold text-rose-600 hover:text-rose-700">{{ __('Reject') }}</button>
                                            @elseif ($payout->status === PayoutStatus::Paid)
                                                <span class="text-[11px] text-slate-400">{{ $payout->paid_at?->format('d M Y') }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-[11px] text-slate-400">{{ __('Super Admin review') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-14 text-center"><p class="text-sm font-medium text-slate-600">{{ __('No commission payouts') }}</p><p class="mt-1 text-xs text-slate-400">{{ __('Generate payouts for a month with collected interest.') }}</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->payouts->hasPages())
                <div class="border-t border-slate-100 px-5 py-3">{{ $this->payouts->links() }}</div>
            @endif
        </div>
    </div>

    @if ($showProfileModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showProfileModal', false)"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <h3 class="text-base font-semibold">{{ __('Admin commission profile') }}</h3>
                <p class="mt-0.5 text-xs text-slate-400">{{ __('Rates are weights used to split the admin pool for each loan.') }}</p>

                <form wire:submit="saveProfile" class="mt-4 space-y-3">
                    <flux:select wire:model="profileUserId" :label="__('Admin')" required>
                        <option value="">{{ __('Select an admin') }}</option>
                        @foreach ($this->admins as $admin)
                            <option value="{{ $admin->id }}">{{ $admin->name }} · {{ $admin->role->label() }}</option>
                        @endforeach
                    </flux:select>
                    @error('profileUserId') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-2 gap-3">
                        <flux:input wire:model="designation" :label="__('Designation')" placeholder="Relationship manager" maxlength="80" />
                        <flux:input wire:model="commissionRate" :label="__('Commission rate (%)')" type="number" step="0.0001" min="0" max="100" required />
                    </div>
                    @error('commissionRate') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <flux:input wire:model="effectiveFrom" :label="__('Effective from')" type="date" required />
                    @error('effectiveFrom') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <input type="checkbox" wire:model="profileActive" class="rounded border-slate-300 text-blue-600">
                        {{ __('Active profile') }}
                    </label>

                    <flux:textarea wire:model="profileNotes" :label="__('Notes')" rows="2" maxlength="2000" />

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showProfileModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Save profile') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showProcessModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showProcessModal', false)"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <h3 class="text-base font-semibold">{{ __('Process commission payout') }}</h3>
                <p class="mt-0.5 text-xs text-slate-400">{{ __('Posts a balanced payable settlement journal entry.') }}</p>
                <form wire:submit="process" class="mt-4 space-y-3">
                    <flux:input wire:model="payment_reference" :label="__('Payment transaction reference')" placeholder="UTR / transaction ID" required maxlength="255" />
                    @error('payment_reference') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    <flux:input wire:model="paid_at" :label="__('Paid on')" type="date" required max="{{ now()->format('Y-m-d') }}" />
                    @error('paid_at') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showProcessModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Process payout') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showRejectModal', false)"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <h3 class="text-base font-semibold">{{ __('Reject commission payout') }}</h3>
                <p class="mt-0.5 text-xs text-slate-400">{{ __('Linked entitlements become available for regeneration.') }}</p>
                <form wire:submit="reject" class="mt-4 space-y-3">
                    <flux:textarea wire:model="rejection_reason" :label="__('Reason')" rows="3" required maxlength="1000" />
                    @error('rejection_reason') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showRejectModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="danger" class="!rounded-xl">{{ __('Reject payout') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
