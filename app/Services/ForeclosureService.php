<?php

namespace App\Services;

use App\Enums\InterestMethod;
use App\Enums\LoanStatus;
use App\Models\ForeclosureRequest;
use App\Models\Loan;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\AuditLogger;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ForeclosureService
{
    public function __construct(
        private readonly LoanEmiSettlementService $loanEmiSettlementService,
    ) {}

    /**
     * Calculate the foreclosure amount for a loan.
     *
     * Foreclosure Amount =
     *   Outstanding Principal
     * + Accrued Interest up to Foreclosure Date
     * + Other Legitimate Outstanding Dues
     * + Applicable Foreclosure Charges
     * - Eligible Adjustments or Credits
     *
     * Future interest is always excluded.
     *
     * @param  array<mixed, mixed>  $calculationInputs
     *                                                  - foreclosure_effective_date: date
     *                                                  - foreclosure_charges_enabled: bool
     *                                                  - foreclosure_charge_type: string ('fixed'|'percentage')
     *                                                  - foreclosure_charge_value: decimal
     *                                                  - foreclosure_charge_minimum: decimal
     *                                                  - foreclosure_charge_maximum: decimal
     *                                                  - effective_from_date: date (for charge applicability)
     *                                                  - waive_accrued_interest: bool (exclude accrued interest from the settlement)
     */
    public function calculateForeclosureAmount(Loan $loan, array $inputs): ForeclosureCalculation
    {
        $effectiveDate = CarbonImmutable::parse($inputs['foreclosure_effective_date'] ?? now()->toDateString());
        $chargesEnabled = $inputs['foreclosure_charges_enabled'] ?? false;
        $chargeType = $inputs['foreclosure_charge_type'] ?? 'fixed';
        $chargeValue = Money::round((string) ($inputs['foreclosure_charge_value'] ?? 0));
        $chargeMinimum = Money::round((string) ($inputs['foreclosure_charge_minimum'] ?? 0));
        $chargeMaximum = $inputs['foreclosure_charge_maximum'] !== null
            ? Money::round((string) $inputs['foreclosure_charge_maximum'])
            : null;
        $waiveAccruedInterest = (bool) ($inputs['waive_accrued_interest'] ?? false);

        // 1. Outstanding principal snapshot (as of the foreclosure date)
        $outstandingPrincipal = Money::round($loan->outstanding_principal);

        // 2. Accrued interest up to the foreclosure date
        // Use the loan's existing interest calculation methodology
        $accruedInterest = $this->calculateAccruedInterest($loan, $effectiveDate);

        // 3. Other legitimate outstanding dues (processing fees, other charges)
        $otherOutstandingDues = Money::round(
            Money::add(
                (string) ($loan->processing_fee ?? '0'),
                (string) ($loan->other_charges ?? '0')
            )
        );

        // 4. Applicable foreclosure charges
        $foreclosureCharges = $this->calculateForeclosureCharges(
            $outstandingPrincipal,
            $accruedInterest,
            $chargeType,
            $chargeValue,
            $chargeMinimum,
            $chargeMaximum,
            $chargesEnabled
        );

        // 5. Eligible adjustments or credits (e.g., part-payments already made)
        $eligibleAdjustments = $this->calculateEligibleAdjustments($loan);

        // 5b. Optionally waive the accrued interest so the borrower only pays
        // the outstanding principal (plus dues/charges and other credits).
        $waivedInterest = $waiveAccruedInterest ? $accruedInterest : '0.00';

        // 6. Calculate total foreclosure amount
        $totalSettlement = Money::round(
            Money::sub(
                Money::add(
                    Money::add(
                        Money::add($outstandingPrincipal, $accruedInterest),
                        $foreclosureCharges
                    ),
                    $otherOutstandingDues
                ),
                Money::add($eligibleAdjustments, $waivedInterest)
            )
        );

        // Prevent negative totals
        $totalSettlement = max($totalSettlement, '0.00');

        return new ForeclosureCalculation(
            outstanding_principal: $outstandingPrincipal,
            accrued_interest: $accruedInterest,
            other_outstanding_dues: $otherOutstandingDues,
            foreclosure_charges: $foreclosureCharges,
            eligible_adjustments: $eligibleAdjustments,
            total_settlement: $totalSettlement,
            calculation_date: now(),
            effective_date: $effectiveDate,
            charges_enabled: $chargesEnabled,
            charge_type: $chargeType,
            waived_interest: $waivedInterest,
        );
    }

    /**
     * Calculate accrued interest up to the foreclosure date using the existing
     * loan interest methodology (flat or reducing balance).
     */
    private function calculateAccruedInterest(Loan $loan, CarbonImmutable $effectiveDate): string
    {
        $principal = Money::round((string) $loan->principal_amount);
        $annualRate = (string) $loan->annual_interest_rate;
        $interestMethod = $loan->interest_method;
        $tenureMonths = (int) $loan->tenure_months;
        $firstEmiDate = $loan->first_emi_date ? CarbonImmutable::parse($loan->first_emi_date) : null;

        // For interest calculation up to the effective date, we need to determine
        // how many months have elapsed since disbursement
        $disbursementDate = CarbonImmutable::parse($loan->disbursement_date ?? $loan->created_at);
        $monthsElapsed = $disbursementDate->diffInMonths($effectiveDate, false);

        // Ensure at least 0 months
        $monthsElapsed = max(0, $monthsElapsed);

        // Cap at tenure to prevent future interest calculation
        $monthsElapsed = min($monthsElapsed, $tenureMonths);

        if ($monthsElapsed <= 0 || Money::isZero($annualRate)) {
            return '0.00';
        }

        if ($interestMethod === InterestMethod::Flat) {
            // Flat interest: total interest is spread across the tenure
            // Accrued interest = (total_interest * months_elapsed) / tenure_months
            $totalInterest = Money::round((string) $loan->total_interest);
            if (Money::isZero($totalInterest)) {
                return '0.00';
            }
            $accrued = Money::round(bcdiv(Money::mul($totalInterest, (string) $monthsElapsed), (string) $tenureMonths, 10));

            return $accrued;
        } else {
            // Reducing balance: calculate interest month-by-month up to the effective date
            $monthlyRate = bcdiv($annualRate, '1200', 12);
            $emi = $this->calculateReducingEmi($principal, $monthlyRate, $tenureMonths);

            $outstanding = $principal;
            $totalAccruedInterest = '0.00';

            for ($month = 1; $month <= $monthsElapsed; $month++) {
                $interest = Money::round(bcmul($outstanding, $monthlyRate, 10));
                $totalAccruedInterest = Money::round(Money::add($totalAccruedInterest, $interest));
                // Principal component reduces outstanding
                $rowPrincipal = Money::sub($emi, $interest);
                if (Money::compare($rowPrincipal, $outstanding) > 0) {
                    $rowPrincipal = $outstanding;
                }
                $outstanding = Money::sub($outstanding, $rowPrincipal);
            }

            return $totalAccruedInterest;
        }
    }

    private function calculateReducingEmi(string $principal, string $monthlyRate, int $months): string
    {
        if (Money::isZero($monthlyRate)) {
            return Money::round(Money::div($principal, (string) $months));
        }

        $growth = bcpow(bcadd('1', $monthlyRate, 12), (string) $months, 12);
        $numerator = bcmul(bcmul($principal, $monthlyRate, 12), $growth, 12);
        $denominator = bcsub($growth, '1', 12);

        return Money::round(bcdiv($numerator, $denominator, 12));
    }

    /**
     * Calculate applicable foreclosure charges based on configuration.
     * When charges are disabled, always returns 0.
     */
    private function calculateForeclosureCharges(
        string $outstandingPrincipal,
        string $accruedInterest,
        string $chargeType,
        string $chargeValue,
        string $chargeMinimum,
        ?string $chargeMaximum,
        bool $chargesEnabled
    ): string {
        if (! $chargesEnabled) {
            return '0.00';
        }

        // Determine the charge basis - percentage of outstanding principal
        // or a fixed amount
        $chargeAmount = '0.00';

        if ($chargeType === 'percentage') {
            // Charge is a percentage of outstanding principal
            $percent = Money::round($chargeValue); // e.g., 2.00 for 2%
            $chargeAmount = Money::round(Money::div(Money::mul($outstandingPrincipal, $percent), '100'));
        } else {
            // Fixed charge value
            $chargeAmount = $chargeValue;
        }

        // Apply minimum limit
        if (Money::compare($chargeAmount, $chargeMinimum) < 0) {
            $chargeAmount = $chargeMinimum;
        }

        // Apply maximum limit
        if ($chargeMaximum !== null && Money::compare($chargeAmount, $chargeMaximum) > 0) {
            $chargeAmount = $chargeMaximum;
        }

        return Money::round($chargeAmount);
    }

    /**
     * Calculate eligible adjustments/credits.
     * For now, this includes any part-payments that have been applied
     * but not yet accounted for in the outstanding principal snapshot.
     * In a full implementation, this would consider reversed payments,
     * waived interests, etc.
     */
    private function calculateEligibleAdjustments(Loan $loan): string
    {
        // Adjustments include any reversed payments that need to be
        // credit back to the settlement amount
        $reversedPayments = (string) ($loan->payments()
            ->where('is_reversed', true)
            ->sum('principal_component') ?? '0');

        // Also consider any interest distribution adjustments
        $distributionAdjustments = (string) ($loan->distributionEntries()
            ->where('status', 'reversed')
            ->sum('amount') ?? '0');

        return Money::round(
            Money::add(
                (string) ($reversedPayments ?? '0'),
                (string) ($distributionAdjustments ?? '0')
            )
        );
    }

    /**
     * Build calculation inputs from the configured foreclosure policy settings.
     *
     * @return array<string, mixed>
     */
    public function settingsInputs(?string $effectiveDate = null, bool $waiveAccruedInterest = false): array
    {
        $settings = app(SettingsService::class);

        return [
            'foreclosure_effective_date' => $effectiveDate ?? now()->toDateString(),
            'foreclosure_charges_enabled' => $settings->getBool('loan.foreclosure_charges_enabled', false),
            'foreclosure_charge_type' => $settings->get('loan.foreclosure_charge_type', 'fixed'),
            'foreclosure_charge_value' => $settings->getDecimal('loan.foreclosure_charge_value', '0'),
            'foreclosure_charge_minimum' => $settings->getDecimal('loan.foreclosure_charge_minimum', '0'),
            'foreclosure_charge_maximum' => $settings->get('loan.foreclosure_charge_maximum'),
            'effective_from_date' => $settings->get('loan.foreclosure_effective_from'),
            'waive_accrued_interest' => $waiveAccruedInterest,
        ];
    }

    /**
     * Record a foreclosure request in the database.
     */
    public function recordRequest(Loan $loan, User $actor, array $inputs): ForeclosureRequest
    {
        $calculation = $this->calculateForeclosureAmount($loan, $inputs);

        return DB::transaction(function () use ($loan, $calculation): ForeclosureRequest {
            $request = ForeclosureRequest::create([
                'loan_id' => $loan->getKey(),
                'borrower_id' => $loan->borrower_id,
                'outstanding_principal' => $calculation->outstanding_principal,
                'accrued_interest' => $calculation->accrued_interest,
                'other_outstanding_dues' => $calculation->other_outstanding_dues,
                'foreclosure_charges' => $calculation->foreclosure_charges,
                'total_settlement' => $calculation->total_settlement,
                'eligible_adjustments' => $calculation->eligible_adjustments,
                'waived_interest' => $calculation->waived_interest,
                'foreclosure_request_date' => now()->toDateString(),
                'effective_foreclosure_date' => $calculation->effective_date->toDateString(),
                'approval_status' => 'pending',
                'payment_status' => 'unpaid',
            ]);

            AuditLogger::log('foreclosure.requested', $loan, [], [
                'outstanding_principal' => $calculation->outstanding_principal,
                'accrued_interest' => $calculation->accrued_interest,
                'foreclosure_charges' => $calculation->foreclosure_charges,
                'total_settlement' => $calculation->total_settlement,
                'waived_interest' => $calculation->waived_interest,
            ]);

            return $request;
        });
    }

    /**
     * Approve a foreclosure request and mark the loan as foreclosed.
     */
    public function approveRequest(ForeclosureRequest $request, User $actor): ForeclosureRequest
    {
        return DB::transaction(function () use ($request, $actor): ForeclosureRequest {
            $request->forceFill([
                'approval_status' => 'approved',
                'approved_by' => $actor->id,
            ])->save();

            $loan = $request->loan;

            // Mark loan as foreclosed
            $loan->forceFill([
                'status' => LoanStatus::Foreclosed->value,
            ])->save();

            $this->loanEmiSettlementService->settleRemainingUnpaidAsPaid($loan, 'foreclosure');

            // Record payment date and closure date
            $today = now();

            // Create the payment record for the foreclosure amount. The
            // receipt number is generated by the EmiPayment model so it stays
            // in sync with regular EMI receipts.
            $loan->payments()->create([
                'amount' => $request->total_settlement,
                'principal_component' => $request->outstanding_principal,
                'interest_component' => Money::sub($request->accrued_interest, $request->waived_interest),
                'penalty_amount' => $request->foreclosure_charges,
                'payment_date' => $today->toDateString(),
                'payment_method' => 'cash',
                'reference' => 'FORECLOSURE-'.$request->id,
                'notes' => 'Loan foreclosure settlement',
            ]);

            // Update request with payment and closure dates
            $request->forceFill([
                'payment_status' => 'paid',
                'payment_date' => $today->toDateString(),
                'actual_closure_date' => $today->toDateString(),
            ])->save();

            // Audit log
            AuditLogger::log('foreclosure.approved', $loan, [], [
                'total_settlement' => $request->total_settlement,
                'waived_interest' => $request->waived_interest,
                'approval_status' => 'approved',
                'actual_closure_date' => $today->toDateString(),
            ]);

            return $request->refresh();
        });
    }

    /**
     * Reject a foreclosure request.
     */
    public function rejectRequest(ForeclosureRequest $request, User $actor, string $rejectionReason): ForeclosureRequest
    {
        return DB::transaction(function () use ($request, $rejectionReason): ForeclosureRequest {
            $request->forceFill([
                'approval_status' => 'rejected',
                'rejection_reason' => $rejectionReason,
            ])->save();

            // Audit log
            AuditLogger::log('foreclosure.rejected', $request->loan, [], [
                'rejection_reason' => $rejectionReason,
                'approval_status' => 'rejected',
            ]);

            return $request->refresh();
        });
    }

    /**
     * Process foreclosure payment for an approved request and complete the foreclosure.
     */
    public function processPayment(ForeclosureRequest $request, User $actor, string $paymentMethod = 'cash', ?string $reference = null): ForeclosureRequest
    {
        return DB::transaction(function () use ($request, $paymentMethod, $reference): ForeclosureRequest {
            /** @var ForeclosureRequest $locked */
            $locked = ForeclosureRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if ($locked->payment_status === 'paid') {
                throw new \InvalidArgumentException('Foreclosure payment has already been processed for this request.');
            }

            if ($locked->approval_status !== 'approved') {
                throw new \InvalidArgumentException('Only approved foreclosure requests can be paid.');
            }

            /** @var Loan $loan */
            $loan = Loan::query()->lockForUpdate()->findOrFail($locked->loan_id);

            $loan->forceFill([
                'status' => LoanStatus::Foreclosed->value,
            ])->save();

            $this->loanEmiSettlementService->settleRemainingUnpaidAsPaid($loan, 'foreclosure');

            $today = now();

            $locked->forceFill([
                'payment_status' => 'paid',
                'payment_date' => $today->toDateString(),
                'actual_closure_date' => $today->toDateString(),
            ])->save();

            $loan->payments()->create([
                'amount' => $locked->total_settlement,
                'principal_component' => $locked->outstanding_principal,
                'interest_component' => Money::sub($locked->accrued_interest, $locked->waived_interest),
                'penalty_amount' => $locked->foreclosure_charges,
                'payment_date' => $today->toDateString(),
                'payment_method' => $paymentMethod,
                'reference' => $reference ?: 'FORECLOSURE-'.$locked->id,
                'notes' => 'Loan foreclosure settlement payment',
            ]);

            AuditLogger::log('foreclosure.payment_processed', $loan, [], [
                'total_settlement' => $locked->total_settlement,
                'payment_status' => 'paid',
                'payment_date' => $today->toDateString(),
            ]);

            return $locked->refresh();
        });
    }
}
