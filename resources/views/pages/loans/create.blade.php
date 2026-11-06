<?php

use App\Enums\InterestMethod;
use App\Models\Borrower;
use App\Services\GuarantorService;
use App\Services\LoanService;
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

    /** @var array<int, array<string, mixed>> */
    public array $guarantors = [];

    public string $notes = '';

    public function mount(Request $request): void
    {
        $this->borrower_id = (string) ($request->query('borrower') ?? '');
        $this->first_emi_date = now()->addMonthNoOverflow()->format('Y-m-d');
        $this->guarantors = [GuarantorService::blankRow()];
        $this->syncAnnualFromMonthly();
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

    public function addGuarantorRow(): void
    {
        if (count($this->guarantors) >= 10) {
            return;
        }

        $this->guarantors[] = GuarantorService::blankRow();
    }

    public function removeGuarantorRow(int $index): void
    {
        unset($this->guarantors[$index]);
        $this->guarantors = array_values($this->guarantors);
    }

    /**
     * @return array<int, Borrower>
     */
    #[Computed]
    public function borrowers()
    {
        return Borrower::query()->active()->orderBy('full_name')->get();
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
        } catch (\Throwable) {
            return null;
        }
    }

    public function save(): void
    {
        $populated = [];

        foreach ($this->guarantors as $index => $row) {
            $normalized = GuarantorService::normalize($row);
            $this->guarantors[$index] = $normalized;

            if (GuarantorService::hasAnyValue($normalized)) {
                $populated[$index] = $normalized;
            }
        }

        $rules = [
            'borrower_id' => ['required', Rule::exists('borrowers', 'id')->where('status', 'active')],
            'principal_amount' => ['required', 'numeric', 'min:1000', 'max:100000000'],
            'monthly_interest_rate' => ['required', 'numeric', 'min:0', 'max:5'],
            'annual_interest_rate' => ['required', 'numeric', 'min:0', 'max:60'],
            'interest_method' => ['required', Rule::enum(InterestMethod::class)],
            'tenure_months' => ['required', 'integer', 'min:1', 'max:360'],
            'first_emi_date' => array_filter([
                'required',
                'date',
                $this->disbursement_date !== '' ? 'after_or_equal:disbursement_date' : null,
            ]),
            'disbursement_date' => ['nullable', 'date'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'monthly_income' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'employment_details' => ['nullable', 'string', 'max:255'],
            'guarantors' => ['array', 'max:10'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        foreach (array_keys($populated) as $index) {
            $rules += GuarantorService::rules('guarantors.'.$index);
        }

        $validated = $this->validate($rules);

        $guarantorMobiles = array_values(array_filter(array_column($populated, 'mobile_number')));

        if (count($guarantorMobiles) !== count(array_unique($guarantorMobiles))) {
            $this->addError('guarantors', __('Each guarantor must have a different mobile number.'));

            return;
        }

        $borrower = Borrower::query()->find($this->borrower_id);

        if ($borrower !== null && in_array($borrower->mobile, $guarantorMobiles, true)) {
            $this->addError('guarantors', __('A guarantor mobile number cannot match the borrower mobile number.'));

            return;
        }

        $service = app(LoanService::class);

        $loan = $service->create([
            ...$validated,
            'guarantors' => array_values($populated),
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

                    <flux:input wire:model.live.debounce.400ms="principal_amount" :label="__('Principal amount (₹)')" type="number" step="0.01" min="1000" max="100000000" placeholder="100000" required />
                    <flux:input wire:model.live.debounce.400ms="monthly_interest_rate" :label="__('Monthly interest rate (%)')" type="number" step="0.01" min="0" max="5" placeholder="1" required />
                    @error('monthly_interest_rate') <p class="-mt-2 text-xs text-rose-600 sm:col-span-2">{{ $message }}</p> @enderror
                    <flux:input wire:model="annual_interest_rate" :label="__('Annual interest rate (%)')" type="number" step="0.01" readonly placeholder="= Monthly × 12" />
                    <p class="-mt-2 text-[11px] text-slate-400 sm:col-span-2">{{ __('Annual rate is auto-calculated as monthly rate × 12.') }}</p>
                    <flux:input wire:model.live.debounce.400ms="tenure_months" :label="__('Tenure (months)')" type="number" min="1" max="360" required />
                    <flux:select wire:model.live="interest_method" :label="__('Interest calculation')" required>
                        @foreach (\App\Enums\InterestMethod::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
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
                        <p class="mt-0.5 text-[11px] text-slate-400">{{ __('Optional. Add a person who guarantees or supports this loan.') }}</p>
                    </div>

                    <button type="button" wire:click="addGuarantorRow" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-[11px] font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900">
                        <flux:icon.plus class="size-3.5" />
                        {{ __('Add guarantor') }}
                    </button>
                </div>

                @error('guarantors') <p class="mt-3 text-xs text-rose-600">{{ $message }}</p> @enderror

                <div class="mt-4 space-y-3">
                    @forelse ($guarantors as $index => $guarantorRow)
                        <div wire:key="guarantor-row-{{ $index }}">
                            <x-guarantor-fields model="guarantors.{{ $index }}" :row="$guarantorRow" :index="$index" :removable="count($guarantors) > 1" />
                        </div>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-200 px-3 py-6 text-center text-xs text-slate-400">{{ __('No guarantor added. This section is optional.') }}</p>
                    @endforelse
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
                                    <span class="text-slate-500">#{{ $installment->installmentNo }} · {{ $installment->dueDate->format('d M Y') }}</span>
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
</div>
