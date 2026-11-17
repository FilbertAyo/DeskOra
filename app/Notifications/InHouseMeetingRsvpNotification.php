<?php

namespace App\Notifications;

use App\Models\InHouseMeeting;
use App\Models\InHouseMeetingParticipant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InHouseMeetingRsvpNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly InHouseMeeting $meeting,
        private readonly User $respondent,
        private readonly string $response,
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
            ->subject($this->respondent->name.' '.$this->responseLabel().' — '.$this->meeting->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->respondent->name.' '.$this->responseLabel().' the invitation to “'.$this->meeting->title.'”.')
            ->action('View meeting', route('meetings.show', $this->meeting));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Meeting RSVP',
            'message' => $this->respondent->name.' '.$this->responseLabel().' “'.$this->meeting->title.'”.',
            'action_url' => route('meetings.show', $this->meeting),
            'meeting_id' => $this->meeting->id,
            'meeting_title' => $this->meeting->title,
            'response' => $this->response,
        ];
    }

    private function responseLabel(): string
    {
        return match ($this->response) {
            InHouseMeetingParticipant::RESPONSE_ACCEPTED => 'accepted',
            InHouseMeetingParticipant::RESPONSE_DECLINED => 'declined',
            default => 'responded to',
        };
    }
}
