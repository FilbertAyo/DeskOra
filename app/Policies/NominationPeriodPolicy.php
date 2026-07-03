<?php

namespace App\Policies;

use App\Models\NominationPeriod;
use App\Models\User;

class NominationPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view appreciation nominations');
    }

    public function view(User $user, NominationPeriod $nominationPeriod): bool
    {
        return $user->can('view appreciation nominations');
    }
}
