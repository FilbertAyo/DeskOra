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

        return (new MailMessage)
            ->subject('Weekly Plan Budget Submitted')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->submittedBy->name.' submitted a weekly plan with budget items requiring review.')
            ->line('Period: '.$periodLabel)
            ->line('Budget items: '.count($this->document->budgetItems()))
            ->action('Open Weekly Budget Review', route('documents.weekly-budget-review', [
                'period_id' => $this->document->period_id,
            ]))
            ->line('You are receiving this because you can process weekly budgets.');
    }
}
