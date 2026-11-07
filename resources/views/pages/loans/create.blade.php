<?php

use App\Enums\InterestMethod;
use App\Models\Borrower;
use App\Models\Guarantor;
use App\Services\GuarantorService;
use App\Services\LoanService;
use App\Services\Settings\SettingsService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New loan')] class extends Component
{
    public string $borrower_id = '';

    public string $principal_amount = '';

    public string $monthly_interest_rate = '4';

    public string $annual_interest_rate = '48';

    public string $interest_method = 'flat';

    public string $tenure_months = '12';

    public string $first_emi_date = '';

    public string $disbursement_date = '';

    public string $purpose = '';

    public string $monthly_income = '';

    public string $employment_details = '';

    /** @var array<int, int> */
    public array $selectedGuarantorIds = [];

    /** @var array<int|string, array{relationship: string, custom_relationship: string}> */
    public array $selectedGuarantorRelationships = [];

    public string $guarantorSearch = '';

    public bool $showNewGuarantorModal = false;

    /** @var array<string, mixed> */
    public array $newGuarantor = [];

    public string $notes = '';

    /** @var array<int, int> */
    public array $tenureOptions = [];

    public function mount(Request $request): void
    {
        $settings = app(SettingsService::class);

        $this->borrower_id = (string) ($request->query('borrower') ?? '');
        $this->first_emi_date = now()->addMonthNoOverflow()->format('Y-m-d');
        $this->newGuarantor = GuarantorService::blankRow();

        $this->monthly_interest_rate = $settings->getDecimal('loan.default_monthly_rate', '4');
        $this->interest_method = $settings->get('loan.default_interest_method', 'flat');
        $this->tenureOptions = array_map('intval', $settings->getJson('loan.tenure_options', [6, 12, 18, 24, 36]));

        if (! in_array((int) $this->tenure_months, $this->tenureOptions, true) && $this->tenureOptions !== []) {
            $this->tenure_months = (string) $this->tenureOptions[0];
        }

        $allowed = $settings->getJson('loan.allowed_interest_methods', ['flat', 'reducing']);

        if (! in_array($this->interest_method, $allowed, true)) {
            $this->interest_method = $allowed[0] ?? 'flat';
        }

        if (filled($settings->get('loan.default_amount'))) {
            $this->principal_amount = (string) $settings->get('loan.default_amount');
        }

        $this->syncAnnualFromMonthly();
    }

    /**
     * @return array{minAmount: string, maxAmount: string, minRate: string, maxRate: string, minTenure: int, maxTenure: int, methods: array<int, string>}
     */
    #[Computed]
    public function loanLimits(): array
    {
        $settings = app(SettingsService::class);

        return [
            'minAmount' => $settings->getDecimal('loan.min_amount', '1000'),
            'maxAmount' => $settings->getDecimal('loan.max_amount', '100000000'),
            'minRate' => $settings->getDecimal('loan.min_monthly_rate', '0'),
            'maxRate' => $settings->getDecimal('loan.max_monthly_rate', '5'),
            'minTenure' => $settings->getInt('loan.min_tenure_months', 1),
            'maxTenure' => $settings->getInt('loan.max_tenure_months', 360),
            'methods' => $settings->getJson('loan.allowed_interest_methods', ['flat', 'reducing']),
        ];
    }

    public function updatedMonthlyInterestRate(): void
    {
        $this->syncAnnualFromMonthly();
    }

    public function updatedAnnualInterestRate(): void
    {
        if (is_numeric($this->annual_interest_rate)) {
            $this->monthly_interest_rate = rtrim(rtrim(number_format((float) $this->annual_interest_rate / 12, 4), '0'), '.');
        }
    }

    private function syncAnnualFromMonthly(): void
    {
        if ($this->monthly_interest_rate === '' || ! is_numeric($this->monthly_interest_rate)) {
            return;
        }

        $annual = (float) $this->monthly_interest_rate * 12;
        $this->annual_interest_rate = rtrim(rtrim(number_format($annual, 4), '0'), '.');
    }

    /**
     * @return array<int, Borrower>
     */
    #[Computed]
    public function borrowers()
    {
        return Borrower::query()->active()->orderBy('full_name')->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Guarantor>
     */
    #[Computed]
    public function guarantorOptions()
    {
        $query = Guarantor::query()->orderBy('full_name');

        if ($this->guarantorSearch !== '') {
            $query->search($this->guarantorSearch);
        }

        return $query->limit(50)->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Guarantor>
     */
    #[Computed]
    public function selectedGuarantors()
    {
        if ($this->selectedGuarantorIds === []) {
            return collect();
        }

        return Guarantor::query()
            ->whereIn('id', $this->selectedGuarantorIds)
            ->get()
            ->sortBy(fn (Guarantor $guarantor) => array_search($guarantor->id, $this->selectedGuarantorIds, true))
            ->values();
    }

    public function toggleGuarantor(int $guarantorId): void
    {
        if (in_array($guarantorId, $this->selectedGuarantorIds, true)) {
            $this->selectedGuarantorIds = array_values(array_filter(
                $this->selectedGuarantorIds,
                fn (int $id): bool => $id !== $guarantorId,
            ));
            unset($this->selectedGuarantorRelationships[$guarantorId]);
        } else {
            $this->selectedGuarantorIds[] = $guarantorId;
            $this->selectedGuarantorRelationships[$guarantorId] = ['relationship' => '', 'custom_relationship' => ''];
        }

        unset($this->selectedGuarantors);
    }

    public function removeSelectedGuarantor(int $guarantorId): void
    {
        $this->selectedGuarantorIds = array_values(array_filter(
            $this->selectedGuarantorIds,
            fn (int $id): bool => $id !== $guarantorId,
        ));
        unset($this->selectedGuarantorRelationships[$guarantorId]);

        unset($this->selectedGuarantors);
    }

    public function openNewGuarantorModal(): void
    {
        $this->newGuarantor = GuarantorService::blankRow();
        $this->resetValidation(['newGuarantor.*']);
        $this->showNewGuarantorModal = true;
    }

    public function saveNewGuarantor(): void
    {
        $this->newGuarantor = GuarantorService::normalize($this->newGuarantor);

        $validated = $this->validate(GuarantorService::rules('newGuarantor'));
        $data = $validated['newGuarantor'];

        $borrower = filled($this->borrower_id) ? Borrower::query()->find($this->borrower_id) : null;

        if ($borrower !== null && $borrower->mobile === $data['mobile_number']) {
            $this->addError('newGuarantor.mobile_number', __('A guarantor mobile number cannot match the borrower mobile number.'));

            return;
        }

        $pivot = GuarantorService::pivotRelationship($data);
        unset($data['relationship'], $data['custom_relationship']);

        $guarantor = app(GuarantorService::class)->createStandalone($data, auth()->user());

        if (! in_array($guarantor->id, $this->selectedGuarantorIds, true)) {
            $this->selectedGuarantorIds[] = $guarantor->id;
        }
        $this->selectedGuarantorRelationships[$guarantor->id] = [
            'relationship' => (string) ($pivot['relationship'] ?? ''),
            'custom_relationship' => (string) ($pivot['custom_relationship'] ?? ''),
        ];

        $this->showNewGuarantorModal = false;
        $this->newGuarantor = GuarantorService::blankRow();
        $this->guarantorSearch = '';
        unset($this->selectedGuarantors, $this->guarantorOptions);

        Flux::toast(variant: 'success', text: __('Guarantor saved and selected for this loan.'));
    }

    #[Computed]
    public function preview(): ?\App\Support\LoanCalculation
    {
        if (! is_numeric($this->principal_amount) || (float) $this->principal_amount <= 0) {
            return null;
        }

        if (! is_numeric($this->tenure_months) || (int) $this->tenure_months < 1) {
            return null;
        }

        try {
            return app(LoanService::class)->preview(
                $this->principal_amount,
                $this->annual_interest_rate ?: '0',
                (int) $this->tenure_months,
                $this->interest_method,
                $this->first_emi_date ?: now()->addMonthNoOverflow()->format('Y-m-d'),
            );
        } catch (\Throwable $throwable) {
            report($throwable);

            return null;
        }
    }

    public function save(): void
    {
        $limits = $this->loanLimits;

        $rules = [
            'borrower_id' => ['required', Rule::exists('borrowers', 'id')->where('status', 'active')],
            'principal_amount' => ['required', 'numeric', 'min:'.$limits['minAmount'], 'max:'.$limits['maxAmount']],
            'monthly_interest_rate' => ['required', 'numeric', 'min:'.$limits['minRate'], 'max:'.$limits['maxRate']],
            'annual_interest_rate' => ['required', 'numeric', 'min:0'],
            'interest_method' => ['required', Rule::in($limits['methods'])],
            'tenure_months' => ['required', 'integer', 'min:'.$limits['minTenure'], 'max:'.$limits['maxTenure']],
            'first_emi_date' => array_filter([
                'required',
                'date',
                $this->disbursement_date !== '' ? 'after_or_equal:disbursement_date' : null,
            ]),
            'disbursement_date' => ['nullable', 'date'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'monthly_income' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'employment_details' => ['nullable', 'string', 'max:255'],
            'selectedGuarantorIds' => ['required', 'array', 'min:1', 'max:10'],
            'selectedGuarantorIds.*' => ['integer', Rule::exists('guarantors', 'id')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        foreach ($this->selectedGuarantorIds as $id) {
            $rules['selectedGuarantorRelationships.'.$id.'.relationship'] = ['required', Rule::enum(\App\Enums\RelationshipType::class)];
            $rules['selectedGuarantorRelationships.'.$id.'.custom_relationship'] = [
                'nullable',
                'string',
                'max:100',
                'required_if:selectedGuarantorRelationships.'.$id.'.relationship,'.App\Enums\RelationshipType::Other->value,
            ];
        }

        $validated = $this->validate($rules);

        $selectedIds = array_values(array_unique(array_map('intval', $this->selectedGuarantorIds)));
        $selections = [];

        foreach ($selectedIds as $id) {
            $relationship = $this->selectedGuarantorRelationships[$id] ?? [];
            $selections[] = [
                'guarantor_id' => $id,
                'relationship' => $relationship['relationship'] ?? null,
                'custom_relationship' => ($relationship['relationship'] ?? null) === \App\Enums\RelationshipType::Other->value
                    ? ($relationship['custom_relationship'] ?? null)
                    : null,
            ];
        }

        if ($selectedIds !== []) {
            $borrower = Borrower::query()->find($this->borrower_id);
            $conflicting = Guarantor::query()
                ->whereIn('id', $selectedIds)
                ->when($borrower !== null, fn ($query) => $query->where('mobile_number', $borrower->mobile))
                ->exists();

            if ($conflicting) {
                $this->addError('selectedGuarantorIds', __('A selected guarantor mobile number cannot match the borrower mobile number.'));

                return;
            }
        }

        $loan = app(LoanService::class)->create([
            ...$validated,
            'guarantor_selections' => $selections,
            'emi_frequency' => 'monthly',
        ], auth()->user());

        Flux::toast(variant: 'success', text: __('Loan application submitted — pending review.'));

        $this->redirectRoute('loans.show', $loan, navigate: true);
    }
};
?>

<div class="mx-auto max-w-[1400px] space-y-5">
    <div>
        <p class="fc-eyebrow">{{ __('Loan management') }}</p>
        <h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ __('New loan application') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ __('Submits a loan application. The offer is generated on the loan page and funding is released only after 100% investor capital allocation.') }}</p>
    </div>

    <form wire:submit="save" class="grid gap-4 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            <div class="fc-card p-5 sm:p-6">
                <h3 class="text-sm font-semibold">{{ __('Borrower & amount') }}</h3>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <flux:select wire:model="borrower_id" :label="__('Borrower')" required>
                            <option value="">{{ __('Select a borrower') }}</option>
                            @foreach ($this->borrowers as $borrower)
                                <option value="{{ $borrower->id }}">{{ $borrower->full_name }} · {{ $borrower->borrower_code }} · {{ $borrower->mobile }}</option>
                            @endforeach
                        </flux:select>
                    </div>

                    <flux:input wire:model.live.debounce.400ms="principal_amount" :label="__('Principal amount (₹)')" type="number" step="0.01" min="{{ $this->loanLimits['minAmount'] }}" max="{{ $this->loanLimits['maxAmount'] }}" placeholder="100000" required />
                    <flux:input wire:model.live.debounce.400ms="monthly_interest_rate" :label="__('Monthly interest rate (%)')" type="number" step="0.01" min="{{ $this->loanLimits['minRate'] }}" max="{{ $this->loanLimits['maxRate'] }}" placeholder="1" required />
                    @error('monthly_interest_rate') <p class="-mt-2 text-xs text-rose-600 sm:col-span-2">{{ $message }}</p> @enderror
                    <flux:input wire:model.live.debounce.400ms="annual_interest_rate" :label="__('Annual interest rate (%)')" type="number" step="0.01" min="0" placeholder="48" required />
                    @error('annual_interest_rate') <p class="-mt-2 text-xs text-rose-600 sm:col-span-2">{{ $message }}</p> @enderror
                    <p class="-mt-2 text-[11px] text-slate-400 sm:col-span-2">{{ __('Linked to monthly rate (× 12) — edit either field.') }}</p>
                    <div>
                        <flux:input wire:model.live.debounce.400ms="tenure_months" :label="__('Tenure (months)')" type="number" min="{{ $this->loanLimits['minTenure'] }}" max="{{ $this->loanLimits['maxTenure'] }}" required />
                        @if (count($tenureOptions) > 0)
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($tenureOptions as $option)
                                    <button type="button" wire:click="$set('tenure_months', '{{ $option }}')"
                                            class="rounded-lg border px-2.5 py-1 text-[11px] font-semibold transition {{ (int) $tenure_months === $option ? 'border-navy-900 bg-navy-900 text-white' : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300' }}">
                                        {{ $option }}m
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        @error('tenure_months') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <flux:select wire:model.live="interest_method" :label="__('Interest calculation')" required>
                        @foreach (\App\Enums\InterestMethod::options() as $value => $label)
                            @if (in_array($value, $this->loanLimits['methods'], true))
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endif
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <div class="fc-card p-5 sm:p-6">
                <h3 class="text-sm font-semibold">{{ __('Application details') }}</h3>
                <p class="mt-0.5 text-[11px] text-slate-400">{{ __('Recorded as the loan application and used during review.') }}</p>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <flux:input wire:model="purpose" :label="__('Purpose of loan')" maxlength="255" placeholder="Business expansion, education, medical…" />
                        @error('purpose') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <flux:input wire:model="monthly_income" :label="__('Monthly income (₹)')" type="number" step="0.01" min="0" max="100000000" />
                    @error('monthly_income') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

                    <flux:input wire:model="employment_details" :label="__('Employment details')" maxlength="255" placeholder="Salaried at X / business Y" />
                    @error('employment_details') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="fc-card p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold">{{ __('Guarantor details') }}</h3>
                        <p class="mt-0.5 text-[11px] text-slate-400">{{ __('Required. Select at least one guarantor or add a new guarantor for this loan application.') }}</p>
                    </div>

                    <button type="button" wire:click="openNewGuarantorModal" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-[11px] font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900">
                        <flux:icon.plus class="size-3.5" />
                        {{ __('Add New Guarantor') }}
                    </button>
                </div>

                @error('selectedGuarantorIds') <p class="mt-3 text-xs text-rose-600">{{ $message }}</p> @enderror

                @if ($this->selectedGuarantors->isNotEmpty())
                    <div class="mt-4 space-y-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('Selected guarantors') }}</p>
                        @foreach ($this->selectedGuarantors as $selected)
                            @php($rel = $selectedGuarantorRelationships[$selected->id]['relationship'] ?? '')
                            <div wire:key="selected-guarantor-{{ $selected->id }}" class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800">{{ $selected->full_name }}</p>
                                        <p class="text-[11px] text-slate-500">{{ $selected->mobile_number }} · {{ collect([$selected->city, $selected->state])->filter()->implode(', ') ?: __('Location not set') }}</p>
                                    </div>
                                    <button type="button" wire:click="removeSelectedGuarantor({{ $selected->id }})" class="shrink-0 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 hover:border-rose-200 hover:text-rose-600">{{ __('Remove') }}</button>
                                </div>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-600">{{ __('Relationship for this loan') }}</label>
                                        <select wire:model.live="selectedGuarantorRelationships.{{ $selected->id }}.relationship" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                                            <option value="">{{ __('Select relationship') }}</option>
                                            @foreach (\App\Enums\RelationshipType::options() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('selectedGuarantorRelationships.'.$selected->id.'.relationship') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                    @if (($selectedGuarantorRelationships[$selected->id]['relationship'] ?? '') === \App\Enums\RelationshipType::Other->value)
                                        <div>
                                            <label class="block text-[11px] font-semibold text-slate-600">{{ __('Custom relationship') }}</label>
                                            <input type="text" wire:model="selectedGuarantorRelationships.{{ $selected->id }}.custom_relationship" maxlength="100" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                                            @error('selectedGuarantorRelationships.'.$selected->id.'.custom_relationship') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/50 p-4">
                    <p class="text-xs font-semibold text-slate-700">{{ __('Select Guarantor') }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-400">{{ __('Search and select one or more existing guarantors.') }}</p>

                    <div class="relative mt-3">
                        <flux:icon.magnifying-glass class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="guarantorSearch"
                            placeholder="{{ __('Search guarantor name or mobile…') }}"
                            class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pe-3 ps-9 text-sm text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        >
                    </div>

                    <div class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
                        @forelse ($this->guarantorOptions as $option)
                            @php($isSelected = in_array($option->id, $selectedGuarantorIds, true))
                            <label wire:key="guarantor-option-{{ $option->id }}" class="flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-2.5 transition {{ $isSelected ? 'border-navy-900 bg-navy-900/5' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                                <input type="checkbox" wire:click="toggleGuarantor({{ $option->id }})" @checked($isSelected) class="mt-1 rounded border-slate-300 text-navy-900 focus:ring-navy-900/20">
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-slate-800">{{ $option->full_name }}</span>
                                    <span class="block text-[11px] text-slate-500">{{ $option->mobile_number }} · {{ collect([$option->city, $option->state])->filter()->implode(', ') ?: '—' }}</span>
                                </span>
                            </label>
                        @empty
                            <p class="rounded-xl border border-dashed border-slate-200 bg-white px-3 py-6 text-center text-xs text-slate-400">
                                {{ $guarantorSearch !== '' ? __('No guarantors match your search.') : __('No guarantors found. Add a new guarantor for this loan.') }}
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="fc-card p-5 sm:p-6">
                <h3 class="text-sm font-semibold">{{ __('Schedule') }}</h3>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model.live="first_emi_date" :label="__('First EMI date')" type="date" required />
                    <flux:input wire:model="disbursement_date" :label="__('Disbursement date')" type="date" />
                </div>
            </div>

            <div class="fc-card p-5 sm:p-6">
                <flux:textarea wire:model="notes" :label="__('Internal notes')" rows="2" maxlength="2000" />
            </div>
        </div>

        <div class="space-y-4">
            <div class="fc-card p-5 sm:p-6">
                <p class="fc-eyebrow">{{ __('Live calculation') }}</p>

                @if ($this->preview)
                    <p class="mt-3 text-3xl font-semibold tracking-tight tabular-nums">{{ \App\Support\Money::format($this->preview->emiAmount) }}</p>
                    <p class="text-xs text-slate-400">{{ __('Monthly EMI') }}</p>

                    <dl class="mt-5 space-y-2.5 border-t border-slate-100 pt-4 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Principal') }}</dt><dd class="font-medium tabular-nums">{{ \App\Support\Money::format($this->preview->principal) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Total interest') }}</dt><dd class="font-medium tabular-nums">{{ \App\Support\Money::format($this->preview->totalInterest) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Total payable') }}</dt><dd class="font-semibold tabular-nums">{{ \App\Support\Money::format($this->preview->totalPayable) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Method') }}</dt><dd class="font-medium">{{ $this->preview->method->label() }}</dd></div>
                    </dl>

                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ __('First 4 installments') }}</p>
                        <ul class="mt-2 space-y-2">
                            @foreach (array_slice($this->preview->installments, 0, 4) as $installment)
                                <li class="flex items-center justify-between gap-2 text-xs">
                                    <span class="text-slate-500">#{{ $installment->installmentNo }} · {{ $installment->dueDate->format(\App\Support\Regional::dateFormat()) }}</span>
                                    <span class="font-semibold tabular-nums text-slate-700">{{ \App\Support\Money::format($installment->total) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-2 text-[11px] text-slate-400">{{ __(':count installments in total', ['count' => count($this->preview->installments)]) }}</p>
                    </div>
                @else
                    <p class="mt-6 text-sm text-slate-400">{{ __('Enter a principal and tenure to preview the EMI schedule.') }}</p>
                @endif
            </div>

            <div class="fc-card space-y-2 p-4">
                <flux:button type="submit" variant="primary" class="w-full !rounded-xl">{{ __('Create loan') }}</flux:button>
                <p class="pt-1 text-center text-[11px] leading-relaxed text-slate-400">{{ __('The application enters review. Offers, capital verification and disbursement are guided from the loan processing center.') }}</p>
            </div>
        </div>
    </form>

    @if ($showNewGuarantorModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" wire:click="$set('showNewGuarantorModal', false)"></div>

            <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-base font-semibold">{{ __('Add New Guarantor') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-400">{{ __('Save this guarantor and select it for the current loan application.') }}</p>
                    </div>
                    <button type="button" wire:click="$set('showNewGuarantorModal', false)" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                        <flux:icon.x-mark class="size-4" />
                    </button>
                </div>

                <form wire:submit="saveNewGuarantor" class="mt-4">
                    <x-guarantor-fields model="newGuarantor" :row="$newGuarantor" :index="0" />

                    <div class="mt-4 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showNewGuarantorModal', false)" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('Cancel') }}</button>
                        <flux:button type="submit" variant="primary" class="!rounded-xl">{{ __('Save & select guarantor') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
