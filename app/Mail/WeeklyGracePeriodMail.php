<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyGracePeriodMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $staffName,
        public readonly string $activityLabel,
        public readonly string $periodLabel,
        public readonly Carbon $graceEndsAt,
        public readonly ?string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Grace period: {$this->activityLabel} deadline extended",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.weekly-grace-period',
            with: [
                'staffName' => $this->staffName,
                'activityLabel' => $this->activityLabel,
                'periodLabel' => $this->periodLabel,
                'graceEndsAt' => $this->graceEndsAt,
                'actionUrl' => $this->actionUrl ?? route('dashboard'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
