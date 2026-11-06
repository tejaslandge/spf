<?php

namespace App\Policies;

use App\Models\InterestConfiguration;
use App\Models\User;

class InterestConfigurationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InterestConfiguration $configuration): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, InterestConfiguration $configuration): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, InterestConfiguration $configuration): bool
    {
        return $user->isSuperAdmin();
    }
}
