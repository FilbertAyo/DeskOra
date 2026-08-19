<?php

namespace App\Policies;

use App\Models\InventoryBorrowRequest;
use App\Models\User;
use App\Support\InventoryApproval;

class InventoryBorrowRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view inventory')
            || $user->can('manage inventory borrowing')
            || $user->can('approve inventory requests');
    }

    public function view(User $user, InventoryBorrowRequest $borrowRequest): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('manage inventory borrowing');
    }

    public function approve(User $user, InventoryBorrowRequest $borrowRequest): bool
    {
        return InventoryApproval::canReview($user, (string) $borrowRequest->requested_by_user_id);
    }

    public function reject(User $user, InventoryBorrowRequest $borrowRequest): bool
    {
        return $this->approve($user, $borrowRequest);
    }

    public function issue(User $user, InventoryBorrowRequest $borrowRequest): bool
    {
        return $user->can('manage inventory borrowing');
    }

    public function returnItem(User $user, InventoryBorrowRequest $borrowRequest): bool
    {
        return $user->can('manage inventory borrowing');
    }
}
