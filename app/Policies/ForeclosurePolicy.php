<?php

namespace App\Policies;

use App\Enums\UserRole;
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
            && !$loan->latestForeclosureRequest()?->approval_status === 'approved';
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
        return $user->can('manage foreclosure requests')
            || $request->borrower_id === $user->borrower->id;
    }

    /**
     * Determine whether the user can approve a foreclosure request.
     */
    public function approve(User $user, ForeclosureRequest $request): bool
    {
        return $user->isSuperAdmin()
            || ($user->isAdmin() && $request->loan->borrower_id === $user->borrower->id);
    }

    /**
     * Determine whether the user can reject a foreclosure request.
     */
    public function reject(User $user, ForeclosureRequest $request): bool
    {
        return $user->isSuperAdmin()
            || ($user->isAdmin() && $request->loan->borrower_id === $user->borrower->id);
    }

    /**
     * Determine whether the user can process foreclosure payment.
     */
    public function processPayment(User $user, ForeclosureRequest $request): bool
    {
        return $user->isSuperAdmin()
            || ($user->isAdmin() && $request->loan->borrower_id === $user->borrower->id);
    }

    /**
     * Determine whether the user can view the foreclosure dashboard.
     */
    public function viewDashboard(User $user): bool
    {
        return $user->can('manage foreclosure requests');
    }

    /**
     * Get the policy notifications for the user.
     *
     * @return array<class-string, class-string>
     */
    public function notifications(User $user): array
    {
        return [];
    }
}