<?php

namespace App\Policies;

use App\Models\InventoryDispatch;
use App\Models\User;

class InventoryDispatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view inventory') || $user->can('manage inventory dispatches');
    }

    public function view(User $user, InventoryDispatch $dispatch): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('manage inventory dispatches');
    }

    public function update(User $user, InventoryDispatch $dispatch): bool
    {
        return $user->can('manage inventory dispatches') && $dispatch->isEditable();
    }

    public function delete(User $user, InventoryDispatch $dispatch): bool
    {
        return $user->can('manage inventory dispatches') && $dispatch->status === InventoryDispatch::STATUS_DRAFT;
    }

    public function confirm(User $user, InventoryDispatch $dispatch): bool
    {
        return $user->can('manage inventory dispatches') && $dispatch->canConfirm();
    }

    public function returnItems(User $user, InventoryDispatch $dispatch): bool
    {
        return $user->can('manage inventory dispatches')
            && ($dispatch->canStartReturn() || $dispatch->canRecordReturn());
    }

    public function close(User $user, InventoryDispatch $dispatch): bool
    {
        return $user->can('manage inventory dispatches') && $dispatch->canClose();
    }
}
