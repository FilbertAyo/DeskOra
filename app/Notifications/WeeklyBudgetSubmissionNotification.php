<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklyBudgetSubmissionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Document $document,
        private readonly User $submittedBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $periodLabel = $this->document->period
            ? 'Week '.$this->document->period->week_number.', '.$this->document->period->year
            : 'Current reporting window';
        $departmentName = $this->submittedBy->department?->name;
        $submitterLabel = $departmentName
            ? $this->submittedBy->name.' ('.$departmentName.')'
            : $this->submittedBy->name;
        $budgetCount = count($this->document->budgetItems());

        $message = (new MailMessage)
            ->subject('Weekly Plan Budget Submitted')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($submitterLabel.' submitted a weekly plan with '.$budgetCount.' budget item(s) requiring finance review.')
            ->line('Period: '.$periodLabel);

        if (filled($this->document->title)) {
            $message->line('Plan title: '.$this->document->title);
        }

        return $message
            ->action('Open Weekly Budget Review', route('documents.weekly-budget-review', [
                'period_id' => $this->document->period_id,
            ]))
            ->line('You are receiving this because you review weekly plan budgets.');
    }
}
