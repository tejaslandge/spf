<?php

namespace App\Http\Livewire;

use App\Enums\LoanStatus;
use App\Models\ForeclosureRequest;
use App\Models\Loan;
use App\Services\ForeclosureService;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Title;
use Livewire\Component;
use App\Support\Money;

#[Title('Foreclose Loan')]
class ForecloseLoan extends Component
{
    public Loan $loan;

    public bool $showForecloseModal = false;

    public string $foreclosureEffectiveDate = '';

    public string $foreclosureAmount = '0.00';

    public string $outstandingPrincipal = '0.00';

    public string $accruedInterest = '0.00';

    public string $foreclosureCharges = '0.00';

    public string $otherOutstandingDues = '0.00';

    public string $eligibleAdjustments = '0.00';

    public string $totalSettlement = '0.00';

    public string $calculationDate = '';

    public string $paymentInstructions = '';

    public bool $chargesEnabled = false;

    public string $chargeType = 'fixed';

    public string $chargeValue = '0.00';

    public string $chargeMinimum = '0.00';

    public ?string $chargeMaximum = null;

    protected ForeclosureService $foreclosureService;

    protected SettingsService $settingsService;

    public function mount(Loan $loan): void
    {
        $this->loan = $loan;
        $this->settingsService = app(SettingsService::class);
        $this->loadForeclosureSettings();
    }

    protected function loadForeclosureSettings(): void
    {
        $this->chargesEnabled = $this->settingsService->getBool('loan.foreclosure_charges_enabled', false);
        $this->chargeType = $this->settingsService->get('loan.foreclosure_charge_type', 'fixed');
        $this->chargeValue = $this->settingsService->getDecimal('loan.foreclosure_charge_value', '0');
        $this->chargeMinimum = $this->settingsService->getDecimal('loan.foreclosure_charge_minimum', '0');
        $maximum = $this->settingsService->get('loan.foreclosure_charge_maximum', null);
        $this->chargeMaximum = $maximum !== null ? $this->settingsService->getDecimal('loan.foreclosure_charge_maximum', $maximum) : null;
    }

    public function open(): void
    {
        // Check if charges are enabled and warn the user
        $this->resetForecloseFields();
        $this->showForecloseModal = true;
    }

    public function resetForecloseFields(): void
    {
        $this->foreclosureEffectiveDate = now()->toDateString();
        $this->outstandingPrincipal = '0.00';
        $this->accruedInterest = '0.00';
        $this->foreclosureCharges = '0.00';
        $this->otherOutstandingDues = '0.00';
        $this->eligibleAdjustments = '0.00';
        $this->totalSettlement = '0.00';
        $this->calculationDate = now()->toDateString();
        $this->paymentInstructions = '';

        // Recalculate with current loan state
        $this->calculateForeclosure();
    }

    public function calculateForeclosure(): void
    {
        $settings = app(SettingsService::class);

        $foreclosureChargesEnabled = $settings->getBool('loan.foreclosure_charges_enabled', false);
        $foreclosureChargeType = $settings->get('loan.foreclosure_charge_type', 'fixed');
        $foreclosureChargeValue = $settings->getDecimal('loan.foreclosure_charge_value', '0');
        $foreclosureChargeMinimum = $settings->getDecimal('loan.foreclosure_charge_minimum', '0');
        $foreclosureChargeMaximum = $settings->get('loan.foreclosure_charge_maximum', null);

        $inputs = [
            'foreclosure_effective_date' => $this->foreclosureEffectiveDate,
            'foreclosure_charges_enabled' => $foreclosureChargesEnabled,
            'foreclosure_charge_type' => $foreclosureChargeType,
            'foreclosure_charge_value' => $foreclosureChargeValue,
            'foreclosure_charge_minimum' => $foreclosureChargeMinimum,
            'foreclosure_charge_maximum' => $foreclosureChargeMaximum,
            'effective_from_date' => null,
        ];

        $calculation = $this->foreclosureService->calculateForeclosureAmount($this->loan, $inputs);

        $this->outstandingPrincipal = $calculation->outstanding_principal;
        $this->accruedInterest = $calculation->accrued_interest;
        $this->foreclosureCharges = $calculation->foreclosure_charges;
        $this->otherOutstandingDues = $calculation->other_outstanding_dues;
        $this->eligibleAdjustments = $calculation->eligible_adjustments;
        $this->totalSettlement = $calculation->total_settlement;
        $this->calculationDate = now()->toDateString();

        // Update charges enabled state
        $this->chargesEnabled = $calculation->charges_enabled;

        // Show warning if future interest would have been included
        if (! $foreclosureChargesEnabled) {
            $this->paymentInstructions = '⚠️ Future interest is excluded from the foreclosure amount per current policy. '
                . 'Only accrued interest up to the foreclosure effective date is included.';
        } else {
            $this->paymentInstructions = 'Payment instructions will be provided upon approval.';
        }
    }

    public function submitForeclosureRequest(): void
    {
        $this->validate([
            'foreclosureEffectiveDate' => ['required', 'date', 'before_or_equal:today'],
        ]);

        // Use the LoanService to record the foreclosure request
        $request = app(LoanService::class)->foreclose(
            $this->loan,
            auth()->user(),
            $this->foreclosureEffectiveDate
        );

        $this->showForecloseModal = false;

        Flux::toast(variant: 'success', text: __('Foreclosure request submitted successfully. Request ID: :id', ['id' => $request->id]));

        $this->redirectRoute('loans.show', $this->loan, navigate: true);
    }

    public function render()
    {
        return view('livewire.foreclose-loan');
    }
}