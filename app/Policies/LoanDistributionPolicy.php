<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Loan;
use App\Models\User;

class LoanDistributionPolicy
{
    public function view(User $user, Loan $loan): bool
    {
        return true;
    }

    public function update(User $user, Loan $loan): bool
    {
        return $user->isSuperAdmin();
    }

    public function viewGuarantors(User $user, Loan $loan): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::SuperAdmin], true);
    }

    public function manageGuarantors(User $user, Loan $loan): bool
    {
        return $loan->guarantorsEditable()
            && in_array($user->role, [UserRole::Admin, UserRole::SuperAdmin], true);
    }
}
