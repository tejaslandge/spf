<?php

namespace App\Support;

use App\Enums\ApplicationStatus;
use App\Enums\LoanFundingStatus;
use App\Enums\LoanStatus;
use App\Models\Loan;

/**
 * Builds the guided 11-step loan processing workflow shown on the loan detail page.
 *
 * Each step carries its display state (done / current / pending / failed) plus the
 * guidance the admin needs: what the step means, why it exists, what to do, which
 * documents and checklist items apply, the valid action, and what happens next.
 */
final class LoanWorkflow
{
    public const STATE_DONE = 'done';

    public const STATE_CURRENT = 'current';

    public const STATE_PENDING = 'pending';

    public const STATE_FAILED = 'failed';

    /**
     * @return array<int, array{
     *     key: string,
     *     step: int,
     *     title: string,
     *     state: string,
     *     meaning: string,
     *     why: string,
     *     todo: string,
     *     documents: array<int, string>,
     *     checklist: array<int, array{label: string, passed: bool}>,
     *     action: ?array{type: string, label: string, target?: string, route?: string},
     *     after: string,
     *     completed_at: ?string
     * }>
     */
    public static function steps(Loan $loan): array
    {
        $loan->loadMissing(['offers', 'schedules', 'payments']);

        $applicationStatus = $loan->application_status ?? ApplicationStatus::Submitted;
        $rejected = in_array($applicationStatus, [ApplicationStatus::Rejected, ApplicationStatus::Cancelled], true);
        $accepted = $applicationStatus === ApplicationStatus::Accepted;
        $latestOffer = $loan->offers->first();
        $hasOffer = $latestOffer !== null;
        $verified = $loan->capital_verified_at !== null;
        $fullyFunded = $loan->funding_status === LoanFundingStatus::FullyFunded;
        $approved = $loan->final_approved_at !== null;
        $disbursed = $loan->disbursement_date !== null && $loan->status !== LoanStatus::Pending;
        $active = $loan->status === LoanStatus::Active;
        $closed = $loan->status === LoanStatus::Closed;
        $allPaid = $loan->schedules->isNotEmpty() && $loan->schedules->every(fn ($s) => in_array($s->status, ['paid', 'waived'], true));

        $steps = [
            [
                'key' => 'application',
                'title' => 'Loan Application',
                'state' => $rejected ? self::STATE_FAILED : ($applicationStatus === ApplicationStatus::Submitted ? self::STATE_CURRENT : self::STATE_DONE),
                'meaning' => 'The borrower\'s request is recorded with the requested amount, tenure, purpose and income details.',
                'why' => 'Creates the base record the company assesses, funds and lends against.',
                'todo' => $rejected
                    ? 'This application was rejected. Review the reason in the notes and either fix the issues and create a new application or close it out.'
                    : ($applicationStatus === ApplicationStatus::Submitted
                        ? 'Review the requested amount, tenure, purpose and income details against the borrower\'s KYC and income proof, then mark the application under review.'
                        : 'The application has been reviewed and is progressing through the offer stage.'),
                'documents' => ['Borrower KYC documents', 'Income / employment proof', 'Address proof'],
                'checklist' => [
                    ['label' => 'Borrower identity and KYC complete', 'passed' => $loan->borrower->kycDocuments()->exists()],
                    ['label' => 'Requested amount and tenure recorded', 'passed' => true],
                    ['label' => 'Purpose and income details recorded', 'passed' => filled($loan->purpose) || filled($loan->monthly_income)],
                ],
                'action' => $applicationStatus === ApplicationStatus::Submitted
                    ? ['type' => 'livewire', 'label' => 'Review application', 'target' => 'reviewApplication']
                    : null,
                'after' => 'The application moves to review and becomes ready for offer generation.',
                'completed_at' => $applicationStatus === ApplicationStatus::Submitted ? null : null,
            ],
            [
                'key' => 'offer',
                'title' => 'Loan Offer',
                'state' => $rejected ? self::STATE_PENDING : ($hasOffer || $accepted ? self::STATE_DONE : ($applicationStatus === ApplicationStatus::Submitted ? self::STATE_PENDING : self::STATE_CURRENT)),
                'meaning' => 'Flat and reducing balance options are calculated side by side and a formal offer is issued.',
                'why' => 'Both parties must agree the exact interest method, rate and instalment amount before any capital is committed.',
                'todo' => 'Compare the flat and reducing repayment options, set any processing fee or charges, and generate the offer for the borrower.',
                'documents' => ['Approved interest rate card / terms', 'Processing fee schedule'],
                'checklist' => [
                    ['label' => 'Interest method chosen (flat or reducing)', 'passed' => $hasOffer],
                    ['label' => 'Rate, tenure and instalment amount calculated', 'passed' => $hasOffer],
                    ['label' => 'Fees and charges stated in the offer', 'passed' => $hasOffer],
                ],
                'action' => ['type' => 'route', 'label' => $hasOffer && ! $accepted ? 'Review offer' : 'Open loan calculator', 'route' => 'loans.calculator'],
                'after' => 'The issued offer appears for the borrower to review and accept.',
                'completed_at' => $hasOffer ? $latestOffer->offered_at?->format('d M Y, H:i') : null,
            ],
            [
                'key' => 'acceptance',
                'title' => 'Borrower Acceptance',
                'state' => $rejected ? self::STATE_PENDING : ($accepted ? self::STATE_DONE : ($hasOffer ? self::STATE_CURRENT : self::STATE_PENDING)),
                'meaning' => 'The borrower accepts the proposed terms and the offer is frozen as an immutable snapshot.',
                'why' => 'Future rate changes must never alter an already-agreed loan, and acceptance unlocks capital verification.',
                'todo' => $hasOffer && ! $accepted
                    ? 'Confirm the borrower has accepted the offer, record the acceptance, and lock the terms snapshot.'
                    : 'Waiting for an offer to be generated first.',
                'documents' => ['Borrower acceptance confirmation', 'Signed loan agreement'],
                'checklist' => [
                    ['label' => 'Offer issued to borrower', 'passed' => $hasOffer],
                    ['label' => 'Borrower acceptance recorded', 'passed' => $accepted],
                    ['label' => 'Terms snapshot stored', 'passed' => $accepted],
                ],
                'action' => $hasOffer && ! $accepted ? ['type' => 'route', 'label' => 'Record acceptance', 'route' => 'loans.calculator'] : null,
                'after' => 'Accepted terms are locked and the loan moves to investor capital verification.',
                'completed_at' => $accepted ? $latestOffer?->accepted_at?->format('d M Y, H:i') : null,
            ],
            [
                'key' => 'capital_verification',
                'title' => 'Investor Capital Verification',
                'state' => $rejected ? self::STATE_PENDING : ($verified ? self::STATE_DONE : ($accepted ? self::STATE_CURRENT : self::STATE_PENDING)),
                'meaning' => 'Actual cleared investor deposits are measured against the approved loan amount.',
                'why' => 'The company must never lend against assumed or unconfirmed capital — pending deposits do not count.',
                'todo' => 'Run the verification to see each investor\'s cleared deposits, active allocations and available capital, and confirm the total covers the approved amount.',
                'documents' => ['Cleared deposit records', 'Bank statements of cleared funds'],
                'checklist' => [
                    ['label' => 'Only cleared deposits counted', 'passed' => $verified],
                    ['label' => 'Total available capital ≥ approved amount', 'passed' => $verified],
                    ['label' => 'Investor accounts active and verified', 'passed' => $verified],
                ],
                'action' => $accepted && ! $verified ? ['type' => 'route', 'label' => 'Verify investor capital', 'route' => 'loans.capital-verification'] : null,
                'after' => 'Eligible investors are listed with their available capital for allocation.',
                'completed_at' => $loan->capital_verified_at?->format('d M Y, H:i'),
            ],
            [
                'key' => 'capital_allocation',
                'title' => 'Capital Allocation',
                'state' => $rejected ? self::STATE_PENDING : ($fullyFunded ? self::STATE_DONE : ($verified ? self::STATE_CURRENT : self::STATE_PENDING)),
                'meaning' => 'The admin selects investors and reserves exact amounts from their available capital.',
                'why' => 'Capital must be reserved against this loan only, never more than each investor holds, and never short of the approved amount.',
                'todo' => 'Enter each investor\'s contribution so the total exactly matches the approved amount and reserve the capital.',
                'documents' => ['Investor capital accounts', 'Allocation mandate / instruction'],
                'checklist' => [
                    ['label' => 'No investor allocated above available capital', 'passed' => $fullyFunded],
                    ['label' => 'Total allocation equals approved amount exactly', 'passed' => $fullyFunded],
                    ['label' => 'Capital reserved against this loan only', 'passed' => $fullyFunded],
                ],
                'action' => $verified && ! $fullyFunded ? ['type' => 'route', 'label' => 'Allocate capital', 'route' => 'loans.capital-allocation'] : null,
                'after' => 'Reaching 100% locks funding and generates the loan-wise investor allocation records.',
                'completed_at' => $loan->funding_locked_at?->format('d M Y, H:i'),
            ],
            [
                'key' => 'funding_confirmation',
                'title' => 'Funding Confirmation',
                'state' => $rejected ? self::STATE_PENDING : ($fullyFunded && $loan->funding_locked_at !== null ? self::STATE_DONE : ($fullyFunded ? self::STATE_CURRENT : self::STATE_PENDING)),
                'meaning' => 'The system confirms the exact approved amount is committed and locks further funding changes.',
                'why' => 'A partially funded loan must never be disbursed, and commitments cannot drift after confirmation.',
                'todo' => 'Confirm the allocation summary: approved amount, total committed and the participating investors. This step completes automatically when allocation reaches 100%.',
                'documents' => ['Funding summary', 'Investor allocation statement'],
                'checklist' => [
                    ['label' => 'Total commitments equal approved amount', 'passed' => $fullyFunded],
                    ['label' => 'Funding locked', 'passed' => $loan->funding_locked_at !== null],
                    ['label' => 'Allocation records generated', 'passed' => $loan->funding_locked_at !== null],
                ],
                'action' => null,
                'after' => 'The loan becomes eligible for final disbursement approval.',
                'completed_at' => $loan->funding_locked_at?->format('d M Y, H:i'),
            ],
            [
                'key' => 'final_approval',
                'title' => 'Final Approval',
                'state' => $rejected ? self::STATE_PENDING : ($approved ? self::STATE_DONE : ($loan->funding_locked_at !== null ? self::STATE_CURRENT : self::STATE_PENDING)),
                'meaning' => 'An authorised admin reviews the funded loan, borrower details and agreement, and authorises disbursement.',
                'why' => 'Disbursement must be an explicit, audited human decision — never a side effect of funding.',
                'todo' => 'Verify borrower and investor details, confirm the loan agreement and bank account, then record final disbursement approval.',
                'documents' => ['Loan agreement', 'Borrower bank account proof', 'Funding allocation summary'],
                'checklist' => [
                    ['label' => 'Borrower and investor details verified', 'passed' => $approved],
                    ['label' => 'Loan agreement completed', 'passed' => $approved],
                    ['label' => 'Disbursement account confirmed', 'passed' => $approved],
                ],
                'action' => $loan->funding_locked_at !== null && ! $approved ? ['type' => 'route', 'label' => 'Approve disbursement', 'route' => 'loans.disbursement'] : null,
                'after' => 'Approval unlocks the disbursement action on the disbursement page.',
                'completed_at' => $loan->final_approved_at?->format('d M Y, H:i'),
            ],
            [
                'key' => 'disbursement',
                'title' => 'Disbursement',
                'state' => $rejected ? self::STATE_PENDING : ($disbursed ? self::STATE_DONE : ($approved ? self::STATE_CURRENT : self::STATE_PENDING)),
                'meaning' => 'The actual payment is executed to the borrower\'s bank account and recorded with its reference.',
                'why' => 'The loan is only truly disbursed after a successful, referenced payment — not when investors commit capital.',
                'todo' => 'Execute the payment, then record the disbursement date, amount and payment reference.',
                'documents' => ['Payment reference / UTR', 'Disbursement approval'],
                'checklist' => [
                    ['label' => 'Final approval recorded', 'passed' => $approved],
                    ['label' => 'Payment executed to borrower account', 'passed' => $disbursed],
                    ['label' => 'Transaction reference stored', 'passed' => $disbursed && filled($loan->disbursement_reference)],
                ],
                'action' => $approved && ! $disbursed ? ['type' => 'route', 'label' => 'Record disbursement', 'route' => 'loans.disbursement'] : null,
                'after' => 'The loan activates and EMI collection begins.',
                'completed_at' => $loan->disbursement_date?->format('d M Y'),
            ],
            [
                'key' => 'emi_collection',
                'title' => 'EMI Collection',
                'state' => $rejected ? self::STATE_PENDING : ($disbursed ? ($allPaid ? self::STATE_DONE : self::STATE_CURRENT) : self::STATE_PENDING),
                'meaning' => 'Borrower EMIs are collected against the generated schedule — full, partial or late payments.',
                'why' => 'Collections are the source of every rupee of investor return; nothing else may substitute for them.',
                'todo' => 'Track due instalments, collect payments with references, and follow up on overdue dues.',
                'documents' => ['Payment receipts', 'EMI schedule'],
                'checklist' => [
                    ['label' => 'EMI schedule generated at disbursement', 'passed' => $disbursed],
                    ['label' => 'Payments recorded against instalments', 'passed' => $loan->payments->isNotEmpty()],
                    ['label' => 'All instalments settled', 'passed' => $allPaid],
                ],
                'action' => $disbursed && ! $allPaid ? ['type' => 'collect', 'label' => 'Record EMI payment'] : null,
                'after' => 'Once every instalment is settled the loan moves to reconciliation.',
                'completed_at' => $allPaid && $disbursed ? now()->format('d M Y') : null,
            ],
            [
                'key' => 'reconciliation',
                'title' => 'Reconciliation',
                'state' => $rejected ? self::STATE_PENDING : ($disbursed && $allPaid ? self::STATE_DONE : self::STATE_PENDING),
                'meaning' => 'Actual collections are matched to investor allocations — principal recovered and real interest distributed.',
                'why' => 'Investors are paid only from interest actually collected, never from hypothetical or scheduled interest.',
                'todo' => 'Verify principal and interest collected against allocations and confirm investor-wise settlement amounts.',
                'documents' => ['Collection statement', 'Investor allocation statement'],
                'checklist' => [
                    ['label' => 'All borrower dues cleared', 'passed' => $disbursed && $allPaid],
                    ['label' => 'Investor principal exposure reconciled', 'passed' => $disbursed && $allPaid],
                    ['label' => 'Interest distribution limited to collected interest', 'passed' => $disbursed && $allPaid],
                ],
                'action' => ['type' => 'route', 'label' => 'Review collections', 'route' => 'collections.index'],
                'after' => 'Once dues and settlements are clean the loan can be closed.',
                'completed_at' => $disbursed && $allPaid ? now()->format('d M Y') : null,
            ],
            [
                'key' => 'closure',
                'title' => 'Loan Closure',
                'state' => $rejected ? self::STATE_PENDING : ($closed ? self::STATE_DONE : ($disbursed && $allPaid ? self::STATE_CURRENT : self::STATE_PENDING)),
                'meaning' => 'All obligations are settled, investor exposure is released and the loan is formally closed.',
                'why' => 'Closure produces the final statements and ends the loan\'s lifecycle with a complete audit trail.',
                'todo' => 'Confirm zero outstanding balances, generate final statements and close the loan.',
                'documents' => ['Final loan statement', 'Investor settlement statements'],
                'checklist' => [
                    ['label' => 'Outstanding principal is zero', 'passed' => Money::compare($loan->outstanding_principal, '0') <= 0],
                    ['label' => 'All EMIs paid or waived', 'passed' => $allPaid],
                    ['label' => 'Investor exposure settled', 'passed' => $closed],
                ],
                'action' => $disbursed && $allPaid && ! $closed ? ['type' => 'livewire', 'label' => 'Close loan', 'target' => 'close'] : null,
                'after' => 'The loan is archived as closed with full transaction history preserved.',
                'completed_at' => $loan->closed_at?->format('d M Y, H:i'),
            ],
        ];

        // The first unfinished step is the current step (a failed step keeps its failed state).
        foreach ($steps as $index => $step) {
            if ($step['state'] !== self::STATE_DONE) {
                if ($step['state'] !== self::STATE_FAILED) {
                    $steps[$index]['state'] = self::STATE_CURRENT;
                }

                break;
            }
        }

        return $steps;
    }

    /**
     * @param  array<int, array{state: string}>  $steps
     * @return array<int, array{state: string}>|null
     */
    public static function current(array $steps): ?array
    {
        foreach ($steps as $step) {
            if ($step['state'] !== self::STATE_DONE) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{state: string}>  $steps
     * @return array{done: int, total: int, percent: float}
     */
    public static function summary(array $steps): array
    {
        $done = count(array_filter($steps, fn (array $step): bool => $step['state'] === self::STATE_DONE));
        $total = count($steps);

        return [
            'done' => $done,
            'total' => $total,
            'percent' => $total > 0 ? round(($done / $total) * 100, 1) : 0.0,
        ];
    }
}
