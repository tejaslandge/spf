<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\InterestMethod;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanOffer;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\LoanCalculation;
use App\Support\LoanCalculator;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoanOfferService
{
    public function __construct(
        private readonly LoanCalculator $calculator,
    ) {}

    /**
     * Side-by-side repayment options for both interest methods.
     *
     * @return array{flat: LoanCalculation, reducing: LoanCalculation}
     */
    public function compare(
        string|int|float $principal,
        string|int|float $annualRate,
        int $tenureMonths,
        CarbonImmutable|string $firstEmiDate,
    ): array {
        return [
            'flat' => $this->calculator->calculate($principal, $annualRate, $tenureMonths, InterestMethod::Flat, $firstEmiDate),
            'reducing' => $this->calculator->calculate($principal, $annualRate, $tenureMonths, InterestMethod::Reducing, $firstEmiDate),
        ];
    }

    /**
     * @param  array{principal_amount: string, annual_interest_rate: string, interest_method: string, tenure_months: int, processing_fee?: string, other_charges?: string, notes?: string|null}  $data
     */
    public function generate(Loan $loan, array $data, User $actor): LoanOffer
    {
        return DB::transaction(function () use ($loan, $data, $actor): LoanOffer {
            /** @var Loan $locked */
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->getKey());

            $this->ensureOfferable($locked);

            $method = InterestMethod::from($data['interest_method']);
            $firstEmiDate = $locked->first_emi_date;

            $calculation = $this->calculator->calculate(
                $data['principal_amount'],
                $data['annual_interest_rate'],
                (int) $data['tenure_months'],
                $method,
                $firstEmiDate,
            );

            $comparison = $this->compare(
                $data['principal_amount'],
                $data['annual_interest_rate'],
                (int) $data['tenure_months'],
                $firstEmiDate,
            );

            $offer = $locked->offers()->create([
                'principal_amount' => $calculation->principal,
                'annual_interest_rate' => $data['annual_interest_rate'],
                'interest_method' => $method,
                'tenure_months' => $calculation->tenureMonths,
                'emi_amount' => $calculation->emiAmount,
                'total_interest' => $calculation->totalInterest,
                'total_repayment' => $calculation->totalPayable,
                'processing_fee' => Money::round($data['processing_fee'] ?? 0),
                'other_charges' => Money::round($data['other_charges'] ?? 0),
                'comparison' => [
                    'flat' => $this->summaries($comparison['flat'], $comparison['flat']->totalInterest, $comparison['flat']->totalPayable),
                    'reducing' => $this->summaries($comparison['reducing'], $comparison['reducing']->totalInterest, $comparison['reducing']->totalPayable),
                ],
                'created_by' => $actor->getKey(),
                'notes' => $data['notes'] ?? null,
            ]);

            if (in_array($locked->application_status, [ApplicationStatus::Submitted, ApplicationStatus::UnderReview], true)) {
                $locked->forceFill(['application_status' => ApplicationStatus::OfferGenerated])->save();
            }

            AuditLogger::log('offer.generated', $offer, [], [
                'loan_id' => $locked->getKey(),
                'principal_amount' => (string) $offer->principal_amount,
                'annual_interest_rate' => (string) $offer->annual_interest_rate,
                'interest_method' => $offer->interest_method->value,
                'tenure_months' => $offer->tenure_months,
            ]);

            return $offer;
        });
    }

    public function accept(LoanOffer $offer, User $actor): Loan
    {
        return DB::transaction(function () use ($offer, $actor): Loan {
            /** @var Loan $locked */
            $locked = Loan::query()->lockForUpdate()->findOrFail($offer->loan_id);

            if ($offer->accepted_at !== null) {
                throw new InvalidArgumentException('This offer has already been accepted.');
            }

            if ($locked->status !== LoanStatus::Pending) {
                throw new InvalidArgumentException('Offers can only be accepted while the loan is pending.');
            }

            if (in_array($locked->application_status, [ApplicationStatus::Rejected, ApplicationStatus::Cancelled], true)) {
                throw new InvalidArgumentException('A rejected or cancelled application cannot accept an offer.');
            }

            $calculation = $this->calculator->calculate(
                $offer->principal_amount,
                $offer->annual_interest_rate,
                $offer->tenure_months,
                $offer->interest_method,
                $locked->first_emi_date,
            );

            $locked->forceFill([
                'principal_amount' => $offer->principal_amount,
                'annual_interest_rate' => $offer->annual_interest_rate,
                'interest_method' => $offer->interest_method,
                'tenure_months' => $offer->tenure_months,
                'emi_amount' => $calculation->emiAmount,
                'total_interest' => $calculation->totalInterest,
                'total_payable' => $calculation->totalPayable,
                'outstanding_principal' => $calculation->principal,
                'outstanding_interest' => $calculation->totalInterest,
                'processing_fee' => $offer->processing_fee,
                'other_charges' => $offer->other_charges,
                'application_status' => ApplicationStatus::Accepted,
            ])->save();

            $this->rebuildSchedule($locked, $calculation);

            $offer->forceFill(['accepted_at' => now(), 'accepted_by' => $actor->getKey()])->save();

            AuditLogger::log('offer.accepted', $locked, [
                'application_status' => ApplicationStatus::OfferGenerated->value,
            ], [
                'application_status' => ApplicationStatus::Accepted->value,
                'loan_offer_id' => $offer->getKey(),
            ]);

            return $locked->refresh();
        });
    }

    public function markUnderReview(Loan $loan, User $actor): Loan
    {
        if ($loan->application_status !== ApplicationStatus::Submitted) {
            throw new InvalidArgumentException('Only submitted applications can be moved to review.');
        }

        $loan->forceFill(['application_status' => ApplicationStatus::UnderReview])->save();

        AuditLogger::log('application.under_review', $loan, [
            'application_status' => ApplicationStatus::Submitted->value,
        ], [
            'application_status' => ApplicationStatus::UnderReview->value,
        ]);

        return $loan->refresh();
    }

    public function rejectApplication(Loan $loan, string $reason, User $actor): Loan
    {
        if ($loan->status !== LoanStatus::Pending || $loan->application_status === ApplicationStatus::Accepted) {
            throw new InvalidArgumentException('Only pending, not-yet-accepted applications can be rejected.');
        }

        $previous = $loan->getRawOriginal('application_status');

        $loan->forceFill([
            'application_status' => ApplicationStatus::Rejected,
            'status' => LoanStatus::Rejected,
            'notes' => trim(($loan->notes ? $loan->notes."\n" : '').'Rejected: '.$reason),
        ])->save();

        AuditLogger::log('application.rejected', $loan, [
            'application_status' => $previous,
        ], [
            'application_status' => ApplicationStatus::Rejected->value,
            'reason' => $reason,
        ]);

        return $loan->refresh();
    }

    private function rebuildSchedule(Loan $loan, LoanCalculation $calculation): void
    {
        if ($loan->payments()->exists()) {
            throw new InvalidArgumentException('The repayment schedule cannot be rebuilt after payments exist.');
        }

        $loan->schedules()->delete();

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
    }

    private function ensureOfferable(Loan $loan): void
    {
        if ($loan->status !== LoanStatus::Pending) {
            throw new InvalidArgumentException('Offers can only be generated for pending loans.');
        }

        if (in_array($loan->application_status, [ApplicationStatus::Rejected, ApplicationStatus::Cancelled, ApplicationStatus::Accepted], true)) {
            throw new InvalidArgumentException('An offer can only be generated before the borrower accepts the application.');
        }
    }

    /**
     * @return array{emi: string, total_interest: string, total_repayment: string, installments: int}
     */
    private function summaries(LoanCalculation $calculation, string $totalInterest, string $totalPayable): array
    {
        return [
            'emi' => (string) $calculation->emiAmount,
            'total_interest' => $totalInterest,
            'total_repayment' => $totalPayable,
            'installments' => count($calculation->installments),
        ];
    }
}
