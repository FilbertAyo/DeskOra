<?php

namespace App\Notifications;

use App\Data\WeeklyBudgetMailContext;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklyBudgetWorkflowNotification extends Notification
{
    public function __construct(
        private readonly WeeklyBudgetMailContext $context,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->context->subject)
            ->view($this->context->view, $this->context->toViewData($notifiable->name ?? null));
    }
}
