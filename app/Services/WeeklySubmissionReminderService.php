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
    public function __construct(
        private readonly WorkPeriodService $workPeriods,
        private readonly WeeklySubmissionWindowService $submissionWindows,
    ) {}

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

        if ($canPlan) {
            $planSlot = $this->submissionWindows->resolvePlanSlot($user);
            if ($planSlot->showAction()) {
                $planSegment = new WeeklyReminderSegment(
                    $planSlot->period,
                    $planSlot->document,
                    false,
                    true,
                );
            } else {
                foreach ($this->submissionWindows->planCandidatePeriods($current, $next) as $period) {
                    if ($period->isOpen()) {
                        continue;
                    }
                    $doc = $this->findWeeklyDocument($user, $period, Document::TYPE_WEEKLY_PLAN);
                    if (! $doc || ! $doc->isSubmitted()) {
                        $planSegment = $this->buildPlanSegment($period, $doc, $missedMessages);

                        break;
                    }
                }
            }
        }

        if ($canReport) {
            $reportSlot = $this->submissionWindows->resolveReportSlot($user);
            if ($reportSlot->showAction()) {
                $reportSegment = new WeeklyReminderSegment(
                    $reportSlot->period,
                    $reportSlot->document,
                    false,
                    true,
                );
            } else {
                foreach ($this->submissionWindows->reportCandidatePeriods($current) as $period) {
                    if ($period->isOpen()) {
                        continue;
                    }
                    $doc = $this->findWeeklyDocument($user, $period, Document::TYPE_WEEKLY_REPORT);
                    if (! $doc || ! $doc->isSubmitted()) {
                        $reportSegment = $this->buildReportSegment($period, $doc, $missedMessages);

                        break;
                    }
                }
            }
        }

        // Floating toast only when staff can still act (before deadline or returned for revision).
        $showToast = ($planSegment?->showCta ?? false) || ($reportSegment?->showCta ?? false);

        return new WeeklySubmissionReminder($showToast, $planSegment, $reportSegment, $missedMessages);
    }

    /**
     * Block creating any new weekly plan/report row when the work period is not open.
     */
    public function blocksNewWeeklyDocument(string $type, WorkPeriod $period): bool
    {
        if (! in_array($type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true)) {
            return false;
        }

        if ($type === Document::TYPE_WEEKLY_PLAN) {
            return ! $period->isOpen() && ! $period->isPlanGracePeriodActive();
        }

        return ! $period->isOpen() && ! $period->isReportGracePeriodActive();
    }

    /**
     * Whether submitting (draft → submitted) is allowed for this weekly document.
     * Submission is allowed while the document's work period is open (calendar deadlines are informational only).
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

        if ($document->type === Document::TYPE_WEEKLY_PLAN) {
            return $period->isOpen() || $period->isPlanGracePeriodActive();
        }

        if ($document->type === Document::TYPE_WEEKLY_REPORT) {
            return $period->isOpen() || $period->isReportGracePeriodActive();
        }

        return $period->isOpen();
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

        if ($next->isOpen()) {
            return new WeeklyReminderSegment($next, $doc, false, true);
        }

        $missedMessages[] = sprintf(
            'Weekly plan for Week %d, %d was not submitted before this work period closed.',
            $next->week_number,
            $next->year
        );

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

        if ($current->isOpen()) {
            return new WeeklyReminderSegment($current, $doc, false, true);
        }

        $missedMessages[] = sprintf(
            'Weekly report for Week %d, %d was not submitted before this work period closed.',
            $current->week_number,
            $current->year
        );

        return new WeeklyReminderSegment($current, $doc, true, false);
    }
}
