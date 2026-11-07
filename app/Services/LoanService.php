<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\InterestMethod;
use App\Enums\LoanFundingStatus;
use App\Enums\LoanStatus;
use App\Enums\Permission;
use App\Models\ForeclosureRequest;
use App\Models\Loan;
use App\Models\User;
use App\Services\Notifications\StaffNotifier;
use App\Services\Settings\SettingsService;
use App\Support\AuditLogger;
use App\Support\LoanCalculation;
use App\Support\LoanCalculator;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class LoanService
{
    public function __construct(
        private readonly LoanCalculator $calculator,
        private readonly JournalService $journal,
        private readonly InterestDistributionService $distribution,
        private readonly GuarantorService $guarantorService,
        private readonly StaffNotifier $notifier,
        private readonly ForeclosureService $foreclosureService,
        private readonly LoanEmiSettlementService $loanEmiSettlementService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): Loan
    {
        $this->assertTermsWithinSettings($data);

        $hasGuarantor = ! empty($data['guarantor_selections'])
            || ! empty($data['guarantor_ids'])
            || ! empty($data['guarantors']);

        if (! $hasGuarantor) {
            throw ValidationException::withMessages([
                'selectedGuarantorIds' => __('At least one guarantor is required for every loan.'),
            ]);
        }

        return DB::transaction(function () use ($data, $actor): Loan {
            $calculation = $this->preview(
                $data['principal_amount'],
                $data['annual_interest_rate'],
                (int) $data['tenure_months'],
                $data['interest_method'],
                $data['first_emi_date'],
            );

            $loan = Loan::query()->create([
                'borrower_id' => $data['borrower_id'],
                'principal_amount' => $calculation->principal,
                'annual_interest_rate' => $data['annual_interest_rate'],
                'interest_method' => $data['interest_method'],
                'tenure_months' => $calculation->tenureMonths,
                'emi_frequency' => $data['emi_frequency'] ?? 'monthly',
                'disbursement_date' => filled($data['disbursement_date'] ?? null) ? $data['disbursement_date'] : null,
                'first_emi_date' => $data['first_emi_date'],
                'emi_amount' => $calculation->emiAmount,
                'total_interest' => $calculation->totalInterest,
                'total_payable' => $calculation->totalPayable,
                'outstanding_principal' => $calculation->principal,
                'outstanding_interest' => $calculation->totalInterest,
                'foreclosure_charge' => $data['foreclosure_charge'] ?? 0,
                'processing_fee' => $data['processing_fee'] ?? 0,
                'other_charges' => $data['other_charges'] ?? 0,
                'status' => LoanStatus::Pending,
                'application_status' => ApplicationStatus::Submitted,
                'purpose' => $data['purpose'] ?? null,
                'monthly_income' => $data['monthly_income'] ?? null,
                'employment_details' => $data['employment_details'] ?? null,
                'created_by' => $actor?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($calculation->installments as $installment) {
                $loan->schedules()->create([
                    'installment_no' => $installment->installmentNo,
                    'due_date' => $installment->dueDate,
                    'principal_amount' => $installment->principal,
                    'interest_amount' => $installment->interest,
                    'total_amount' => $installment->total,
                    'outstanding_principal' => $installment->outstandingPrincipal,
                    'status' => 'pending',
                ]);
            }

            if (! empty($data['guarantor_selections'])) {
                $this->guarantorService->attachSelectionsToLoan($loan, $data['guarantor_selections']);
            } elseif (! empty($data['guarantor_ids'])) {
                $loan->guarantors()->sync($data['guarantor_ids']);
            }

            if (! empty($data['guarantors'])) {
                $this->guarantorService->syncForLoan($loan, $data['guarantors'], $actor);
            }

            return $loan->load('schedules', 'borrower', 'guarantors');
        });
    }

    public function preview(
        string|int|float $principal,
        string|int|float $annualRate,
        int $tenureMonths,
        string|InterestMethod $method,
        CarbonImmutable|string $firstEmiDate,
    ): LoanCalculation {
        $method = $method instanceof InterestMethod
            ? $method
            : InterestMethod::from($method);

        return $this->calculator->calculate($principal, $annualRate, $tenureMonths, $method, $firstEmiDate);
    }

    /**
     * Enforce the database-driven loan limits on the authoritative backend,
     * independently of the form validation.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertTermsWithinSettings(array $data): void
    {
        $settings = app(SettingsService::class);
        $errors = [];

        $amount = (string) ($data['principal_amount'] ?? '0');
        $minAmount = $settings->getDecimal('loan.min_amount', '1000');
        $maxAmount = $settings->getDecimal('loan.max_amount', '100000000');

        if (! is_numeric($amount) || Money::compare($amount, $minAmount) < 0 || Money::compare($amount, $maxAmount) > 0) {
            $errors['principal_amount'] = __('The loan amount must be between :min and :max.', ['min' => $minAmount, 'max' => $maxAmount]);
        }

        $tenure = (int) ($data['tenure_months'] ?? 0);
        $minTenure = $settings->getInt('loan.min_tenure_months', 1);
        $maxTenure = $settings->getInt('loan.max_tenure_months', 360);

        if ($tenure < $minTenure || $tenure > $maxTenure) {
            $errors['tenure_months'] = __('The loan tenure must be between :min and :max months.', ['min' => $minTenure, 'max' => $maxTenure]);
        }

        $annual = $data['annual_interest_rate'] ?? '0';
        $minRate = $settings->getDecimal('loan.min_monthly_rate', '0');
        $maxRate = $settings->getDecimal('loan.max_monthly_rate', '5');

        if (! is_numeric($annual)) {
            $errors['annual_interest_rate'] = __('The interest rate is invalid.');
        } else {
            $monthly = bcdiv((string) $annual, '12', 6);

            if (bccomp($monthly, $minRate, 6) < 0 || bccomp($monthly, $maxRate, 6) > 0) {
                $errors['annual_interest_rate'] = __('The monthly interest rate must be between :min% and :max%.', ['min' => $minRate, 'max' => $maxRate]);
            }
        }

        $method = ($data['interest_method'] ?? null) instanceof InterestMethod
            ? $data['interest_method']->value
            : (string) ($data['interest_method'] ?? '');
        $allowed = $settings->getJson('loan.allowed_interest_methods', ['flat', 'reducing']);

        if (! in_array($method, $allowed, true)) {
            $errors['interest_method'] = __('The selected interest method is not enabled.');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function approveForDisbursement(Loan $loan, User $actor): Loan
    {
        return DB::transaction(function () use ($loan, $actor): Loan {
            /** @var Loan $locked */
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->getKey());

            if (app(SettingsService::class)->getBool('loan.require_super_admin_approval', false) && ! $actor->isSuperAdmin()) {
                throw new InvalidArgumentException('This organisation requires final disbursement approval to be recorded by a Super Admin.');
            }

            if ($locked->status !== LoanStatus::Pending) {
                throw new InvalidArgumentException('Only pending loans can receive final disbursement approval.');
            }

            if (! $locked->application_status->allowsProcessing()) {
                throw new InvalidArgumentException('The borrower must accept the loan offer before final approval.');
            }

            if ($locked->capital_verified_at === null) {
                throw new InvalidArgumentException('Investor capital verification must be completed before final approval.');
            }

            if ($locked->funding_status !== LoanFundingStatus::FullyFunded) {
                throw new InvalidArgumentException('The loan is not fully funded. Final approval is only available at 100% funding.');
            }

            $locked->forceFill([
                'final_approved_at' => now(),
                'final_approved_by' => $actor->getKey(),
                'approved_by' => $actor->getKey(),
            ])->save();

            AuditLogger::log('disbursement.approved', $locked, [], [
                'loan_id' => $locked->getKey(),
                'final_approved_by' => $actor->getKey(),
            ]);

            $this->notifier->notifyPermission(
                Permission::LoansApprove,
                'loan_approvals',
                __('Loan approved for disbursement'),
                $locked->loan_code.' · '.$locked->borrower->full_name.' · '.Money::format((string) $locked->principal_amount),
                route('loans.show', $locked),
                $actor,
            );

            return $locked->refresh();
        });
    }

    public function disburse(Loan $loan, User $actor, ?CarbonImmutable $date = null, ?string $reference = null): Loan
    {
        return DB::transaction(function () use ($loan, $actor, $date, $reference): Loan {
            $settings = app(SettingsService::class);

            /** @var Loan $locked */
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->getKey());

            if ($locked->status !== LoanStatus::Pending) {
                throw new InvalidArgumentException('Only pending loans can be disbursed.');
            }

            if ($locked->funding_status !== LoanFundingStatus::FullyFunded) {
                throw new InvalidArgumentException('The loan is not fully funded. Investors must confirm the full approved amount before disbursement.');
            }

            if ($locked->final_approved_at === null) {
                throw new InvalidArgumentException('Final disbursement approval has not been recorded for this loan.');
            }

            $date ??= CarbonImmutable::today();

            $locked->forceFill([
                'status' => LoanStatus::Active,
                'disbursement_date' => $date,
                'disbursement_reference' => filled($reference) ? $reference : $locked->disbursement_reference,
            ])->save();

            $fee = Money::round((string) $locked->processing_fee);
            $deductFee = $settings->get('loan.processing_fee_collection', 'record_only') === 'deduct_at_disbursement'
                && Money::compare($fee, '0') > 0;

            $journalLines = [
                ['code' => '1100', 'debit' => (string) $locked->principal_amount, 'narration' => 'Loan receivable '.$locked->loan_code],
                [
                    'code' => '1000',
                    'credit' => $deductFee ? Money::sub((string) $locked->principal_amount, $fee) : (string) $locked->principal_amount,
                    'narration' => 'Cash disbursed '.$locked->loan_code,
                ],
            ];

            if ($deductFee) {
                $journalLines[] = ['code' => '4400', 'credit' => $fee, 'narration' => 'Processing fee '.$locked->loan_code];
            }

            $this->journal->post(
                $date,
                'Loan disbursed to '.$locked->borrower->full_name,
                $journalLines,
                $locked,
            );

            AuditLogger::log('loan.disbursed', $locked, [], [
                'loan_id' => $locked->getKey(),
                'disbursement_date' => $date->toDateString(),
                'reference' => $locked->disbursement_reference,
                'processing_fee_deducted' => $deductFee ? $fee : '0.00',
            ]);

            $this->notifier->notifyPermission(
                Permission::LoansView,
                'loan_updates',
                __('Loan disbursed'),
                $locked->loan_code.' · '.$locked->borrower->full_name.' · '.Money::format((string) $locked->principal_amount),
                route('loans.show', $locked),
                $actor,
            );

            $this->distribution->ensureSnapshot($locked->refresh(), $actor);

            return $locked->refresh();
        });
    }

    public function close(Loan $loan, User $actor): Loan
    {
        return DB::transaction(function () use ($loan): Loan {
            /** @var Loan $locked */
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->getKey());

            if ($locked->status !== LoanStatus::Active) {
                throw new InvalidArgumentException('Only active loans can be closed.');
            }

            if (Money::compare($locked->outstanding_principal, '0') > 0) {
                throw new InvalidArgumentException('The loan still has an outstanding principal balance.');
            }

            $locked->forceFill([
                'status' => LoanStatus::Closed,
                'closed_at' => now(),
            ])->save();

            $this->loanEmiSettlementService->settleRemainingUnpaidAsPaid($locked, 'loan_closure');

            return $locked->refresh();
        });
    }

    public function foreclose(Loan $loan, User $actor, ?string $effectiveDate = null, bool $waiveAccruedInterest = false): ForeclosureRequest
    {
        if ($loan->status !== LoanStatus::Active) {
            throw new InvalidArgumentException('Only active loans can be foreclosed.');
        }

        $latestRequest = $loan->latestForeclosureRequest();

        if ($latestRequest !== null && in_array($latestRequest->approval_status, ['pending', 'approved'], true)) {
            throw new InvalidArgumentException('A foreclosure request is already in progress for this loan.');
        }

        $inputs = $this->foreclosureService->settingsInputs($effectiveDate, $waiveAccruedInterest);

        return $this->foreclosureService->recordRequest($loan, $actor, $inputs);
    }
}
