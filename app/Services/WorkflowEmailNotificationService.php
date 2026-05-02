<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Notifications\WeeklyActionLogSubmittedNotification;
use App\Notifications\WeeklyBudgetSubmissionNotification;
use App\Notifications\WeeklyDocumentReturnedToDraftNotification;
use App\Notifications\WeeklyDocumentSubmittedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

class WorkflowEmailNotificationService
{
    /**
     * Notify approvers when a weekly plan/report is submitted.
     */
    public function notifyWeeklyDocumentSubmitted(Document $document, User $submittedBy): void
    {
        if (! in_array($document->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true) || ! $document->isSubmitted()) {
            return;
        }

        $recipients = $this->recipientsForPermission('approve documents')
            ->reject(fn (User $user) => (string) $user->id === (string) $submittedBy->id)
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new WeeklyDocumentSubmittedNotification($document, $submittedBy));
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
        if (! $owner instanceof User || ! $this->userReceivesEmailNotifications($owner)) {
            return;
        }

        $owner->notify(new WeeklyDocumentReturnedToDraftNotification($document, $reviewer, $reason));
    }

    /**
     * Notify budget reviewers when a weekly plan containing budget rows is submitted.
     */
    public function notifyWeeklyBudgetSubmitted(Document $document, User $submittedBy): void
    {
        if ($document->type !== Document::TYPE_WEEKLY_PLAN || ! $document->isSubmitted() || ! $document->hasBudgetItems()) {
            return;
        }

        $recipients = $this->recipientsForPermission('process weekly budgets')
            ->reject(fn (User $user) => (string) $user->id === (string) $submittedBy->id)
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new WeeklyBudgetSubmissionNotification($document, $submittedBy));
    }

    /**
     * Notify all active users with email/both channel when weekly action log is submitted.
     */
    public function notifyWeeklyActionLogSubmitted(Document $actionLog, User $submittedBy): void
    {
        if ($actionLog->type !== Document::TYPE_ACTION_LOG || ! $actionLog->isSubmitted()) {
            return;
        }

        $actionLog->loadMissing('period');
        $recipients = $this->eligibleEmailRecipients()->get();
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new WeeklyActionLogSubmittedNotification($actionLog, $submittedBy));
    }

    /**
     * @return Builder<User>
     */
    private function eligibleEmailRecipients(): Builder
    {
        return User::query()
            ->where('status', 'active')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereIn('notification_channel', ['email', 'both']);
    }

    private function userReceivesEmailNotifications(User $user): bool
    {
        return $user->status === 'active'
            && filled(trim((string) $user->email))
            && in_array((string) $user->notification_channel, ['email', 'both'], true);
    }

    private function recipientsForPermission(string $permission)
    {
        return $this->eligibleEmailRecipients()
            ->permission($permission)
            ->get();
    }
}
