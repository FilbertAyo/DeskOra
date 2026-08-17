<?php

namespace App\Notifications;

use App\Models\OfficialLetter;
use App\Models\OfficialLetterDownloadRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfficialLetterDownloadDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly OfficialLetter $letter,
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
        $authorized = $this->downloadRequest->isAuthorized();
        $outcome = $authorized ? 'authorized' : 'rejected';
        $deciderName = $this->downloadRequest->decider?->name ?? 'An authorizer';

        $mail = (new MailMessage)
            ->subject('Official letter download '.$outcome.' — '.$this->letter->reference_number)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($deciderName.' '.$outcome.' your request to download '.$this->letter->reference_number.'.')
            ->line('Subject: '.$this->letter->subject);

        $note = trim((string) $this->downloadRequest->decision_note);
        if ($note !== '') {
            $mail->line('Note: '.$note);
        }

        if ($authorized) {
            $mail->line('You can now download this official letter as a PDF.');
        } else {
            $mail->line('You can request download authorization again from the Official Letters panel.');
        }

        return $mail->action('Open Letter', route('official-letters.show', $this->letter));
    }

    public function toArray(object $notifiable): array
    {
        $authorized = $this->downloadRequest->isAuthorized();
        $outcome = $authorized ? 'authorized' : 'rejected';
        $deciderName = $this->downloadRequest->decider?->name ?? 'An authorizer';

        return [
            'title' => 'Official letter download '.$outcome,
            'message' => $deciderName.' '.$outcome.' your download request for '.$this->letter->reference_number.'.',
            'action_url' => route('official-letters.show', $this->letter),
            'reference_number' => $this->letter->reference_number,
            'status' => $this->downloadRequest->status,
        ];
    }
}
