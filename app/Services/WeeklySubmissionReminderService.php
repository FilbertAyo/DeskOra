<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\WeeklyReminderSegment;
use App\Data\WeeklySubmissionReminder;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;

class WeeklySubmissionReminderService
{
    public function __construct(private readonly WorkPeriodService $workPeriods) {}

    public function forUser(User $user): WeeklySubmissionReminder
    {
        $canPlan = $user->hasPermissionTo('create weekly plan');
        $canReport = $user->hasPermissionTo('create weekly report');

        if (! $canPlan && ! $canReport) {
            return new WeeklySubmissionReminder(false, null, null, []);
        }

        $current = $this->workPeriods->periodForWeeklyReportSubmission();
        $next = $this->workPeriods->periodForWeeklyPlanSubmission();

        $planSegment = null;
        $reportSegment = null;
        $missedMessages = [];

        if ($canPlan && $next) {
            $doc = $this->findWeeklyDocument($user, $next, Document::TYPE_WEEKLY_PLAN);
            $planSegment = $this->buildPlanSegment($next, $doc, $missedMessages);
        }

        if ($canReport && $current) {
            $doc = $this->findWeeklyDocument($user, $current, Document::TYPE_WEEKLY_REPORT);
            $reportSegment = $this->buildReportSegment($current, $doc, $missedMessages);
        }

        // Floating toast only when staff can still act (before deadline or returned for revision).
        $showToast = ($planSegment?->showCta ?? false) || ($reportSegment?->showCta ?? false);

        return new WeeklySubmissionReminder($showToast, $planSegment, $reportSegment, $missedMessages);
    }

    /**
     * Block creating any new weekly plan/report row when the relevant deadline has passed,
     * except when updating an existing draft via the store→update path (handled separately).
     */
    public function blocksNewWeeklyDocument(string $type, WorkPeriod $period): bool
    {
        if ($type === Document::TYPE_WEEKLY_PLAN) {
            return $period->isPlanDeadlinePassed();
        }

        if ($type === Document::TYPE_WEEKLY_REPORT) {
            return $period->isReportDeadlinePassed();
        }

        return false;
    }

    /**
     * Whether submitting (draft → submitted) is allowed for this weekly document.
     */
    public function allowsSubmitWeeklyDocument(Document $document): bool
    {
        if (! in_array($document->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true)) {
            return true;
        }

        $period = $document->period;
        if (! $period) {
            return true;
        }

        $deadlinePassed = $document->type === Document::TYPE_WEEKLY_PLAN
            ? $period->isPlanDeadlinePassed()
            : $period->isReportDeadlinePassed();

        if (! $deadlinePassed) {
            return true;
        }

        return $document->isDraft() && $document->isReturnedForRevision();
    }

    private function findWeeklyDocument(User $user, WorkPeriod $period, string $type): ?Document
    {
        $submitted = Document::query()
            ->where('user_id', $user->id)
            ->where('period_id', $period->id)
            ->where('type', $type)
            ->where('state', Document::STATE_SUBMITTED)
            ->first();

        if ($submitted) {
            return $submitted;
        }

        return Document::query()
            ->where('user_id', $user->id)
            ->where('period_id', $period->id)
            ->where('type', $type)
            ->where('state', Document::STATE_DRAFT)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  list<string>  $missedMessages
     */
    private function buildPlanSegment(WorkPeriod $next, ?Document $doc, array &$missedMessages): ?WeeklyReminderSegment
    {
        if ($doc && $doc->isSubmitted()) {
            return null;
        }

        $deadlinePassed = $next->isPlanDeadlinePassed();
        $returned = $doc && $doc->isReturnedForRevision();

        if (! $deadlinePassed) {
            return new WeeklyReminderSegment($next, $doc, false, true);
        }

        if ($returned) {
            return new WeeklyReminderSegment($next, $doc, false, true);
        }

        $missedMessages[] = 'Weekly plan for Week '.$next->week_number.', '.$next->year.' was not submitted before the plan deadline.';

        return new WeeklyReminderSegment($next, $doc, true, false);
    }

    /**
     * @param  list<string>  $missedMessages
     */
    private function buildReportSegment(WorkPeriod $current, ?Document $doc, array &$missedMessages): ?WeeklyReminderSegment
    {
        if ($doc && $doc->isSubmitted()) {
            return null;
        }

        $deadlinePassed = $current->isReportDeadlinePassed();
        $returned = $doc && $doc->isReturnedForRevision();

        if (! $deadlinePassed) {
            return new WeeklyReminderSegment($current, $doc, false, true);
        }

        if ($returned) {
            return new WeeklyReminderSegment($current, $doc, false, true);
        }

        $missedMessages[] = 'Weekly report for Week '.$current->week_number.', '.$current->year.' was not submitted before the report deadline.';

        return new WeeklyReminderSegment($current, $doc, true, false);
    }
}
