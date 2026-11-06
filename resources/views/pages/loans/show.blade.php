<?php

use App\Enums\LoanFundingStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Guarantor;
use App\Models\Loan;
use App\Models\LoanOffer;
use App\Services\EmiCollectionService;
use App\Services\GuarantorService;
use App\Services\LoanFundingService;
use App\Services\LoanOfferService;
use App\Services\LoanService;
use App\Support\LoanWorkflow;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Loan details')] class extends Component
{
    public Loan $loan;

    public bool $showCollectModal = false;

    public string $amount = '';

    public string $payment_date = '';

    public string $payment_method = 'cash';

    public string $reference = '';

    public string $notes = '';

    public bool $showGuarantorModal = false;

    public ?int $editingGuarantorId = null;

    /** @var array<string, mixed> */
    public array $guarantorForm = [];

    public function mount(Loan $loan): void
    {
        $this->loan = $loan->refresh()->load(['borrower', 'guarantors', 'allocations.investor']);
        $this->payment_date = now()->format('Y-m-d');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function workflow(): array
    {
        return LoanWorkflow::steps($this->loan);
    }

    #[Computed]
    public function workflowSummary(): array
    {
        return LoanWorkflow::summary($this->workflow);
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    #[Computed]
    public function auditTrail(): \Illuminate\Support\Collection
    {
        return DB::table('audit_logs as al')
            ->leftJoin('users as u', 'u.id', '=', 'al.user_id')
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('al.auditable_type', (new Loan)->getMorphClass())
                        ->where('al.auditable_id', $this->loan->getKey());
                })->orWhere(function ($query): void {
                    $query->where('al.auditable_type', (new LoanOffer)->getMorphClass())
                        ->whereIn('al.auditable_id', $this->loan->offers()->pluck('id'));
                })->orWhere(function ($query): void {
                    $query->where('al.auditable_type', (new Guarantor)->getMorphClass())
                        ->whereIn('al.auditable_id', $this->loan->guarantors()->withTrashed()->pluck('guarantors.id'));
                });
            })
            ->orderByDesc('al.created_at')
            ->limit(16)
            ->get(['al.id', 'al.action', 'al.new_values', 'al.created_at', 'u.name as user_name']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\EmiSchedule>
     */
    #[Computed]
    public function schedules()
    {
        return $this->loan->schedules()->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\EmiPayment>
     */
    #[Computed]
    public function payments()
    {
        return $this->loan->payments()->latest('payment_date')->get();
    }

    #[Computed]
    public function progress(): float
    {
        return $this->loan->completionPercentage();
    }

    #[Computed]
    public function fundingProgress(): array
    {
        return app(LoanFundingService::class)->progress($this->loan);
    }

    #[Computed]
    public function latestOffer(): ?LoanOffer
    {
        return $this->loan->offers()->first();
    }

    public function reviewApplication(): void
    {
        try {
            app(LoanOfferService::class)->markUnderReview($this->loan, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Application marked under review. Generate the offer next.'));
        $this->redirectRoute('loans.calculator', $this->loan, navigate: true);
    }

    public function openCollectModal(): void
    {
        $next = $this->loan->schedules()->unsettled()->orderBy('installment_no')->first();

        $this->amount = $next ? Money::sub($next->total_amount, $next->paid_amount) : '0.00';
        $this->payment_date = now()->format('Y-m-d');
        $this->payment_method = 'cash';
        $this->reference = '';
        $this->notes = '';
        $this->showCollectModal = true;
    }

    public function collect(): void
    {
        $validated = $this->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:100000000'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => [Rule::requiredIf($this->payment_method !== 'cash'), 'nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(EmiCollectionService::class)->collect($this->loan, $validated, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            $this->addError('amount', $exception->getMessage());

            return;
        }

        $this->showCollectModal = false;
        Flux::toast(variant: 'success', text: __('EMI payment recorded.'));

        $this->redirectRoute('loans.show', $this->loan, navigate: true);
    }

    public function addGuarantor(): void
    {
        $this->authorizeGuarantorManagement();

        $this->editingGuarantorId = null;
        $this->guarantorForm = GuarantorService::blankRow();
        $this->showGuarantorModal = true;
    }

    public function editGuarantor(int $guarantorId): void
    {
        $this->authorizeGuarantorManagement();

        $guarantor = $this->loan->guarantors()->findOrFail($guarantorId);

        $this->editingGuarantorId = $guarantor->getKey();
        $this->guarantorForm = [
            'id' => $guarantor->getKey(),
            'full_name' => $guarantor->full_name,
            'mobile_number' => $guarantor->mobile_number,
            'alternate_mobile' => (string) $guarantor->alternate_mobile,
            'email' => (string) $guarantor->email,
            'address' => $guarantor->address,
            'city' => (string) $guarantor->city,
            'state' => (string) $guarantor->state,
            'pincode' => (string) $guarantor->pincode,
            'relationship' => $guarantor->relationship?->value ?? '',
            'custom_relationship' => (string) $guarantor->custom_relationship,
            'occupation' => (string) $guarantor->occupation,
            'identity_document_type' => $guarantor->identity_document_type?->value ?? '',
            'identity_document_number' => (string) $guarantor->identity_document_number,
        ];
        $this->showGuarantorModal = true;
    }

    public function saveGuarantor(): void
    {
        $this->authorizeGuarantorManagement();

        $this->guarantorForm = GuarantorService::normalize($this->guarantorForm);

        $validated = $this->validate(GuarantorService::rules('guarantorForm'));
        $data = $validated['guarantorForm'];

        if ($data['mobile_number'] === $this->loan->borrower->mobile) {
            $this->addError('guarantorForm.mobile_number', __('A guarantor mobile number cannot match the borrower mobile number.'));

            return;
        }

        $duplicate = $this->loan->guarantors()
            ->where('mobile_number', $data['mobile_number'])
            ->when($this->editingGuarantorId !== null, fn ($query) => $query->where('guarantors.id', '!=', $this->editingGuarantorId))
            ->exists();

        if ($duplicate) {
            $this->addError('guarantorForm.mobile_number', __('This mobile number is already used by another guarantor on this loan.'));

            return;
        }

        $service = app(GuarantorService::class);

        if ($this->editingGuarantorId !== null) {
            $guarantor = $this->loan->guarantors()->findOrFail($this->editingGuarantorId);
            $service->update($guarantor, $data, auth()->user());
            $message = __('Guarantor details updated.');
        } else {
            $service->createForLoan($this->loan, $data, auth()->user());
            $message = __('Guarantor added.');
        }

        $this->showGuarantorModal = false;
        $this->editingGuarantorId = null;
        $this->guarantorForm = [];
        $this->refreshLoan();

        Flux::toast(variant: 'success', text: $message);
    }

    public function removeGuarantor(int $guarantorId): void
    {
        $this->authorizeGuarantorManagement();

        $guarantor = $this->loan->guarantors()->findOrFail($guarantorId);
        app(GuarantorService::class)->removeFromLoan($this->loan, $guarantor);

        $this->refreshLoan();

        Flux::toast(variant: 'success', text: __('Guarantor removed.'));
    }

    private function authorizeGuarantorManagement(): void
    {
        Gate::authorize('manageGuarantors', $this->loan);

        abort_unless($this->loan->guarantorsEditable(), 403);
    }

    private function refreshLoan(): void
    {
        $this->loan = $this->loan->refresh()->load(['borrower', 'guarantors', 'allocations.investor']);
    }

    public function disburse(): void
    {
        try {
            app(LoanService::class)->disburse($this->loan, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Loan disbursed and activated.'));
        $this->redirectRoute('loans.show', $this->loan, navigate: true);
    }

    public function close(): void
    {
        try {
            app(LoanService::class)->close($this->loan, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Loan closed.'));
        $this->redirectRoute('loans.show', $this->loan, navigate: true);
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ route('loans.index') }}" class="hover:text-blue-600">{{ __('Loans') }}</a>
        <flux:icon.chevron-right class="size-3" />
        <span class="text-slate-600">{{ $loan->loan_code }}</span>
    </div>

    <div class="fc-card p-5 sm:p-6">
        <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-semibold tracking-tight">{{ $loan->loan_code }}</h2>
                    <x-status-badge :status="$loan->status" />
                    <x-status-badge :status="$loan->application_status" />
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    <a href="{{ route('borrowers.show', $loan->borrower) }}" class="font-medium text-blue-600 hover:text-blue-700">{{ $loan->borrower->full_name }}</a>
                    · {{ $loan->borrower->mobile }} · {{ $loan->interest_method->label() }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('loans.distribution', $loan) }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900">
                    <flux:icon.chart-pie class="size-4" />
                    {{ __('Distribution') }}
                </a>

                @if ($loan->status === \App\Enums\LoanStatus::Pending)
                    @if ($loan->final_approved_at !== null && $loan->funding_status === \App\Enums\LoanFundingStatus::FullyFunded)
                        <a href="{{ route('loans.disbursement', $loan) }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                            <flux:icon.banknotes class="size-4" />
                            {{ __('Record disbursement') }}
                        </a>
                    @elseif ($loan->funding_status === \App\Enums\LoanFundingStatus::FullyFunded)
                        <a href="{{ route('loans.disbursement', $loan) }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                            <flux:icon.shield-check class="size-4" />
                            {{ __('Approve & disburse') }}
                        </a>
                    @else
                        <a href="{{ route('loans.capital-verification', $loan) }}" wire:navigate class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900" title="{{ __('Investor capital must cover the full approved amount before disbursement unlocks.') }}">
                            <flux:icon.lock-closed class="size-4" />
                            {{ __('Disbursement locked · :percent% funded', ['percent' => rtrim(rtrim(number_format($this->fundingProgress['percent'], 1), '0'), '.')]) }}
                        </a>
                    @endif
                @endif

                @if ($loan->status === \App\Enums\LoanStatus::Active)
                    <button type="button" wire:click="openCollectModal" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white shadow-lg shadow-navy-900/20 transition hover:bg-navy-800">
                        <flux:icon.receipt-percent class="size-4" />
                        {{ __('Record EMI') }}
                    </button>
                    <button type="button" wire:click="close" wire:confirm="{{ __('Close this loan? Outstanding principal must be zero.') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-600 shadow-sm transition hover:border-slate-300 hover:text-slate-900">
                        <flux:icon.lock-closed class="size-4" />
                        {{ __('Close loan') }}
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div class="fc-card p-5 sm:p-6" wire:key="workflow-tracker">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="fc-eyebrow">{{ __('Loan processing center') }}</p>
                <h3 class="mt-1 text-sm font-semibold">{{ __('Processing tracker') }}</h3>
            </div>
            <span class="text-xs text-slate-400">{{ __(':done of :total steps complete', ['done' => $this->workflowSummary['done'], 'total' => $this->workflowSummary['total']]) }}</span>
        </div>

        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-navy-900 transition-all duration-500" style="width: {{ $this->workflowSummary['percent'] }}%"></div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($this->workflow as $workflowStep)
                @php
                    $stepClass = match ($workflowStep['state']) {
                        \App\Support\LoanWorkflow::STATE_DONE => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                        \App\Support\LoanWorkflow::STATE_CURRENT => 'border-navy-900 bg-navy-900 text-white',
                        \App\Support\LoanWorkflow::STATE_FAILED => 'border-rose-200 bg-rose-50 text-rose-700',
                        default => 'border-slate-200 bg-white text-slate-400',
                    };
                    $dotClass = match ($workflowStep['state']) {
                        \App\Support\LoanWorkflow::STATE_DONE => 'bg-emerald-500 text-white',
                        \App\Support\LoanWorkflow::STATE_CURRENT => 'bg-white text-navy-900',
                        \App\Support\LoanWorkflow::STATE_FAILED => 'bg-rose-500 text-white',
                        default => 'bg-slate-200 text-slate-500',
                    };
                @endphp
                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1.5 text-[11px] font-semibold {{ $stepClass }}" title="{{ $workflowStep['title'] }}">
                    <span class="flex size-4 items-center justify-center rounded-full text-[9px] font-bold {{ $dotClass }}">
                        @if ($workflowStep['state'] === \App\Support\LoanWorkflow::STATE_DONE)
                            <flux:icon.check class="size-2.5" />
                        @elseif ($workflowStep['state'] === \App\Support\LoanWorkflow::STATE_FAILED)
                            <flux:icon.x-mark class="size-2.5" />
                        @else
                            {{ $loop->iteration }}
                        @endif
                    </span>
                    {{ $workflowStep['title'] }}
                </span>
            @endforeach
        </div>

        @php $currentStep = \App\Support\LoanWorkflow::current($this->workflow); @endphp

        @if ($currentStep)
            <div class="mt-5 grid gap-4 rounded-2xl border border-slate-200 bg-slate-50/60 p-4 lg:grid-cols-3">
                <div class="min-w-0 lg:col-span-2">
                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-navy-900">Step {{ collect($this->workflow)->search(fn ($step) => $step['key'] === $currentStep['key']) + 1 }} · {{ __('Current step') }}</p>
                    <h4 class="mt-1 flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-900">
                        {{ $currentStep['title'] }}
                        @if ($currentStep['state'] === \App\Support\LoanWorkflow::STATE_FAILED)
                            <x-status-badge :status="\App\Enums\ApplicationStatus::Rejected" class="!text-[9px]" />
                        @endif
                    </h4>
                    <dl class="mt-2.5 space-y-1.5 text-xs leading-relaxed text-slate-600">
                        <div><dt class="inline font-semibold text-slate-800">{{ __('What: ') }}</dt><dd class="inline">{{ $currentStep['meaning'] }}</dd></div>
                        <div><dt class="inline font-semibold text-slate-800">{{ __('Why: ') }}</dt><dd class="inline">{{ $currentStep['why'] }}</dd></div>
                        <div><dt class="inline font-semibold text-slate-800">{{ __('Do now: ') }}</dt><dd class="inline">{{ $currentStep['todo'] }}</dd></div>
                        <div><dt class="inline font-semibold text-slate-800">{{ __('After: ') }}</dt><dd class="inline">{{ $currentStep['after'] }}</dd></div>
                    </dl>

                    @if ($currentStep['action'])
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            @if ($currentStep['action']['type'] === 'route')
                                <a href="{{ route($currentStep['action']['route'], $loan) }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white transition hover:bg-navy-800">
                                    <flux:icon.arrow-right class="size-4" />
                                    {{ $currentStep['action']['label'] }}
                                </a>
                            @elseif ($currentStep['action']['type'] === 'livewire')
                                <button type="button" wire:click="{{ $currentStep['action']['target'] }}" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white transition hover:bg-navy-800">
                                    <flux:icon.arrow-right class="size-4" />
                                    {{ $currentStep['action']['label'] }}
                                </button>
                            @elseif ($currentStep['action']['type'] === 'collect')
                                <button type="button" wire:click="openCollectModal" class="inline-flex items-center gap-2 rounded-xl bg-navy-900 px-3.5 py-2.5 text-xs font-semibold text-white transition hover:bg-navy-800">
                                    <flux:icon.receipt-percent class="size-4" />
                                    {{ $currentStep['action']['label'] }}
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="min-w-0 space-y-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">{{ __('Validation checklist') }}</p>
                        <ul class="mt-1.5 space-y-1.5 text-xs">
                            @foreach ($currentStep['checklist'] as $item)
                                <li class="flex items-start gap-2">
                                    <span class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full {{ $item['passed'] ? 'bg-emerald-500 text-white' : 'border border-slate-300 bg-white' }}">
                                        @if ($item['passed'])<flux:icon.check class="size-2.5" />@endif
                                    </span>
                                    <span class="{{ $item['passed'] ? 'text-slate-700' : 'text-slate-500' }}">{{ $item['label'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">{{ __('Required documents / information') }}</p>
                        <ul class="mt-1.5 space-y-1 text-xs text-slate-600">
                            @foreach ($currentStep['documents'] as $document)
                                <li class="flex items-center gap-1.5"><flux:icon.document-text class="size-3.5 shrink-0 text-slate-300" />{{ $document }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card :label="__('Principal')" :value="\App\Support\Money::format($loan->principal_amount)" :hint="__('Disbursed :date', ['date' => $loan->disbursement_date?->format(\App\Support\Regional::dateFormat()) ?? __('pending')])" icon="banknotes" tone="navy" />
        <x-stat-card :label="__('Monthly EMI')" :value="\App\Support\Money::format($loan->emi_amount)" :hint="__(':count installments', ['count' => $this->schedules->count()])" icon="calendar-days" tone="blue" />
        <x-stat-card :label="__('Outstanding principal')" :value="\App\Support\Money::format($loan->outstanding_principal)" :hint="__('of :amount', ['amount' => \App\Support\Money::format($loan->principal_amount)])" icon="scale" tone="amber" />
        <x-stat-card :label="__('Collected')" :value="\App\Support\Money::format($loan->paidAmount())" :hint="__(':percent% of schedule settled', ['percent' => $this->progress])" icon="arrow-down-tray" tone="emerald" />
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            <div class="fc-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <p class="fc-eyebrow">{{ __('Amortization') }}</p>
                        <h3 class="mt-1 text-sm font-semibold">{{ __('EMI schedule') }}</h3>
                    </div>
                    <span class="text-xs text-slate-400">{{ $this->schedules->filter(fn ($schedule) => $schedule->status === \App\Enums\EmiStatus::Paid)->count() }} / {{ $this->schedules->count() }} {{ __('paid') }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="fc-table-head">
                            <tr>
                                <th class="px-5 py-3">#</th>
                                <th class="px-5 py-3">{{ __('Due date') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('Principal') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('Interest') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('Total') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('Paid') }}</th>
                                <th class="px-5 py-3">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($this->schedules as $schedule)
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="px-5 py-2.5 text-xs text-slate-400">{{ $schedule->installment_no }}</td>
                                    <td class="whitespace-nowrap px-5 py-2.5 text-slate-600">{{ $schedule->due_date->format(\App\Support\Regional::dateFormat()) }}</td>
                                    <td class="whitespace-nowrap px-5 py-2.5 text-end tabular-nums text-slate-600">{{ \App\Support\Money::format($schedule->principal_amount) }}</td>
                                    <td class="whitespace-nowrap px-5 py-2.5 text-end tabular-nums text-slate-600">{{ \App\Support\Money::format($schedule->interest_amount) }}</td>
                                    <td class="whitespace-nowrap px-5 py-2.5 text-end font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($schedule->total_amount) }}</td>
                                    <td class="whitespace-nowrap px-5 py-2.5 text-end tabular-nums text-emerald-600">{{ \App\Support\Money::format($schedule->paid_amount) }}</td>
                                    <td class="px-5 py-2.5">
                                        <x-status-badge :status="$schedule->isOverdue() ? \App\Enums\EmiStatus::Overdue : $schedule->status" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="fc-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold">{{ __('Payment history') }}</h3>
                    <span class="text-xs text-slate-400">{{ __(':count receipts', ['count' => $this->payments->count()]) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="fc-table-head">
                            <tr>
                                <th class="px-5 py-3">{{ __('Receipt') }}</th>
                                <th class="px-5 py-3">{{ __('Date') }}</th>
                                <th class="px-5 py-3">{{ __('Mode') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('Amount') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('Principal') }}</th>
                                <th class="px-5 py-3 text-end">{{ __('Interest') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($this->payments as $payment)
                                <tr>
                                    <td class="px-5 py-2.5 font-medium text-slate-800">{{ $payment->receipt_no }}</td>
                                    <td class="whitespace-nowrap px-5 py-2.5 text-slate-600">{{ $payment->payment_date->format(\App\Support\Regional::dateFormat()) }}</td>
                                    <td class="px-5 py-2.5 text-slate-600">{{ $payment->payment_method->label() }}</td>
                                    <td class="whitespace-nowrap px-5 py-2.5 text-end font-semibold tabular-nums text-slate-800">{{ \App\Support\Money::format($payment->amount) }}</td>
                                    <td class="whitespace-nowrap px-5 py-2.5 text-end tabular-nums text-slate-600">{{ \App\Support\Money::format($payment->principal_component) }}</td>
                                    <td class="whitespace-nowrap px-5 py-2.5 text-end tabular-nums text-slate-600">{{ \App\Support\Money::format($payment->interest_component) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-10 text-center text-xs text-slate-400">{{ __('No payments recorded for this loan.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="fc-card p-5">
                <h3 class="text-sm font-semibold">{{ __('Loan terms') }}</h3>
                <dl class="mt-4 space-y-2.5 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-400">{{ __('Method') }}</dt><dd class="font-medium">{{ $loan->interest_method->label() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">{{ __('Monthly rate') }}</dt><dd class="font-medium">{{ rtrim(rtrim(number_format((float) $loan->annual_interest_rate / 12, 4), '0'), '.') }}%</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">{{ __('Annual rate') }}</dt><dd class="font-medium">{{ rtrim(rtrim($loan->annual_interest_rate, '0'), '.') }}%</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">{{ __('Tenure') }}</dt><dd class="font-medium">{{ $loan->tenure_months }} {{ __('months') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">{{ __('First EMI') }}</dt><dd class="font-medium">{{ $loan->first_emi_date->format(\App\Support\Regional::dateFormat()) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">{{ __('Total interest') }}</dt><dd class="font-medium tabular-nums">{{ \App\Support\Money::format($loan->total_interest) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">{{ __('Total payable') }}</dt><dd class="font-semibold tabular-nums">{{ \App\Support\Money::format($loan->total_payable) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">{{ __('Foreclosure charge') }}</dt><dd class="font-medium tabular-nums">{{ \App\Support\Money::format($loan->foreclosure_charge) }}</dd></div>
                </dl>

                <div class="mt-4 border-t border-slate-100 pt-4">
                    <div class="flex items-center justify-between text-[11px] text-slate-400">
                        <span>{{ __('Repayment progress') }}</span>
                        <span>{{ $this->progress }}%</span>
                    </div>
                    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-emerald-500" style="width: {{ $this->progress }}%"></div>
                    </div>
                </div>

                @if ($loan->notes)
                    <div class="mt-4 rounded-xl bg-slate-50 p-3 text-xs leading-relaxed text-slate-600">{{ $loan->notes }}</div>
                @endif
            </div>

            <div class="fc-card p-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold">{{ __('Application & offer') }}</h3>
                    <a href="{{ route('loans.calculator', $loan) }}" wire:navigate class="text-[11px] font-semibold text-navy-900 hover:underline">{{ __('Calculator') }}</a>
                </div>
                <dl class="mt-4 space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Purpose') }}</dt><dd class="truncate font-medium">{{ $loan->purpose ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Monthly income') }}</dt><dd class="font-medium tabular-nums">{{ $loan->monthly_income !== null ? \App\Support\Money::format($loan->monthly_income) : '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Employment') }}</dt><dd class="truncate font-medium">{{ $loan->employment_details ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Processing fee') }}</dt><dd class="font-medium tabular-nums">{{ \App\Support\Money::format($loan->processing_fee) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Other charges') }}</dt><dd class="font-medium tabular-nums">{{ \App\Support\Money::format($loan->other_charges) }}</dd></div>
                </dl>

                @if ($this->latestOffer)
                    @if ($this->latestOffer->accepted_at !== null)
                        <div class="mt-4 rounded-xl border border-emerald-100 bg-emerald-50/60 p-3 text-xs leading-relaxed text-emerald-900">
                            <p class="flex items-center gap-1.5 font-semibold"><flux:icon.lock-closed class="size-3.5 shrink-0" />{{ __('Terms snapshot locked') }}</p>
                            <p class="mt-1">{{ __('Accepted on :date · :amount, :rate%, :method, :months months', ['date' => $this->latestOffer->accepted_at?->format(\App\Support\Regional::dateFormat().', '.\App\Support\Regional::timeFormat()), 'amount' => \App\Support\Money::format($this->latestOffer->principal_amount), 'rate' => rtrim(rtrim((string) $this->latestOffer->annual_interest_rate, '0'), '.'), 'method' => $this->latestOffer->interest_method->label(), 'months' => $this->latestOffer->tenure_months]) }}</p>
                        </div>
                    @else
                        <p class="mt-4 text-[11px] text-slate-400">{{ __('Latest offer issued :date — awaiting borrower acceptance.', ['date' => $this->latestOffer->offered_at->format(\App\Support\Regional::dateFormat().', '.\App\Support\Regional::timeFormat())]) }}</p>
                    @endif
                @endif
            </div>

            <div class="fc-card p-5">
                <h3 class="text-sm font-semibold">{{ __('Audit trail') }}</h3>
                <div class="mt-3 max-h-72 overflow-y-auto pr-1 overscroll-contain">
                <ul class="space-y-2">
                    @forelse ($this->auditTrail as $entry)
                        <li class="rounded-xl border border-slate-200 p-2.5">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold text-slate-800">{{ str($entry->action)->replace('.', ' ')->title() }}</p>
                                <span class="text-[10px] text-slate-400">{{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('d M, H:i') }}</span>
                            </div>
                            @php $entryValues = (array) json_decode($entry->new_values ?? '', true); @endphp
                            <p class="mt-0.5 truncate text-[10px] text-slate-400">{{ $entry->user_name ?: 'System' }}@if (filled($entryValues) && isset($entryValues['amount'])) · ₹{{ $entryValues['amount'] }}@endif</p>
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-slate-200 px-3 py-5 text-center text-xs text-slate-400">{{ __('No audit entries recorded yet.') }}</li>
                    @endforelse
                </ul>
                </div>
            </div>

            <div class="fc-card p-5">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold">{{ __('Guarantor Information') }}</h3>
                    @if (auth()->user()?->can('manageGuarantors', $loan) && $loan->guarantorsEditable())
                        <button type="button" wire:click="addGuarantor" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-[11px] font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900">
                            <flux:icon.plus class="size-3.5" />
                            {{ __('Add') }}
                        </button>
                    @endif
                </div>
                <div class="mt-3 space-y-3">
                    @forelse ($loan->guarantors as $guarantor)
                        <article class="rounded-2xl border border-slate-200 p-3.5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <flux:avatar :name="$guarantor->full_name" :initials="str($guarantor->full_name)->substr(0, 2)" size="sm" />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $guarantor->full_name }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $guarantor->relationshipLabel() }} · {{ $guarantor->mobile_number }}</p>
                                    </div>
                                </div>
                                @if (auth()->user()?->can('manageGuarantors', $loan) && $loan->guarantorsEditable())
                                    <button type="button" wire:click="editGuarantor({{ $guarantor->id }})" class="shrink-0 rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:text-slate-900">{{ __('Edit') }}</button>
                                @endif
                            </div>
                            <dl class="mt-3 space-y-1.5 text-xs">
                                <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Mobile') }}</dt><dd class="font-medium text-slate-700">{{ $guarantor->mobile_number }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Alternate') }}</dt><dd class="font-medium text-slate-700">{{ $guarantor->alternate_mobile ?: '—' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Email') }}</dt><dd class="truncate font-medium text-slate-700">{{ $guarantor->email ?: '—' }}</dd></div>
                                <div><dt class="text-slate-400">{{ __('Address') }}</dt><dd class="mt-0.5 leading-relaxed text-slate-700">{{ $guarantor->address }}, {{ $guarantor->city }}, {{ $guarantor->state }} - {{ $guarantor->pincode }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Occupation') }}</dt><dd class="font-medium text-slate-700">{{ $guarantor->occupation ?: '—' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Identity') }}</dt><dd class="font-medium text-slate-700">{{ $guarantor->identity_document_type?->label() ?? '—' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-slate-400">{{ __('Document number') }}</dt><dd class="break-all font-medium text-slate-700">{{ $guarantor->identity_document_number ?: '—' }}</dd></div>
                            </dl>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <a href="tel:{{ $guarantor->mobile_number }}" class="inline-flex items-center gap-1.5 rounded-xl bg-navy-900 px-3 py-2 text-[11px] font-semibold text-white hover:bg-navy-800"><flux:icon.phone class="size-3.5" />{{ __('Call') }}</a>
                                <button type="button" x-data="{ copied: false }" x-on:click="navigator.clipboard.writeText('{{ $guarantor->mobile_number }}'); copied = true; setTimeout(() => copied = false, 1500)" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-[11px] font-semibold text-slate-600 hover:text-slate-900"><flux:icon.clipboard-document class="size-3.5" /><span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy Mobile') }}'"></span></button>
                            </div>
                        </article>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">{{ __('No guarantor details available.') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="fc-card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold">{{ __('Investor funding') }}</h3>
                        <p class="mt-0.5 text-[11px] text-slate-400">{{ __('Approved amount · :amount', ['amount' => \App\Support\Money::format($this->fundingProgress['required'])]) }}</p>
                    </div>
                    <x-status-badge :status="$this->loan->funding_status" />
                </div>

                <div class="mt-4">
                    <div class="flex items-center justify-between text-[11px] text-slate-400">
                        <span>{{ __(':committed of :required committed', ['committed' => \App\Support\Money::format($this->fundingProgress['committed']), 'required' => \App\Support\Money::format($this->fundingProgress['required'])]) }}</span>
                        <span>{{ rtrim(rtrim(number_format($this->fundingProgress['percent'], 1), '0'), '.') }}%</span>
                    </div>
                    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full transition-all duration-500 {{ ($this->fundingProgress['status'] ?? null) === \App\Enums\LoanFundingStatus::FullyFunded ? 'bg-emerald-500' : 'bg-blue-500' }}" style="width: {{ $this->fundingProgress['percent'] }}%"></div>
                    </div>
                    <p class="mt-1.5 text-[11px] text-slate-400">{{ __(':remaining remaining · :count investor(s)', ['remaining' => \App\Support\Money::format($this->fundingProgress['remaining']), 'count' => $this->fundingProgress['investors']]) }}</p>
                </div>

                @if ($loan->status === \App\Enums\LoanStatus::Pending && $loan->funding_locked_at === null)
                    @if (! in_array($loan->application_status, [\App\Enums\ApplicationStatus::Accepted, \App\Enums\ApplicationStatus::Rejected, \App\Enums\ApplicationStatus::Cancelled], true))
                        <p class="mt-4 flex items-center gap-2 rounded-xl border border-amber-100 bg-amber-50 px-3 py-2.5 text-[11px] font-medium leading-relaxed text-amber-800">
                            <flux:icon.clock class="size-4 shrink-0" />
                            {{ __('Funding opens after the borrower accepts the loan offer.') }}
                        </p>
                    @elseif ($loan->capital_verified_at === null)
                        <a href="{{ route('loans.capital-verification', $loan) }}" wire:navigate class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-navy-900 bg-navy-900/5 px-3.5 py-2.5 text-xs font-semibold text-navy-900 transition hover:bg-navy-900/10">
                            <flux:icon.magnifying-glass class="size-4" />
                            {{ __('Verify investor capital') }}
                        </a>
                    @else
                        <a href="{{ route('loans.capital-allocation', $loan) }}" wire:navigate class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-navy-900 bg-navy-900/5 px-3.5 py-2.5 text-xs font-semibold text-navy-900 transition hover:bg-navy-900/10">
                            <flux:icon.puzzle-piece class="size-4" />
                            {{ __('Allocate capital') }}
                        </a>
                    @endif
                @elseif ($loan->funding_locked_at)
                    <p class="mt-4 flex items-center gap-2 rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2.5 text-[11px] font-medium leading-relaxed text-emerald-800">
                        <flux:icon.lock-closed class="size-4 shrink-0" />
                        {{ __('Fully funded. Capital reserved &amp; locked · :date.', ['date' => $this->loan->funding_locked_at->format(\App\Support\Regional::dateFormat().', '.\App\Support\Regional::timeFormat())]) }}
                    </p>
                @endif

                @if ($loan->allocations->isNotEmpty())
                    <p class="mt-4 border-t border-slate-100 pt-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">{{ __('Allocations') }}</p>
                    <ul class="mt-2 space-y-2">
                        @foreach ($loan->allocations as $allocation)
                            <li class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-2.5">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-slate-800">{{ $allocation->investor->name }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $allocation->investor->investor_code }} · {{ $allocation->allocation_date?->format(\App\Support\Regional::dateFormat()) }}</p>
                                </div>
                                <span class="text-xs font-semibold tabular-nums text-slate-700">{{ \App\Support\Money::format($allocation->principal_amount) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    @if ($showGuarantorModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showGuarantorModal', false)"></div>
            <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between"><div><h3 class="text-base font-semibold">{{ $editingGuarantorId ? __('Edit Guarantor') : __('Add Guarantor') }}</h3><p class="mt-0.5 text-xs text-slate-400">{{ $loan->loan_code }} · {{ $loan->borrower->full_name }}</p></div><button type="button" wire:click="$set('showGuarantorModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><flux:icon.x-mark class="size-4" /></button></div>
                <form wire:submit="saveGuarantor" class="mt-4"><x-guarantor-fields model="guarantorForm" :row="$guarantorForm" :index="0" /><div class="mt-4 flex items-center justify-end gap-2"><button type="button" wire:click="$set('showGuarantorModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button><flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Save Guarantor') }}</flux:button></div></form>
            </div>
        </div>
    @endif

    @if ($showCollectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showCollectModal', false)"></div>

            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold">{{ __('Record EMI collection') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">{{ $loan->loan_code }} · {{ $loan->borrower->full_name }}</p>
                    </div>
                    <button type="button" wire:click="$set('showCollectModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                        <flux:icon.x-mark class="size-4" />
                    </button>
                </div>

                <form wire:submit="collect" class="mt-4 space-y-3">
                    <flux:input wire:model="amount" :label="__('Amount received (₹)')" type="number" step="0.01" min="1" max="100000000" required />
                    @error('amount') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-2 gap-3">
                        <flux:input wire:model="payment_date" :label="__('Payment date')" type="date" required max="{{ now()->format('Y-m-d') }}" />
                        <flux:select wire:model="payment_method" :label="__('Mode')" required>
                            @foreach (\App\Enums\PaymentMethod::options() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                    @error('payment_date') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    @error('payment_method') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <flux:input wire:model="reference" :label="__('Reference')" placeholder="Required for non-cash payments" maxlength="255" />
                    @error('reference') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                    <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" maxlength="1000" />

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" wire:click="$set('showCollectModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Post payment') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
