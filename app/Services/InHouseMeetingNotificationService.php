<?php

namespace App\Services;

use App\Models\InHouseMeeting;
use App\Models\InHouseMeetingParticipant;
use App\Models\User;
use App\Notifications\InHouseMeetingCancelledNotification;
use App\Notifications\InHouseMeetingInvitationNotification;
use App\Notifications\InHouseMeetingRsvpNotification;
use App\Notifications\InHouseMeetingUpdatedNotification;
use Illuminate\Support\Collection;

class InHouseMeetingNotificationService
{
    /**
     * @param  Collection<int, User>|iterable<int, User>  $recipients
     */
    public function notifyInvited(InHouseMeeting $meeting, User $organizer, iterable $recipients): void
    {
        foreach ($recipients as $recipient) {
            if ((string) $recipient->id === (string) $organizer->id) {
                continue;
            }

            $recipient->notify(new InHouseMeetingInvitationNotification($meeting, $organizer));
        }
    }

    /**
     * @param  Collection<int, User>|iterable<int, User>  $recipients
     */
    public function notifyUpdated(InHouseMeeting $meeting, User $actor, iterable $recipients, string $changeSummary = 'updated'): void
    {
        foreach ($recipients as $recipient) {
            if ((string) $recipient->id === (string) $actor->id) {
                continue;
            }

            $recipient->notify(new InHouseMeetingUpdatedNotification($meeting, $actor, $changeSummary));
        }
    }

    /**
     * @param  Collection<int, User>|iterable<int, User>  $recipients
     */
    public function notifyCancelled(InHouseMeeting $meeting, User $actor, iterable $recipients): void
    {
        foreach ($recipients as $recipient) {
            if ((string) $recipient->id === (string) $actor->id) {
                continue;
            }

            $recipient->notify(new InHouseMeetingCancelledNotification($meeting, $actor));
        }
    }

    public function notifyRsvp(InHouseMeeting $meeting, User $respondent, string $response): void
    {
        $organizer = $meeting->organizer;

        if (! $organizer || (string) $organizer->id === (string) $respondent->id) {
            return;
        }

        if (! in_array($response, [
            InHouseMeetingParticipant::RESPONSE_ACCEPTED,
            InHouseMeetingParticipant::RESPONSE_DECLINED,
        ], true)) {
            return;
        }

        $organizer->notify(new InHouseMeetingRsvpNotification($meeting, $respondent, $response));
    }
}
