<?php

namespace App\Services;

use App\Data\WeeklyBudgetMailContext;
use App\Models\Document;
use App\Models\User;
use App\Notifications\WeeklyBudgetWorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class WeeklyBudgetNotificationService
{
    public function notifyStageApprovers(
        Document $document,
        string $event,
        User $actor,
        ?string $itemLabel = null,
        ?string $note = null,
    ): void {
        $config = $this->eventConfig($event);
        $permission = $config['permission'] ?? null;
        if (! is_string($permission) || $permission === '') {
            return;
        }

        $context = $this->buildContext($document, $event, $actor, $itemLabel, $note);
        $recipients = $this->recipientsForPermission($permission)
            ->reject(fn (User $user) => (string) $user->id === (string) $actor->id)
            ->values();

        if ($recipients->isEmpty()) {
            $this->logMissingRecipients($event, $document, $permission);

            return;
        }

        $this->sendToRecipients($recipients, $context, (string) ($config['sms'] ?? ''));
    }

    public function notifyOwner(
        Document $document,
        string $event,
        User $actor,
        ?string $itemLabel = null,
        ?string $note = null,
    ): void {
        $document->loadMissing('user');
        $owner = $document->user;
        if (! $owner instanceof User) {
            return;
        }

        $config = $this->eventConfig($event);
        $context = $this->buildContext($document, $event, $actor, $itemLabel, $note);
        $this->sendToRecipients(collect([$owner]), $context, (string) ($config['sms'] ?? ''));
    }

    public function buildContext(
        Document $document,
        string $event,
        ?User $actor = null,
        ?string $itemLabel = null,
        ?string $note = null,
    ): WeeklyBudgetMailContext {
        $config = $this->eventConfig($event);
        $document->loadMissing(['period', 'user.department']);
        $submitter = $document->user;
        $periodLabel = $document->period
            ? 'Week '.$document->period->week_number.', '.$document->period->year
            : 'Current reporting window';

        return new WeeklyBudgetMailContext(
            event: $event,
            subject: (string) ($config['subject'] ?? 'Weekly budget update'),
            headline: (string) ($config['headline'] ?? 'Weekly budget update'),
            intro: (string) ($config['intro'] ?? ''),
            periodLabel: $periodLabel,
            submitterName: $submitter?->name ?? 'Staff',
            departmentName: $submitter?->department?->name,
            actionUrl: route('documents.weekly-budget-review', [
                'period_id' => $document->period_id,
            ]),
            actionLabel: (string) (($config['action']['label'] ?? null) ?: 'Open Weekly Budget Review'),
            view: (string) ($config['view'] ?? 'mail.budgets.stage-advanced'),
            actorName: $actor?->name,
            itemLabel: $itemLabel,
            note: filled($note) ? $note : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function eventConfig(string $event): array
    {
        return config("workflow_notifications.weekly_budget.{$event}", []);
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsForPermission(string $permission): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->permission($permission)
            ->get();
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function sendToRecipients(Collection $recipients, WeeklyBudgetMailContext $context, string $smsTemplate): void
    {
        $emailRecipients = $recipients->filter(fn (User $user) => $user->receivesEmailNotifications());

        if ($emailRecipients->isNotEmpty()) {
            try {
                Notification::send($emailRecipients, new WeeklyBudgetWorkflowNotification($context));
            } catch (\Throwable $e) {
                Log::error('WeeklyBudgetNotificationService: failed to send email', [
                    'event' => $context->event,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($smsTemplate === '') {
            return;
        }

        $smsMessage = strtr($smsTemplate, [
            ':period' => $context->periodLabel,
            ':submitter' => $context->submitterName,
            ':item' => $context->itemLabel ?? 'budget item',
        ]);

        $notifier = app(NotificationService::class);
        foreach ($recipients as $user) {
            $channel = $user->notification_channel ?: 'sms';
            if (! in_array($channel, ['sms', 'both'], true) || empty($user->phone)) {
                continue;
            }

            try {
                $notifier->sendSms($user->phone, $smsMessage);
            } catch (\Throwable $e) {
                Log::error('WeeklyBudgetNotificationService: failed to send SMS', [
                    'user_id' => $user->id,
                    'event' => $context->event,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function logMissingRecipients(string $event, Document $document, string $permission): void
    {
        if (! config('workflow_notifications.log_missing_recipients', true)) {
            return;
        }

        Log::warning('Weekly budget notification skipped: no eligible recipients.', [
            'event' => $event,
            'document_id' => $document->id,
            'permission' => $permission,
        ]);
    }
}
