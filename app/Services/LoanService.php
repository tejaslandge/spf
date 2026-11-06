<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\InterestMethod;
use App\Enums\LoanFundingStatus;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\LoanCalculation;
use App\Support\LoanCalculator;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoanService
{
    public function __construct(
        private readonly LoanCalculator $calculator,
        private readonly JournalService $journal,
        private readonly InterestDistributionService $distribution,
        private readonly GuarantorService $guarantorService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): Loan
    {
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

            if (! empty($data['guarantor_ids'])) {
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

    public function approveForDisbursement(Loan $loan, User $actor): Loan
    {
        return DB::transaction(function () use ($loan, $actor): Loan {
            /** @var Loan $locked */
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->getKey());

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

            return $locked->refresh();
        });
    }

    public function disburse(Loan $loan, User $actor, ?CarbonImmutable $date = null, ?string $reference = null): Loan
    {
        return DB::transaction(function () use ($loan, $actor, $date, $reference): Loan {
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

            $this->journal->post(
                $date,
                'Loan disbursed to '.$locked->borrower->full_name,
                [
                    ['code' => '1100', 'debit' => (string) $locked->principal_amount, 'narration' => 'Loan receivable '.$locked->loan_code],
                    ['code' => '1000', 'credit' => (string) $locked->principal_amount, 'narration' => 'Cash disbursed '.$locked->loan_code],
                ],
                $locked,
            );

            AuditLogger::log('loan.disbursed', $locked, [], [
                'loan_id' => $locked->getKey(),
                'disbursement_date' => $date->toDateString(),
                'reference' => $locked->disbursement_reference,
            ]);

            $this->distribution->ensureSnapshot($locked->refresh(), $actor);

            return $locked->refresh();
        });
    }

    public function close(Loan $loan, User $actor): Loan
    {
        if ($loan->status !== LoanStatus::Active) {
            throw new InvalidArgumentException('Only active loans can be closed.');
        }

        if (Money::compare($loan->outstanding_principal, '0') > 0) {
            throw new InvalidArgumentException('The loan still has an outstanding principal balance.');
        }

        $loan->forceFill([
            'status' => LoanStatus::Closed,
            'closed_at' => now(),
        ])->save();

        return $loan;
    }
}
