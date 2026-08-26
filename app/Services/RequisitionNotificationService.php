<?php

namespace App\Services;

use App\Data\RequisitionMailContext;
use App\Models\Request as FinanceRequest;
use App\Models\User;
use App\Notifications\RequisitionWorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class RequisitionNotificationService
{
    public function notifyStageApprovers(FinanceRequest $request, string $event): void
    {
        if (! $this->isStaffRequisition($request)) {
            return;
        }

        $config = $this->eventConfig($event);
        $permission = $config['permission'] ?? null;
        if (! is_string($permission) || $permission === '') {
            return;
        }

        $request->loadMissing(['user', 'department', 'requisitionDetails']);
        $context = $this->buildContext($request, $event);
        $recipients = $this->recipientsForPermission($permission);

        if ($recipients->isEmpty()) {
            $this->logMissingRecipients($event, $request, $permission);

            return;
        }

        $this->sendToRecipients($recipients, $context, (string) ($config['sms'] ?? ''));
    }

    public function notifyRequester(FinanceRequest $request, string $event, ?User $actor = null, ?string $comment = null): void
    {
        if (! $this->isStaffRequisition($request)) {
            return;
        }

        $request->loadMissing(['user', 'department', 'requisitionDetails']);
        $requester = $request->user;
        if (! $requester instanceof User) {
            return;
        }

        $config = $this->eventConfig($event);
        $context = $this->buildContext($request, $event, $actor, $comment);
        $this->sendToRecipients(collect([$requester]), $context, (string) ($config['sms'] ?? ''));
    }

    public function buildContext(
        FinanceRequest $request,
        string $event,
        ?User $actor = null,
        ?string $comment = null,
    ): RequisitionMailContext {
        $config = $this->eventConfig($event);
        $actionConfig = $config['action'] ?? [];
        $requesterName = $request->user?->name ?? 'User';
        $summary = $request->request_for ?: 'Staff requisition';
        $ref = (string) ($request->request_no ?: $request->id);
        $amount = number_format((float) $request->amount, 2);

        return new RequisitionMailContext(
            event: $event,
            subject: (string) ($config['subject'] ?? 'Staff Requisition update'),
            headline: (string) ($config['headline'] ?? 'Staff Requisition update'),
            intro: (string) ($config['intro'] ?? ''),
            ref: $ref,
            requesterName: $requesterName,
            summary: $summary,
            amountFormatted: 'TZS '.$amount,
            departmentName: $request->department?->name,
            actionUrl: $this->buildActionUrl($request, $actionConfig),
            actionLabel: (string) ($actionConfig['label'] ?? 'View Requisition'),
            view: (string) ($config['view'] ?? 'mail.requisitions.submitted'),
            actorName: $actor?->name,
            comment: filled($comment) ? $comment : null,
        );
    }

    private function isStaffRequisition(FinanceRequest $request): bool
    {
        return $request->request_type === 'Staff Requisition';
    }

    /**
     * @return array<string, mixed>
     */
    private function eventConfig(string $event): array
    {
        $typeKey = config('request_notifications.request_type_keys.Staff Requisition', 'staff_requisition');

        return config("request_notifications.{$typeKey}.{$event}", []);
    }

    /**
     * @param  array<string, mixed>  $actionConfig
     */
    private function buildActionUrl(FinanceRequest $request, array $actionConfig): string
    {
        $routeName = (string) ($actionConfig['route'] ?? 'requisition.show');
        $params = ['petty' => $request];

        if (isset($actionConfig['stage']) && is_string($actionConfig['stage'])) {
            $params['stage'] = $actionConfig['stage'];
        }

        return route($routeName, $params);
    }

    /**
     * Requisition reviewer/approver/authorizer permissions are org-wide, not department-scoped.
     *
     * @return Collection<int, User>
     */
    private function recipientsForPermission(string $permission): Collection
    {
        return User::query()
            ->withoutDemoViewers()
            ->where('status', 'active')
            ->permission($permission)
            ->get();
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function sendToRecipients(Collection $recipients, RequisitionMailContext $context, string $smsTemplate): void
    {
        $emailRecipients = $recipients->filter(fn (User $user) => $user->receivesEmailNotifications());

        if ($emailRecipients->isNotEmpty()) {
            try {
                Notification::send($emailRecipients, new RequisitionWorkflowNotification($context));
            } catch (\Throwable $e) {
                Log::error('RequisitionNotificationService: failed to send email notifications', [
                    'event' => $context->event,
                    'ref' => $context->ref,
                    'recipient_ids' => $emailRecipients->pluck('id')->all(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $smsMessage = $this->buildSms($smsTemplate, $context);
        if ($smsMessage === '') {
            return;
        }

        $notifier = app(NotificationService::class);
        foreach ($recipients as $user) {
            $channel = $user->notification_channel ?: 'sms';
            if (! in_array($channel, ['sms', 'both'], true) || empty($user->phone)) {
                continue;
            }

            try {
                $notifier->sendSms($user->phone, $smsMessage);
            } catch (\Throwable $e) {
                Log::error('RequisitionNotificationService: failed to send SMS', [
                    'user_id' => $user->id,
                    'event' => $context->event,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function buildSms(string $template, RequisitionMailContext $context): string
    {
        if ($template === '') {
            return '';
        }

        return strtr($template, [
            ':ref' => $context->ref,
            ':requester' => $context->requesterName,
            ':summary' => $context->summary,
        ]);
    }

    private function logMissingRecipients(string $event, FinanceRequest $request, string $permission): void
    {
        if (! config('request_notifications.log_missing_recipients', true)) {
            return;
        }

        Log::warning('Requisition email notification skipped: no eligible recipients.', [
            'event' => $event,
            'request_id' => $request->id,
            'request_type' => $request->request_type,
            'department_id' => $request->department_id,
            'permission' => $permission,
        ]);
    }
}
