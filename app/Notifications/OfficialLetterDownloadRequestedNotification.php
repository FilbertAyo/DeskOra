<?php

namespace App\Notifications;

use App\Models\OfficialLetter;
use App\Models\OfficialLetterDownloadRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfficialLetterDownloadRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly OfficialLetter $letter,
        private readonly User $requestedBy,
        private readonly OfficialLetterDownloadRequest $downloadRequest,
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
        $mail = (new MailMessage)
            ->subject('Official letter download request — '.$this->letter->reference_number)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->requestedBy->name.' requested permission to download an official letter.')
            ->line('Reference: '.$this->letter->reference_number)
            ->when(filled($this->letter->name), fn (MailMessage $mail) => $mail->line('Name: '.$this->letter->name))
            ->line('Subject: '.$this->letter->subject);

        $note = trim((string) $this->downloadRequest->request_note);
        if ($note !== '') {
            $mail->line('Note: '.$note);
        }

        return $mail
            ->action('Review Letter', route('official-letters.show', $this->letter))
            ->line('Open the Official Letters panel to authorize or reject this download.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Official letter download requested',
            'message' => $this->requestedBy->name.' requested download authorization for '.$this->letter->reference_number.'.',
            'action_url' => route('official-letters.show', $this->letter),
            'reference_number' => $this->letter->reference_number,
            'requested_by_name' => $this->requestedBy->name,
        ];
    }
}
