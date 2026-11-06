<?php

namespace App\Policies;

use App\Models\AdminCommissionPayout;
use App\Models\User;

class AdminCommissionPayoutPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AdminCommissionPayout $payout): bool
    {
        return true;
    }

    public function approve(User $user, AdminCommissionPayout $payout): bool
    {
        return $user->isSuperAdmin();
    }

    public function reject(User $user, AdminCommissionPayout $payout): bool
    {
        return $user->isSuperAdmin();
    }

    public function process(User $user, AdminCommissionPayout $payout): bool
    {
        return $user->isSuperAdmin();
    }

    public function cancel(User $user, AdminCommissionPayout $payout): bool
    {
        return $user->isSuperAdmin();
    }
}
