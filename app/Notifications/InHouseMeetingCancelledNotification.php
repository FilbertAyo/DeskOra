<?php

namespace App\Notifications;

use App\Models\InHouseMeeting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InHouseMeetingCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly InHouseMeeting $meeting,
        private readonly User $actor,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->canReceiveGracePeriodWorkEmail()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Meeting cancelled — '.$this->meeting->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->actor->name.' cancelled “'.$this->meeting->title.'”.')
            ->line('Originally scheduled for '.$this->meeting->starts_at->timezone(config('app.timezone'))->format('D, M j, Y · g:i A').'.')
            ->action('View meeting', route('meetings.show', $this->meeting));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Meeting cancelled',
            'message' => $this->actor->name.' cancelled “'.$this->meeting->title.'”.',
            'action_url' => route('meetings.show', $this->meeting),
            'meeting_id' => $this->meeting->id,
            'meeting_title' => $this->meeting->title,
        ];
    }
}
