<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Notifications\WeeklyBudgetSubmissionNotification;
use App\Notifications\WeeklyDocumentDepartmentHeadNotification;
use App\Notifications\WeeklyDocumentReturnedToDraftNotification;
use App\Notifications\WeeklyDocumentSubmittedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class WorkflowEmailNotificationService
{
    /**
     * Notify approvers (e.g. CEO / document_approver role) when a weekly plan/report is submitted.
     */
    public function notifyWeeklyDocumentSubmitted(Document $document, User $submittedBy): void
    {
        if (! in_array($document->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true) || ! $document->isSubmitted()) {
            return;
        }

        $document->loadMissing('period', 'user');
        $submittedBy->loadMissing('department');

        $permission = (string) config('workflow_notifications.weekly_document_submitted.permission', 'approve documents');
        $recipients = $this->recipientsForPermission($permission, $submittedBy);

        if ($recipients->isEmpty()) {
            $this->logMissingRecipients('weekly_document_submitted', $document, $permission);
        } else {
            Notification::send($recipients, new WeeklyDocumentSubmittedNotification($document, $submittedBy));
        }

        $this->notifyDepartmentHeadWeeklyDocumentSubmitted($document, $submittedBy);
    }

    /**
     * Notify the parent department head when a staff member submits a weekly plan/report.
     */
    public function notifyDepartmentHeadWeeklyDocumentSubmitted(Document $document, User $submittedBy): void
    {
        if (! config('workflow_notifications.weekly_document_submitted.notify_department_head', true)) {
            return;
        }

        if (! in_array($document->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true) || ! $document->isSubmitted()) {
            return;
        }

        $document->loadMissing('period', 'user');
        $owner = $document->user;
        if (! $owner instanceof User) {
            return;
        }

        $owner->loadMissing('department.head');
        $head = $owner->department?->head;
        if (! $head instanceof User) {
            $this->logMissingDepartmentHead($document, $owner);

            return;
        }

        if ((string) $head->id === (string) $submittedBy->id) {
            return;
        }

        if (! $head->receivesEmailNotifications()) {
            return;
        }

        $head->notify(new WeeklyDocumentDepartmentHeadNotification($document, $submittedBy, $owner));
    }

    /**
     * Notify the owner when reviewer returns weekly plan/report to draft.
     */
    public function notifyWeeklyDocumentReturnedToDraft(Document $document, User $reviewer, string $reason): void
    {
        if (! in_array($document->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true)) {
            return;
        }

        $document->loadMissing('user', 'period');
        $owner = $document->user;
        if (! $owner instanceof User || ! $owner->receivesEmailNotifications()) {
            return;
        }

        $owner->notify(new WeeklyDocumentReturnedToDraftNotification($document, $reviewer, $reason));
    }

    /**
     * Notify finance processors when a weekly plan containing budget rows is submitted.
     */
    public function notifyWeeklyBudgetSubmitted(Document $document, User $submittedBy): void
    {
        if ($document->type !== Document::TYPE_WEEKLY_PLAN || ! $document->isSubmitted()) {
            return;
        }

        $document->refresh();
        $document->loadMissing('period', 'user');
        $submittedBy->loadMissing('department');

        if (! $document->hasBudgetItems()) {
            return;
        }

        $permission = (string) config('workflow_notifications.weekly_budget_submitted.permission', 'process weekly budgets');
        $recipients = $this->recipientsForPermission($permission, $submittedBy);

        if ($recipients->isEmpty()) {
            $this->logMissingRecipients('weekly_budget_submitted', $document, $permission);

            return;
        }

        Notification::send($recipients, new WeeklyBudgetSubmissionNotification($document, $submittedBy));
    }

    /**
     * @return Builder<User>
     */
    private function eligibleEmailRecipients(): Builder
    {
        return User::query()
            ->withoutDemoViewers()
            ->where('status', 'active')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereIn('notification_channel', ['email', 'both']);
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsForPermission(string $permission, User $excludeUser): Collection
    {
        return $this->eligibleEmailRecipients()
            ->permission($permission)
            ->get()
            ->reject(fn (User $user) => (string) $user->id === (string) $excludeUser->id)
            ->values();
    }

    private function logMissingRecipients(string $event, Document $document, string $permission): void
    {
        if (! config('workflow_notifications.log_missing_recipients', true)) {
            return;
        }

        Log::warning('Workflow email notification skipped: no eligible recipients.', [
            'event' => $event,
            'document_id' => $document->id,
            'document_type' => $document->type,
            'period_id' => $document->period_id,
            'permission' => $permission,
        ]);
    }

    private function logMissingDepartmentHead(Document $document, User $owner): void
    {
        if (! config('workflow_notifications.log_missing_recipients', true)) {
            return;
        }

        Log::warning('Workflow email notification skipped: no department head assigned.', [
            'event' => 'weekly_document_department_head',
            'document_id' => $document->id,
            'document_type' => $document->type,
            'period_id' => $document->period_id,
            'owner_id' => $owner->id,
            'department_id' => $owner->department_id,
        ]);
    }
}
