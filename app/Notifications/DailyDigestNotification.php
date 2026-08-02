<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyDigestNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly array $payload,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (method_exists($notifiable, 'receivesEmailNotifications') && $notifiable->receivesEmailNotifications()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('DeskOra daily digest')
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line('Here is what needs your attention today:');

        foreach (($this->payload['lines'] ?? []) as $line) {
            $message->line('• '.$line);
        }

        return $message
            ->action('Open dashboard', (string) ($this->payload['dashboard_url'] ?? url('/dashboard')))
            ->line('Have a productive day.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $lines = $this->payload['lines'] ?? [];

        return [
            'title' => 'Daily digest',
            'message' => implode(' ', $lines),
            'lines' => $lines,
            'url' => $this->payload['dashboard_url'] ?? route('dashboard'),
            'type' => 'daily_digest',
        ];
    }
}
