<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\WeeklySubmissionSlot;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use Illuminate\Support\Collection;

class WeeklySubmissionWindowService
{
    public function __construct(private readonly WorkPeriodService $workPeriods) {}

    public function resolvePlanSlot(User $user): WeeklySubmissionSlot
    {
        if (! $user->hasPermissionTo('create weekly plan')) {
            return $this->emptySlot();
        }

        $current = $this->workPeriods->periodForWeeklyReportSubmission();
        $next = $this->workPeriods->periodForWeeklyPlanSubmission();

        return $this->firstActionablePlanSlot($user, $this->planCandidatePeriods($current, $next));
    }

    public function resolveReportSlot(User $user, ?WorkPeriod $preferredPeriod = null): WeeklySubmissionSlot
    {
        if (! $user->hasPermissionTo('create weekly report')) {
            return $this->emptySlot();
        }

        $current = $this->workPeriods->periodForWeeklyReportSubmission();

        $candidates = $preferredPeriod && $preferredPeriod->isOpen()
            ? collect([$preferredPeriod])
            : $this->reportCandidatePeriods($current);

        return $this->firstActionableReportSlot($user, $candidates);
    }

    /**
     * Plan slot for a period that is in an admin-extended (grace) plan window, if the user still owes that plan.
     */
    public function gracePlanSlot(User $user): WeeklySubmissionSlot
    {
        if (! $user->hasPermissionTo('create weekly plan')) {
            return $this->emptySlot();
        }

        $current = $this->workPeriods->periodForWeeklyReportSubmission();
        $next = $this->workPeriods->periodForWeeklyPlanSubmission();

        foreach ($this->planCandidatePeriods($current, $next) as $period) {
            if (! $period->isPlanGracePeriodActive()) {
                continue;
            }

            $slot = $this->planSlotForPeriod($user, $period);
            if ($slot->showAction()) {
                return $slot;
            }
        }

        return $this->emptySlot();
    }

    /**
     * Report slot for a period that is in an admin-extended (grace) report window, if the user still owes that report.
     */
    public function graceReportSlot(User $user): WeeklySubmissionSlot
    {
        if (! $user->hasPermissionTo('create weekly report')) {
            return $this->emptySlot();
        }

        $current = $this->workPeriods->periodForWeeklyReportSubmission();

        foreach ($this->reportCandidatePeriods($current) as $period) {
            if (! $period->isReportGracePeriodActive()) {
                continue;
            }

            $slot = $this->reportSlotForPeriod($user, $period);
            if ($slot->showAction()) {
                return $slot;
            }
        }

        return $this->emptySlot();
    }

    public function hasActivePlanGrace(User $user): bool
    {
        return $this->anyPlanGracePeriod($user) !== null;
    }

    public function hasActiveReportGrace(User $user): bool
    {
        return $this->anyReportGracePeriod($user) !== null;
    }

    public function activePlanGracePeriod(User $user): ?WorkPeriod
    {
        return $this->anyPlanGracePeriod($user);
    }

    public function activeReportGracePeriod(User $user): ?WorkPeriod
    {
        return $this->anyReportGracePeriod($user);
    }

    public function planGraceEndsAt(User $user): ?\Carbon\Carbon
    {
        $period = $this->anyPlanGracePeriod($user);

        return $period?->getPlanDeadlineCutoff();
    }

    public function reportGraceEndsAt(User $user): ?\Carbon\Carbon
    {
        $period = $this->anyReportGracePeriod($user);

        return $period?->getReportDeadlineCutoff();
    }

    /**
     * @return Collection<int, WorkPeriod>
     */
    public function planCandidatePeriods(?WorkPeriod $current, ?WorkPeriod $next): Collection
    {
        // Normally plans target next week; during an admin plan grace on the current week, that week comes first.
        $ordered = ($current && $current->isPlanGracePeriodActive())
            ? [$current, $next]
            : [$next, $current];

        return collect($ordered)
            ->filter()
            ->unique(fn (WorkPeriod $period) => $period->id)
            ->values();
    }

    /**
     * @return Collection<int, WorkPeriod>
     */
    public function reportCandidatePeriods(?WorkPeriod $current): Collection
    {
        $previous = $current?->getPreviousPeriod();

        $ordered = ($previous && $previous->isReportGracePeriodActive())
            ? [$previous, $current]
            : [$current, $previous];

        return collect($ordered)
            ->filter()
            ->unique(fn (WorkPeriod $period) => $period->id)
            ->values();
    }

    /**
     * @param  Collection<int, WorkPeriod>  $periods
     */
    private function firstActionablePlanSlot(User $user, Collection $periods): WeeklySubmissionSlot
    {
        foreach ($periods as $period) {
            $slot = $this->planSlotForPeriod($user, $period);
            if ($slot->showAction()) {
                return $slot;
            }
        }

        return $this->emptySlot();
    }

    /**
     * @param  Collection<int, WorkPeriod>  $periods
     */
    private function firstActionableReportSlot(User $user, Collection $periods): WeeklySubmissionSlot
    {
        foreach ($periods as $period) {
            $slot = $this->reportSlotForPeriod($user, $period);
            if ($slot->showAction()) {
                return $slot;
            }
        }

        return $this->emptySlot();
    }

    public function planSlotForPeriod(User $user, WorkPeriod $period): WeeklySubmissionSlot
    {
        if (! $period->isOpen()) {
            return $this->emptySlot();
        }

        $document = Document::findWeeklyPlanForPeriodOwner((string) $period->id, (string) $user->id);
        if ($document?->isSubmitted()) {
            return $this->emptySlot();
        }

        return new WeeklySubmissionSlot(
            $period,
            $document,
            $period->isPlanGracePeriodActive(),
            $period->isPlanGracePeriodActive() ? $period->getPlanDeadlineCutoff() : null,
        );
    }

    public function reportSlotForPeriod(User $user, WorkPeriod $period): WeeklySubmissionSlot
    {
        if (! $period->isOpen()) {
            return $this->emptySlot();
        }

        $document = Document::findWeeklyReportForPeriodOwner((string) $period->id, (string) $user->id);
        if ($document?->isSubmitted()) {
            return $this->emptySlot();
        }

        return new WeeklySubmissionSlot(
            $period,
            $document,
            $period->isReportGracePeriodActive(),
            $period->isReportGracePeriodActive() ? $period->getReportDeadlineCutoff() : null,
        );
    }

    private function anyPlanGracePeriod(User $user): ?WorkPeriod
    {
        if (! $user->hasPermissionTo('create weekly plan')) {
            return null;
        }

        $current = $this->workPeriods->periodForWeeklyReportSubmission();
        $next = $this->workPeriods->periodForWeeklyPlanSubmission();

        foreach ($this->planCandidatePeriods($current, $next) as $period) {
            if ($period->isPlanGracePeriodActive()) {
                return $period;
            }
        }

        return null;
    }

    private function anyReportGracePeriod(User $user): ?WorkPeriod
    {
        if (! $user->hasPermissionTo('create weekly report')) {
            return null;
        }

        $current = $this->workPeriods->periodForWeeklyReportSubmission();

        foreach ($this->reportCandidatePeriods($current) as $period) {
            if ($period->isReportGracePeriodActive()) {
                return $period;
            }
        }

        return null;
    }

    /**
     * Active staff who still owe a weekly plan or report for the given period.
     *
     * @return Collection<int, array{user: User, slot: WeeklySubmissionSlot}>
     */
    public function staffOwingWeeklyDocument(WorkPeriod $period, string $documentType): Collection
    {
        if (! in_array($documentType, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true)) {
            return collect();
        }

        $permission = $documentType === Document::TYPE_WEEKLY_PLAN
            ? 'create weekly plan'
            : 'create weekly report';

        return User::query()
            ->where('status', 'active')
            ->get()
            ->filter(fn (User $user) => $user->hasPermissionTo($permission))
            ->map(function (User $user) use ($period, $documentType) {
                $slot = $documentType === Document::TYPE_WEEKLY_PLAN
                    ? $this->planSlotForPeriod($user, $period)
                    : $this->reportSlotForPeriod($user, $period);

                return ['user' => $user, 'slot' => $slot];
            })
            ->filter(fn (array $row) => $row['slot']->showAction())
            ->values();
    }

    private function emptySlot(): WeeklySubmissionSlot
    {
        return new WeeklySubmissionSlot(null, null);
    }
}
