<?php

namespace App\Notifications;

use App\Models\InHouseMeeting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InHouseMeetingInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly InHouseMeeting $meeting,
        private readonly User $organizer,
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
            ->subject('Meeting invitation — '.$this->meeting->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->organizer->name.' invited you to “'.$this->meeting->title.'”.')
            ->line($this->whenLine())
            ->action($this->actionLabel(), $this->actionUrl())
            ->line($this->isLiveSoon()
                ? 'Open the portal voice call to join.'
                : 'Please confirm whether you can attend.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->isLiveSoon() ? 'Voice call invitation' : 'Meeting invitation',
            'message' => $this->organizer->name.' invited you to “'.$this->meeting->title.'”.',
            'action_url' => $this->actionUrl(),
            'meeting_id' => $this->meeting->id,
            'meeting_title' => $this->meeting->title,
        ];
    }

    private function isLiveSoon(): bool
    {
        return $this->meeting->starts_at->lte(now()->addMinutes(5));
    }

    private function actionUrl(): string
    {
        return $this->isLiveSoon()
            ? route('meetings.call', $this->meeting)
            : route('meetings.show', $this->meeting);
    }

    private function actionLabel(): string
    {
        return $this->isLiveSoon() ? 'Join voice call' : 'View meeting';
    }

    private function whenLine(): string
    {
        return 'When: '.$this->meeting->starts_at->timezone(config('app.timezone'))->format('D, M j, Y · g:i A')
            .' – '.$this->meeting->ends_at->timezone(config('app.timezone'))->format('g:i A');
    }
}
