<?php

namespace App\Policies;

use App\Models\InvestorPayout;
use App\Models\User;

class InvestorPayoutPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InvestorPayout $payout): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function approve(User $user, InvestorPayout $payout): bool
    {
        return $user->isSuperAdmin();
    }

    public function reject(User $user, InvestorPayout $payout): bool
    {
        return $user->isSuperAdmin();
    }

    public function process(User $user, InvestorPayout $payout): bool
    {
        return $user->isSuperAdmin();
    }

    public function cancel(User $user, InvestorPayout $payout): bool
    {
        return $user->isSuperAdmin();
    }
}
