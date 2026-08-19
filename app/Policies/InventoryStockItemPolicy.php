<?php

namespace App\Policies;

use App\Models\InventoryStockItem;
use App\Models\User;

class InventoryStockItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view inventory') || $user->can('manage inventory stock');
    }

    public function view(User $user, InventoryStockItem $stockItem): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('manage inventory stock');
    }

    public function update(User $user, InventoryStockItem $stockItem): bool
    {
        return $user->can('manage inventory stock');
    }

    public function issue(User $user, InventoryStockItem $stockItem): bool
    {
        return $user->can('manage inventory stock');
    }

    public function adjust(User $user, InventoryStockItem $stockItem): bool
    {
        return $user->can('manage inventory stock');
    }
}
