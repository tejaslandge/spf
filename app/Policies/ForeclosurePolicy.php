<?php

namespace App\Policies;

use App\Enums\LoanStatus;
use App\Models\ForeclosureRequest;
use App\Models\Loan;
use App\Models\User;

class ForeclosurePolicy
{
    /**
     * Determine whether the user can view the foreclose option.
     */
    public function viewForecloseOption(User $user, Loan $loan): bool
    {
        return $loan->status === LoanStatus::Active
            && $loan->latestForeclosureRequest()?->approval_status !== 'approved';
    }

    /**
     * Determine whether the user can request foreclosure.
     */
    public function requestForeclosure(User $user, Loan $loan): bool
    {
        return $this->viewForecloseOption($user, $loan);
    }

    /**
     * Determine whether the user can view foreclosure request details.
     */
    public function viewRequest(User $user, ForeclosureRequest $request): bool
    {
        return $this->manageForeclosure($user);
    }

    /**
     * Determine whether the user can approve a foreclosure request.
     */
    public function approve(User $user, ForeclosureRequest $request): bool
    {
        return $request->approval_status === 'pending'
            && $this->manageForeclosure($user);
    }

    /**
     * Determine whether the user can reject a foreclosure request.
     */
    public function reject(User $user, ForeclosureRequest $request): bool
    {
        return $request->approval_status === 'pending'
            && $this->manageForeclosure($user);
    }

    /**
     * Determine whether the user can process a foreclosure payment.
     */
    public function processPayment(User $user, ForeclosureRequest $request): bool
    {
        return $request->approval_status === 'approved'
            && $request->payment_status === 'unpaid'
            && $this->manageForeclosure($user);
    }

    /**
     * Determine whether the user can view the foreclosure dashboard.
     */
    public function viewDashboard(User $user): bool
    {
        return $this->manageForeclosure($user);
    }

    private function manageForeclosure(User $user): bool
    {
        return $user->canManageForeclosures();
    }
}
