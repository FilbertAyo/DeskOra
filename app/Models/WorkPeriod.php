<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class WorkPeriod extends Model
{
    use HasFactory, SoftDeletes;

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
     * - Plan deadline (Friday): Submit plan FOR the NEXT week
     * - Report deadline (Saturday): Submit report FOR the CURRENT week
     * 
     * Example:
     * - Week 1 (Jan 1-7): 
     *   - Plan for Week 2 due Friday Jan 5
     *   - Report for Week 1 due Saturday Jan 6
     * - Week 2 (Jan 8-14):
     *   - Plan for Week 3 due Friday Jan 12
     *   - Report for Week 2 due Saturday Jan 13
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
     * Relationship: WorkPeriod has many Documents
     *
     * @return HasMany
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'period_id');
    }

    /**
     * Get weekly plans for this period
     *
     * @return HasMany
     */
    public function weeklyPlans(): HasMany
    {
        return $this->hasMany(Document::class, 'period_id')->where('type', Document::TYPE_WEEKLY_PLAN);
    }

    /**
     * Get weekly reports for this period
     *
     * @return HasMany
     */
    public function weeklyReports(): HasMany
    {
        return $this->hasMany(Document::class, 'period_id')->where('type', Document::TYPE_WEEKLY_REPORT);
    }

    /**
     * Get monthly reports for this period
     *
     * @return HasMany
     */
    public function monthlyReports(): HasMany
    {
        return $this->hasMany(Document::class, 'period_id')->where('type', Document::TYPE_MONTHLY_REPORT);
    }

    /**
     * Scope: Get current open period
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
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
     * @param \Illuminate\Database\Eloquent\Builder $query
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
     * @param \Illuminate\Database\Eloquent\Builder $query
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
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $year
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByYear($query, int $year)
    {
        return $query->where('year', $year)->orderBy('week_number', 'asc');
    }

    /**
     * Scope: Get open periods
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope: Get closed periods
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    /**
     * Check if period is open
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Check if period is closed
     *
     * @return bool
     */
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Check if period is archived
     *
     * @return bool
     */
    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /**
     * Check if plan deadline is today
     *
     * @return bool
     */
    public function isPlanDeadlineToday(): bool
    {
        return Carbon::parse($this->plan_deadline)->isToday();
    }

    /**
     * Check if report deadline is today
     *
     * @return bool
     */
    public function isReportDeadlineToday(): bool
    {
        return Carbon::parse($this->report_deadline)->isToday();
    }

    /**
     * Check if plan deadline has passed
     *
     * @return bool
     */
    public function isPlanDeadlinePassed(): bool
    {
        return Carbon::parse($this->plan_deadline)->isPast();
    }

    /**
     * Check if report deadline has passed
     *
     * @return bool
     */
    public function isReportDeadlinePassed(): bool
    {
        return Carbon::parse($this->report_deadline)->isPast();
    }

    /**
     * Get days until plan deadline
     *
     * @return int
     */
    public function getDaysUntilPlanDeadline(): int
    {
        $deadline = Carbon::parse($this->plan_deadline);
        $today = Carbon::today();
        
        if ($deadline->isPast()) {
            return 0;
        }
        
        return $today->diffInDays($deadline, false);
    }

    /**
     * Get days until report deadline
     *
     * @return int
     */
    public function getDaysUntilReportDeadline(): int
    {
        $deadline = Carbon::parse($this->report_deadline);
        $today = Carbon::today();
        
        if ($deadline->isPast()) {
            return 0;
        }
        
        return $today->diffInDays($deadline, false);
    }

    /**
     * Get period display name
     *
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        return "Week {$this->week_number} of {$this->year}";
    }

    /**
     * Get period date range display
     *
     * @return string
     */
    public function getDateRangeAttribute(): string
    {
        $start = Carbon::parse($this->week_start_date)->format('M d');
        $end = Carbon::parse($this->week_end_date)->format('M d, Y');
        return "{$start} - {$end}";
    }

    /**
     * Close the period
     *
     * @return bool
     */
    public function close(): bool
    {
        return $this->update(['status' => self::STATUS_CLOSED]);
    }

    /**
     * Archive the period
     *
     * @return bool
     */
    public function archive(): bool
    {
        return $this->update(['status' => self::STATUS_ARCHIVED]);
    }

    /**
     * Get current period (static helper)
     *
     * @return WorkPeriod|null
     */
    public static function getCurrent(): ?WorkPeriod
    {
        return self::current()->first();
    }

    /**
     * Get period by date
     *
     * @param Carbon|string $date
     * @return WorkPeriod|null
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
     *
     * @return WorkPeriod|null
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
     *
     * @return WorkPeriod|null
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
     *
     * @return WorkPeriod|null
     */
    public static function getPeriodForPlanSubmission(): ?WorkPeriod
    {
        $current = self::getCurrent();
        if (!$current) {
            return null;
        }
        
        return $current->getNextPeriod();
    }

    /**
     * Get period for report submission
     * Reports are created FOR the current week, submitted during current week
     * This returns the period that the report is FOR (current week)
     *
     * @return WorkPeriod|null
     */
    public static function getPeriodForReportSubmission(): ?WorkPeriod
    {
        return self::getCurrent();
    }

    /**
     * Check if plan deadline is for submitting plan FOR next week
     * Plan deadline is Friday of current week, to submit plan for next week
     *
     * @return bool
     */
    public function canSubmitPlanForNextWeek(): bool
    {
        if (!$this->isOpen()) {
            return false;
        }

        // Check if plan deadline hasn't passed
        if ($this->isPlanDeadlinePassed()) {
            return false;
        }

        // Check if next period exists
        return $this->getNextPeriod() !== null;
    }

    /**
     * Check if report deadline is for submitting report FOR this week
     * Report deadline is Saturday of current week, to submit report for current week
     *
     * @return bool
     */
    public function canSubmitReportForThisWeek(): bool
    {
        if (!$this->isOpen()) {
            return false;
        }

        // Check if report deadline hasn't passed
        return !$this->isReportDeadlinePassed();
    }

    /**
     * Check if plan deadline is approaching (within 2 days)
     *
     * @return bool
     */
    public function isPlanDeadlineApproaching(): bool
    {
        $daysUntil = $this->getDaysUntilPlanDeadline();
        return $daysUntil > 0 && $daysUntil <= 2;
    }

    /**
     * Check if report deadline is approaching (within 2 days)
     *
     * @return bool
     */
    public function isReportDeadlineApproaching(): bool
    {
        $daysUntil = $this->getDaysUntilReportDeadline();
        return $daysUntil > 0 && $daysUntil <= 2;
    }

    /**
     * Get deadline status for plan
     * Returns: 'overdue', 'today', 'approaching', 'upcoming', 'passed'
     *
     * @return string
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
     *
     * @return string
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
     *
     * @return string
     */
    public function getPlanDeadlineBadgeClass(): string
    {
        return match($this->getPlanDeadlineStatus()) {
            'overdue' => 'badge-danger',
            'today' => 'badge-warning',
            'approaching' => 'badge-warning',
            'upcoming' => 'badge-info',
            default => 'badge-secondary',
        };
    }

    /**
     * Get badge color class for report deadline status
     *
     * @return string
     */
    public function getReportDeadlineBadgeClass(): string
    {
        return match($this->getReportDeadlineStatus()) {
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
     * @param string $type 'plan' or 'report'
     * @return string
     */
    public function getDeadlineStatusText(string $type = 'plan'): string
    {
        $status = $type === 'plan' ? $this->getPlanDeadlineStatus() : $this->getReportDeadlineStatus();
        $daysUntil = $type === 'plan' ? $this->getDaysUntilPlanDeadline() : $this->getDaysUntilReportDeadline();

        return match($status) {
            'overdue' => 'Overdue',
            'today' => 'Due Today',
            'approaching' => "Due in {$daysUntil} day(s)",
            'upcoming' => "Due in {$daysUntil} days",
            default => 'Passed',
        };
    }
}
