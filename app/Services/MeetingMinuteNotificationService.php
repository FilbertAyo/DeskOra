<?php

namespace App\Services;

use App\Models\MeetingMinute;
use App\Models\User;
use App\Notifications\MeetingMinutesUploadedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class MeetingMinuteNotificationService
{
    public function notifyMinutesUploaded(MeetingMinute $meetingMinute, User $uploadedBy): void
    {
        $meetingMinute->loadMissing('period');
        $uploadedBy->loadMissing('department');

        $recipients = $this->activeStaffRecipients();

        if ($recipients->isEmpty()) {
            Log::warning('Meeting minutes upload notification skipped: no eligible recipients.', [
                'meeting_minute_id' => $meetingMinute->id,
                'period_id' => $meetingMinute->period_id,
            ]);

            return;
        }

        Notification::send(
            $recipients,
            new MeetingMinutesUploadedNotification($meetingMinute, $uploadedBy)
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function activeStaffRecipients(): Collection
    {
        return User::query()
            ->active()
            ->get();
    }
}
