<?php

namespace App\Policies;

use App\Models\InventoryMaintenanceRecord;
use App\Models\User;

class InventoryMaintenanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view inventory') || $user->can('manage inventory maintenance');
    }

    public function view(User $user, InventoryMaintenanceRecord $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('manage inventory maintenance');
    }

    public function complete(User $user, InventoryMaintenanceRecord $record): bool
    {
        return $user->can('manage inventory maintenance') && $record->isOpen();
    }
}
