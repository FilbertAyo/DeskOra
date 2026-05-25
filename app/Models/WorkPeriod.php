<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WorkPeriod extends Model
{
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('work_periods')
            ->logOnly([
                'year',
                'week_number',
                'week_start_date',
                'week_end_date',
                'plan_deadline',
                'report_deadline',
                'status',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Status Constants
     */
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * IMPORTANT: Submission Logic
     *
     * During the CURRENT week:
     * - Plan deadline (Saturday 23:59): Submit plan FOR the NEXT week
     * - Report deadline (Saturday): Submit report FOR the CURRENT week
     *
     * Example:
     * - Week 1 (Jan 1-7):
     *   - Plan for Week 2 due Saturday Jan 6 at 23:59
     *   - Report for Week 1 due Saturday Jan 6 at 23:59
     * - Week 2 (Jan 8-14):
     *   - Plan for Week 3 due Saturday Jan 13 at 23:59
     *   - Report for Week 2 due Saturday Jan 13 at 23:59
     */

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'year',
        'week_number',
        'week_start_date',
        'week_end_date',
        'plan_deadline',
        'report_deadline',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'week_number' => 'integer',
            'week_start_date' => 'date',
            'week_end_date' => 'date',
            'plan_deadline' => 'date',
            'report_deadline' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Plan deadline calendar date (stored value; admins may extend via edit).
     * Falls back to Saturday before week start when unset (legacy rows).
     */
    private function canonicalPlanDeadlineDate(): Carbon
    {
        if ($this->plan_deadline !== null) {
            return Carbon::parse($this->plan_deadline)->startOfDay();
        }

        return Carbon::parse($this->week_start_date)->subDays(2)->startOfDay();
    }

    /**
     * Report deadline calendar date (stored value; admins may extend via edit).
     * Falls back to Saturday of the work week when unset (legacy rows).
     */
    private function canonicalReportDeadlineDate(): Carbon
    {
        if ($this->report_deadline !== null) {
            return Carbon::parse($this->report_deadline)->startOfDay();
        }

        return Carbon::parse($this->week_start_date)->addDays(5)->startOfDay();
    }

    /**
     * Plan deadline cutoff timestamp (23:59 local time on the stored plan deadline date).
     */
    public function getPlanDeadlineCutoff(): Carbon
    {
        return $this->canonicalPlanDeadlineDate()->copy()->setTime(23, 59, 0);
    }

    /**
     * Report deadline cutoff timestamp (23:59 local time on the stored report deadline date).
     */
    public function getReportDeadlineCutoff(): Carbon
    {
        return $this->canonicalReportDeadlineDate()->copy()->setTime(23, 59, 0);
    }

    /**
     * Relationship: WorkPeriod has many Documents
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'period_id');
    }

    /**
     * Get weekly plans for this period
     */
    public function weeklyPlans(): HasMany
    {
        return $this->hasMany(Document::class, 'period_id')->where('type', Document::TYPE_WEEKLY_PLAN);
    }

    /**
     * Get weekly reports for this period
     */
    public function weeklyReports(): HasMany
    {
        return $this->hasMany(Document::class, 'period_id')->where('type', Document::TYPE_WEEKLY_REPORT);
    }

    /**
     * Get monthly reports for this period
     */
    public function monthlyReports(): HasMany
    {
        return $this->hasMany(Document::class, 'period_id')->where('type', Document::TYPE_MONTHLY_REPORT);
    }

    /**
     * Scope: Get current open period
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCurrent($query)
    {
        $today = Carbon::today();

        return $query->where('status', self::STATUS_OPEN)
            ->where('week_start_date', '<=', $today)
            ->where('week_end_date', '>=', $today)
            ->orderBy('week_start_date', 'desc');
    }

    /**
     * Scope: Get upcoming periods
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUpcoming($query)
    {
        $today = Carbon::today();

        return $query->where('status', self::STATUS_OPEN)
            ->where('week_start_date', '>', $today)
            ->orderBy('week_start_date', 'asc');
    }

    /**
     * Scope: Get past periods
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePast($query)
    {
        $today = Carbon::today();

        return $query->where('week_end_date', '<', $today)
            ->orderBy('week_end_date', 'desc');
    }

    /**
     * Scope: Get periods by year
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope: Get open periods
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope: Get closed periods
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    /**
     * Check if period is open
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Check if period is closed
     */
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Check if period is archived
     */
    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /**
     * Check if plan deadline is today
     */
    public function isPlanDeadlineToday(): bool
    {
        return $this->canonicalPlanDeadlineDate()->isToday();
    }

    /**
     * Check if report deadline is today
     */
    public function isReportDeadlineToday(): bool
    {
        return $this->canonicalReportDeadlineDate()->isToday();
    }

    /**
     * Check if plan deadline has passed
     */
    public function isPlanDeadlinePassed(): bool
    {
        return Carbon::now()->greaterThan($this->getPlanDeadlineCutoff());
    }

    /**
     * Check if report deadline has passed
     */
    public function isReportDeadlinePassed(): bool
    {
        return Carbon::now()->greaterThan($this->getReportDeadlineCutoff());
    }

    /**
     * Get days until plan deadline
     */
    public function getDaysUntilPlanDeadline(): int
    {
        $cutoff = $this->getPlanDeadlineCutoff();
        $now = Carbon::now();

        if ($now->greaterThan($cutoff)) {
            return 0;
        }

        return $now->diffInDays($cutoff, false);
    }

    /**
     * Get days until report deadline
     */
    public function getDaysUntilReportDeadline(): int
    {
        $cutoff = $this->getReportDeadlineCutoff();
        $now = Carbon::now();

        if ($now->greaterThan($cutoff)) {
            return 0;
        }

        return $now->diffInDays($cutoff, false);
    }

    /**
     * Get period display name
     */
    public function getDisplayNameAttribute(): string
    {
        return "Week {$this->week_number} of {$this->year}";
    }

    /**
     * Get period date range display
     */
    public function getDateRangeAttribute(): string
    {
        $start = Carbon::parse($this->week_start_date)->format('M d');
        $end = Carbon::parse($this->week_end_date)->format('M d, Y');

        return "{$start} - {$end}";
    }

    /**
     * Close the period
     */
    public function close(): bool
    {
        return $this->update(['status' => self::STATUS_CLOSED]);
    }

    /**
     * Archive the period
     */
    public function archive(): bool
    {
        return $this->update(['status' => self::STATUS_ARCHIVED]);
    }

    /**
     * Get current period (static helper)
     */
    public static function getCurrent(): ?WorkPeriod
    {
        return self::current()->first();
    }

    /**
     * Get period by date
     *
     * @param  Carbon|string  $date
     */
    public static function getPeriodByDate($date): ?WorkPeriod
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        return self::where('week_start_date', '<=', $date)
            ->where('week_end_date', '>=', $date)
            ->first();
    }

    /**
     * Get next period (for plan submission)
     * Plans are submitted FOR the next week, during the current week
     */
    public function getNextPeriod(): ?WorkPeriod
    {
        // If next week is in the same year
        $nextWeekNumber = $this->week_number + 1;
        $nextPeriod = self::where('year', $this->year)
            ->where('week_number', $nextWeekNumber)
            ->first();

        if ($nextPeriod) {
            return $nextPeriod;
        }

        // If next week is in the next year (Week 1 of next year)
        $nextYear = $this->year + 1;

        return self::where('year', $nextYear)
            ->where('week_number', 1)
            ->first();
    }

    /**
     * Get previous period (for report submission context)
     * Reports are submitted FOR the current week, during the current week
     */
    public function getPreviousPeriod(): ?WorkPeriod
    {
        // If previous week is in the same year
        $prevWeekNumber = $this->week_number - 1;
        if ($prevWeekNumber > 0) {
            return self::where('year', $this->year)
                ->where('week_number', $prevWeekNumber)
                ->first();
        }

        // If previous week is in the previous year (last week of previous year)
        $prevYear = $this->year - 1;

        return self::where('year', $prevYear)
            ->orderBy('week_number', 'desc')
            ->first();
    }

    /**
     * Get period for plan submission
     * Plans are created FOR the next week, but submitted during current week
     * This returns the period that the plan is FOR (next week)
     */
    public static function getPeriodForPlanSubmission(): ?WorkPeriod
    {
        $current = self::getCurrent();
        if (! $current) {
            return null;
        }

        return $current->getNextPeriod();
    }

    /**
     * Get period for report submission
     * Reports are created FOR the current week, submitted during current week
     * This returns the period that the report is FOR (current week)
     */
    public static function getPeriodForReportSubmission(): ?WorkPeriod
    {
        return self::getCurrent();
    }

    /**
     * Whether an open plan for the next work period can still be submitted.
     */
    public function canSubmitPlanForNextWeek(): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        $next = $this->getNextPeriod();

        return $next !== null && $next->isOpen();
    }

    /**
     * Whether a report for this work period can still be submitted.
     */
    public function canSubmitReportForThisWeek(): bool
    {
        return $this->isOpen();
    }

    /**
     * Check if plan deadline is approaching (within 2 days)
     */
    public function isPlanDeadlineApproaching(): bool
    {
        $daysUntil = $this->getDaysUntilPlanDeadline();

        return $daysUntil > 0 && $daysUntil <= 2;
    }

    /**
     * Check if report deadline is approaching (within 2 days)
     */
    public function isReportDeadlineApproaching(): bool
    {
        $daysUntil = $this->getDaysUntilReportDeadline();

        return $daysUntil > 0 && $daysUntil <= 2;
    }

    /**
     * Get deadline status for plan
     * Returns: 'overdue', 'today', 'approaching', 'upcoming', 'passed'
     */
    public function getPlanDeadlineStatus(): string
    {
        if ($this->isPlanDeadlinePassed()) {
            return 'overdue';
        }

        if ($this->isPlanDeadlineToday()) {
            return 'today';
        }

        if ($this->isPlanDeadlineApproaching()) {
            return 'approaching';
        }

        $daysUntil = $this->getDaysUntilPlanDeadline();
        if ($daysUntil > 2) {
            return 'upcoming';
        }

        return 'passed';
    }

    /**
     * Get deadline status for report
     * Returns: 'overdue', 'today', 'approaching', 'upcoming', 'passed'
     */
    public function getReportDeadlineStatus(): string
    {
        if ($this->isReportDeadlinePassed()) {
            return 'overdue';
        }

        if ($this->isReportDeadlineToday()) {
            return 'today';
        }

        if ($this->isReportDeadlineApproaching()) {
            return 'approaching';
        }

        $daysUntil = $this->getDaysUntilReportDeadline();
        if ($daysUntil > 2) {
            return 'upcoming';
        }

        return 'passed';
    }

    /**
     * Get badge color class for plan deadline status
     */
    public function getPlanDeadlineBadgeClass(): string
    {
        return match ($this->getPlanDeadlineStatus()) {
            'overdue' => 'badge-danger',
            'today' => 'badge-warning',
            'approaching' => 'badge-warning',
            'upcoming' => 'badge-info',
            default => 'badge-secondary',
        };
    }

    /**
     * Get badge color class for report deadline status
     */
    public function getReportDeadlineBadgeClass(): string
    {
        return match ($this->getReportDeadlineStatus()) {
            'overdue' => 'badge-danger',
            'today' => 'badge-warning',
            'approaching' => 'badge-warning',
            'upcoming' => 'badge-info',
            default => 'badge-secondary',
        };
    }

    /**
     * Get human-readable deadline status text
     *
     * @param  string  $type  'plan' or 'report'
     */
    public function getDeadlineStatusText(string $type = 'plan'): string
    {
        $status = $type === 'plan' ? $this->getPlanDeadlineStatus() : $this->getReportDeadlineStatus();
        $daysUntil = $type === 'plan' ? $this->getDaysUntilPlanDeadline() : $this->getDaysUntilReportDeadline();

        return match ($status) {
            'overdue' => 'Overdue',
            'today' => 'Due Today',
            'approaching' => "Due in {$daysUntil} day(s)",
            'upcoming' => "Due in {$daysUntil} days",
            default => 'Passed',
        };
    }
}
