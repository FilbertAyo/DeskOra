<?php

namespace App\Policies;

use App\Models\FeatureUsageEvent;
use App\Models\User;

class FeatureUsageEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view feature usage');
    }

    public function view(User $user, FeatureUsageEvent $featureUsageEvent): bool
    {
        return $user->can('view feature usage');
    }
}
