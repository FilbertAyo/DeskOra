<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklySubmissionReminderMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $staffName,
        public readonly string $activityLabel,
        public readonly string $periodLabel
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reminder: Submit {$this->activityLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.weekly-submission-reminder',
            with: [
                'staffName' => $this->staffName,
                'activityLabel' => $this->activityLabel,
                'periodLabel' => $this->periodLabel,
                'documentsUrl' => route('documents.create'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
