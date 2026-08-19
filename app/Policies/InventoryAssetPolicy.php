<?php

namespace App\Policies;

use App\Models\InventoryAsset;
use App\Models\User;

class InventoryAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view inventory') || $user->can('manage inventory assets');
    }

    public function view(User $user, InventoryAsset $asset): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('manage inventory assets');
    }

    public function update(User $user, InventoryAsset $asset): bool
    {
        return $user->can('manage inventory assets') && ! $asset->isDisposed();
    }

    public function assign(User $user, InventoryAsset $asset): bool
    {
        return $user->can('manage inventory assets') && ! $asset->isDisposed();
    }

    public function transfer(User $user, InventoryAsset $asset): bool
    {
        return $user->can('manage inventory transfers') && ! $asset->isDisposed();
    }

    public function reportProblem(User $user, InventoryAsset $asset): bool
    {
        return $user->can('manage inventory maintenance') && ! $asset->isDisposed();
    }

    public function requestDisposal(User $user, InventoryAsset $asset): bool
    {
        return $user->can('manage inventory assets') && ! $asset->isDisposed();
    }
}
