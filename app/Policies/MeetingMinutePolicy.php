<?php

namespace App\Policies;

use App\Models\MeetingMinute;
use App\Models\User;

class MeetingMinutePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MeetingMinute $meetingMinute): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('manage minutes');
    }

    public function update(User $user, MeetingMinute $meetingMinute): bool
    {
        return $user->can('manage minutes');
    }
}
