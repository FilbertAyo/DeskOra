<?php

namespace App\Notifications;

use App\Models\OfficialLetter;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfficialLetterSharedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly OfficialLetter $letter,
        private readonly User $sharedBy,
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
            ->subject('Official Letter Shared — '.$this->letter->reference_number)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->sharedBy->name.' shared an official letter with you.')
            ->line('Reference: '.$this->letter->reference_number)
            ->when(filled($this->letter->name), fn (MailMessage $mail) => $mail->line('Name: '.$this->letter->name))
            ->line('Subject: '.$this->letter->subject)
            ->action('Open Letter', route('official-letters.show', $this->letter))
            ->line('You can view and edit this letter in the portal.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Official letter shared with you',
            'message' => $this->sharedBy->name.' shared '.$this->letter->reference_number.' with you.',
            'action_url' => route('official-letters.show', $this->letter),
            'reference_number' => $this->letter->reference_number,
            'shared_by_name' => $this->sharedBy->name,
        ];
    }
}
