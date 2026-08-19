<?php

namespace App\Policies;

use App\Models\InventoryDisposalRequest;
use App\Models\User;
use App\Support\InventoryApproval;

class InventoryDisposalRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view inventory')
            || $user->can('manage inventory assets')
            || $user->can('approve inventory requests');
    }

    public function view(User $user, InventoryDisposalRequest $disposalRequest): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('manage inventory assets');
    }

    public function approve(User $user, InventoryDisposalRequest $disposalRequest): bool
    {
        return InventoryApproval::canReview($user, (string) $disposalRequest->requested_by_user_id);
    }

    public function reject(User $user, InventoryDisposalRequest $disposalRequest): bool
    {
        return $this->approve($user, $disposalRequest);
    }
}
