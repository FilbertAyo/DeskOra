<?php

namespace App\Support;

use App\Models\User;

class InventoryApproval
{
    public static function canReview(User $user, string $requestedByUserId): bool
    {
        if (! $user->can('approve inventory requests')) {
            return false;
        }

        if ((string) $requestedByUserId === (string) $user->id && ! $user->hasRole('superadmin')) {
            return false;
        }

        return true;
    }
}
