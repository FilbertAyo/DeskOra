<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WeeklyWorkLog;

class WeeklyWorkLogPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->status ?? '') === 'active';
    }

    public function view(User $user, WeeklyWorkLog $weeklyWorkLog): bool
    {
        if ((string) $weeklyWorkLog->user_id === (string) $user->id) {
            return true;
        }

        return $user->can('approve documents');
    }

    public function create(User $user): bool
    {
        return ($user->status ?? '') === 'active';
    }

    public function update(User $user, WeeklyWorkLog $weeklyWorkLog): bool
    {
        return (string) $weeklyWorkLog->user_id === (string) $user->id;
    }

    public function delete(User $user, WeeklyWorkLog $weeklyWorkLog): bool
    {
        return (string) $weeklyWorkLog->user_id === (string) $user->id;
    }

    public function createActionFromBlocker(User $user, WeeklyWorkLog $weeklyWorkLog): bool
    {
        return (string) $weeklyWorkLog->user_id === (string) $user->id
            && $weeklyWorkLog->hasBlocker();
    }
}
