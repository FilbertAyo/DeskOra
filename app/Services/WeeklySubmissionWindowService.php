<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\WeeklySubmissionSlot;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WeeklySubmissionWindowService
{
    /** @var Collection<int, WorkPeriod>|null */
    private ?Collection $recentWorkPeriodsCache = null;

    public function __construct(private readonly WorkPeriodService $workPeriods) {}

    public function resolvePlanSlot(User $user, ?WorkPeriod $preferredPeriod = null): WeeklySubmissionSlot
    {
        if (! $user->hasPermissionTo('create weekly plan')) {
            return $this->emptySlot();
        }

        $current = $this->workPeriods->periodForWeeklyReportSubmission();
        $next = $this->workPeriods->periodForWeeklyPlanSubmission();

        $candidates = $this->planCandidatePeriods($current, $next);
        if ($preferredPeriod && ($preferredPeriod->isOpen() || $preferredPeriod->isPlanGracePeriodActive())) {
            $candidates = $this->mergeUniquePeriods(
                collect([$preferredPeriod]),
                $candidates->all(),
            );
        }

        return $this->firstActionablePlanSlot($user, $candidates);
    }

    public function resolveReportSlot(User $user, ?WorkPeriod $preferredPeriod = null): WeeklySubmissionSlot
    {
        if (! $user->hasPermissionTo('create weekly report')) {
            return $this->emptySlot();
        }

        $current = $this->workPeriods->periodForWeeklyReportSubmission();

        $candidates = $preferredPeriod && ($preferredPeriod->isOpen() || $preferredPeriod->isReportGracePeriodActive())
            ? collect([$preferredPeriod])
            : $this->reportCandidatePeriods($current);

        return $this->firstActionableReportSlot($user, $candidates);
    }

    /**
     * Plan slot for a period that is in an admin-extended (grace) plan window, if the user still owes that plan.
     */
    public function gracePlanSlot(User $user): WeeklySubmissionSlot
    {
        return $this->gracePlanEntries($user)->first() ?? $this->emptySlot();
    }

    /**
     * Report slot for a period that is in an admin-extended (grace) report window, if the user still owes that report.
     */
    public function graceReportSlot(User $user): WeeklySubmissionSlot
    {
        return $this->graceReportEntries($user)->first() ?? $this->emptySlot();
    }

    /**
     * All weekly plan grace windows the user can still act on.
     *
     * @return Collection<int, WeeklySubmissionSlot>
     */
    public function gracePlanEntries(User $user): Collection
    {
        if (! $user->hasPermissionTo('create weekly plan')) {
            return collect();
        }

        return $this->recentWorkPeriods()
            ->filter(fn (WorkPeriod $period) => $period->isPlanGracePeriodActive())
            ->map(fn (WorkPeriod $period) => $this->planSlotForPeriod($user, $period))
            ->filter(fn (WeeklySubmissionSlot $slot) => $slot->showAction() && $slot->graceActive)
            ->values();
    }

    /**
     * All weekly report grace windows the user can still act on.
     *
     * @return Collection<int, WeeklySubmissionSlot>
     */
    public function graceReportEntries(User $user): Collection
    {
        if (! $user->hasPermissionTo('create weekly report')) {
            return collect();
        }

        return $this->recentWorkPeriods()
            ->filter(fn (WorkPeriod $period) => $period->isReportGracePeriodActive())
            ->map(fn (WorkPeriod $period) => $this->reportSlotForPeriod($user, $period))
            ->filter(fn (WeeklySubmissionSlot $slot) => $slot->showAction() && $slot->graceActive)
            ->values();
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
        return $this->gracePlanEntries($user)->first()?->period;
    }

    public function activeReportGracePeriod(User $user): ?WorkPeriod
    {
        return $this->graceReportEntries($user)->first()?->period;
    }

    public function planGraceEndsAt(User $user): ?\Carbon\Carbon
    {
        return $this->gracePlanEntries($user)->first()?->graceEndsAt;
    }

    public function reportGraceEndsAt(User $user): ?\Carbon\Carbon
    {
        return $this->graceReportEntries($user)->first()?->graceEndsAt;
    }

    /**
     * @return Collection<int, WorkPeriod>
     */
    public function planCandidatePeriods(?WorkPeriod $current, ?WorkPeriod $next): Collection
    {
        $gracePeriods = collect($this->recentWorkPeriods()->all())
            ->filter(fn (WorkPeriod $period) => $period->isPlanGracePeriodActive());

        // Normally plans target next week; grace periods (newest first) take priority.
        $ordered = ($current && $current->isPlanGracePeriodActive())
            ? [$current, $next]
            : [$next, $current];

        return $this->mergeUniquePeriods($gracePeriods, $ordered);
    }

    /**
     * @return Collection<int, WorkPeriod>
     */
    public function reportCandidatePeriods(?WorkPeriod $current): Collection
    {
        $previous = $current?->getPreviousPeriod();

        $gracePeriods = collect($this->recentWorkPeriods()->all())
            ->filter(fn (WorkPeriod $period) => $period->isReportGracePeriodActive());

        $ordered = ($previous && $previous->isReportGracePeriodActive())
            ? [$previous, $current]
            : [$current, $previous];

        return $this->mergeUniquePeriods($gracePeriods, $ordered);
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
        if (! $period->isOpen() && ! $period->isPlanGracePeriodActive()) {
            return $this->emptySlot();
        }

        $document = Document::findWeeklyPlanForUserAndPeriod((string) $period->id, (string) $user->id);
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
        if (! $period->isOpen() && ! $period->isReportGracePeriodActive()) {
            return $this->emptySlot();
        }

        $document = Document::findWeeklyReportForUserAndPeriod((string) $period->id, (string) $user->id);
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
        return $this->activePlanGracePeriod($user);
    }

    private function anyReportGracePeriod(User $user): ?WorkPeriod
    {
        return $this->activeReportGracePeriod($user);
    }

    /**
     * @return Collection<int, WorkPeriod>
     */
    private function recentWorkPeriods(): Collection
    {
        if ($this->recentWorkPeriodsCache !== null) {
            return $this->recentWorkPeriodsCache;
        }

        $this->recentWorkPeriodsCache = WorkPeriod::query()
            ->where('week_start_date', '>=', Carbon::now()->subWeeks(16)->startOfWeek()->format('Y-m-d'))
            ->orderByDesc('week_start_date')
            ->get();

        return $this->recentWorkPeriodsCache;
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

    /**
     * Merge period lists without nulls. Uses a base Collection so Eloquent merge/unique never calls getKey() on null.
     *
     * @param  Collection<int, WorkPeriod>  $primary
     * @param  list<WorkPeriod|null>  $additional
     * @return Collection<int, WorkPeriod>
     */
    private function mergeUniquePeriods(Collection $primary, array $additional): Collection
    {
        return collect($primary->all())
            ->concat(
                collect($additional)->filter(fn ($period) => $period instanceof WorkPeriod)
            )
            ->unique(fn (WorkPeriod $period) => (string) $period->getKey())
            ->values();
    }
}
