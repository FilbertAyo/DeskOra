<?php

namespace App\Policies;

use App\Models\InventoryVerificationSession;
use App\Models\User;

class InventoryVerificationSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view inventory') || $user->can('manage inventory assets');
    }

    public function view(User $user, InventoryVerificationSession $session): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('manage inventory assets');
    }

    public function update(User $user, InventoryVerificationSession $session): bool
    {
        return $user->can('manage inventory assets') && $session->isOpen();
    }

    public function complete(User $user, InventoryVerificationSession $session): bool
    {
        return $this->update($user, $session);
    }
}
