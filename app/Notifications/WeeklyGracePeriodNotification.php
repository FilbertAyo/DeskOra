<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WeeklyGracePeriodNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $activityType,
        public readonly string $activityLabel,
        public readonly string $periodLabel,
        public readonly Carbon $graceEndsAt,
        public readonly ?string $actionUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $cutoffFormatted = $this->graceEndsAt->format('M d, Y H:i');

        return [
            'activity_type' => $this->activityType,
            'activity_label' => $this->activityLabel,
            'period_label' => $this->periodLabel,
            'grace_ends_at' => $this->graceEndsAt->toIso8601String(),
            'title' => "Grace period: submit your {$this->activityLabel}",
            'message' => "The deadline for your {$this->activityLabel} ({$this->periodLabel}) was extended. Submit before {$cutoffFormatted}.",
            'action_url' => $this->actionUrl ?? route('dashboard'),
            'dispatch_type' => 'grace_period',
        ];
    }
}
