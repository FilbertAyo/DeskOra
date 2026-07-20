<?php

namespace App\Notifications;

use App\Models\MeetingMinute;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingMinutesUploadedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly MeetingMinute $meetingMinute,
        private readonly User $uploadedBy,
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
        $periodLabel = $this->periodLabel();
        $uploaderLabel = $this->uploaderLabel();

        return (new MailMessage)
            ->subject('Meeting Minutes Available — '.$periodLabel)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($uploaderLabel.' uploaded the meeting minutes for '.$periodLabel.'.')
            ->line('You can read the minutes directly in the portal.')
            ->action('Open Minutes', route('meeting-minutes.show', $this->meetingMinute))
            ->line('Thank you.');
    }

    public function toArray(object $notifiable): array
    {
        $periodLabel = $this->periodLabel();
        $uploaderLabel = $this->uploaderLabel();

        return [
            'title' => 'Meeting minutes available',
            'message' => $uploaderLabel.' uploaded meeting minutes for '.$periodLabel.'.',
            'action_url' => route('meeting-minutes.show', $this->meetingMinute),
            'period_label' => $periodLabel,
            'uploaded_by_name' => $this->uploadedBy->name,
        ];
    }

    private function periodLabel(): string
    {
        $period = $this->meetingMinute->period;

        if (! $period) {
            return 'the latest work period';
        }

        return 'Week '.$period->week_number.' of '.$period->year;
    }

    private function uploaderLabel(): string
    {
        $departmentName = $this->uploadedBy->department?->name;

        return $departmentName
            ? $this->uploadedBy->name.' ('.$departmentName.')'
            : $this->uploadedBy->name;
    }
}
