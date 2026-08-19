<?php

namespace App\Policies;

use App\Models\InventoryReceipt;
use App\Models\User;

class InventoryReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view inventory') || $user->can('manage inventory receipts');
    }

    public function view(User $user, InventoryReceipt $receipt): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('manage inventory receipts');
    }

    public function addItems(User $user, InventoryReceipt $receipt): bool
    {
        return $user->can('manage inventory receipts');
    }
}
