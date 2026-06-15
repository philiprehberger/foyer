<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function view(User $user, Business $business): bool
    {
        return $user->ownsBusiness($business->id);
    }

    public function update(User $user, Business $business): bool
    {
        return $user->ownsBusiness($business->id);
    }

    public function manageBookings(User $user, Business $business): bool
    {
        return $user->ownsBusiness($business->id);
    }

    public function toggleKillSwitch(User $user, Business $business): bool
    {
        return $user->ownsBusiness($business->id);
    }
}
