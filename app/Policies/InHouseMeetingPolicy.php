<?php

namespace App\Policies;

use App\Models\InHouseMeeting;
use App\Models\User;

class InHouseMeetingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, InHouseMeeting $meeting): bool
    {
        if ($meeting->meeting_type === InHouseMeeting::TYPE_WEEKLY) {
            return true;
        }

        return $meeting->isOrganizer($user) || $meeting->isParticipant($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, InHouseMeeting $meeting): bool
    {
        return $meeting->isOrganizer($user) && ! $meeting->isCancelled();
    }

    public function cancel(User $user, InHouseMeeting $meeting): bool
    {
        return $meeting->isOrganizer($user) && $meeting->status === InHouseMeeting::STATUS_SCHEDULED;
    }

    public function complete(User $user, InHouseMeeting $meeting): bool
    {
        return $meeting->isOrganizer($user) && $meeting->status === InHouseMeeting::STATUS_SCHEDULED;
    }

    public function manageRecords(User $user, InHouseMeeting $meeting): bool
    {
        return $meeting->isOrganizer($user) && ! $meeting->isCancelled();
    }

    public function rsvp(User $user, InHouseMeeting $meeting): bool
    {
        if ($meeting->isCancelled() || $meeting->isOrganizer($user)) {
            return false;
        }

        return $meeting->isParticipant($user);
    }

    public function joinCall(User $user, InHouseMeeting $meeting): bool
    {
        if ($meeting->isCancelled()) {
            return false;
        }

        return $this->view($user, $meeting);
    }
}
