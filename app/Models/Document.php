<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Document Types Constants
     */
    public const TYPE_WEEKLY_PLAN = 'weekly_plan';
    public const TYPE_WEEKLY_REPORT = 'weekly_report';
    public const TYPE_MONTHLY_REPORT = 'monthly_report';
    public const TYPE_ACTION_LOG = 'action_log';

    /**
     * Document State Constants
     */
    public const STATE_DRAFT = 'draft';
    public const STATE_SUBMITTED = 'submitted';
    public const REVIEW_REJECTION_PREFIX = '[REJECT_TO_DRAFT] ';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'period_id',
        'type',
        'file_path',
        'attachment',
        'month',
        'year',
        'title',
        'file_size',
        'original_filename',
        'content',
        'main_target',
        'target_accomplished',
        'target_explanation',
        'state',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
            // Used for template-based weekly_plan/weekly_report forms.
            'content' => 'array',
        ];
    }

    /**
     * Get all available document types
     *
     * @return array
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_WEEKLY_PLAN => 'Weekly Plan',
            self::TYPE_WEEKLY_REPORT => 'Weekly Report',
            self::TYPE_MONTHLY_REPORT => 'Monthly Report',
            self::TYPE_ACTION_LOG => 'Action Log',
        ];
    }

    /**
     * Get all available document states
     *
     * @return array
     */
    public static function getStates(): array
    {
        return [
            self::STATE_DRAFT => 'Draft',
            self::STATE_SUBMITTED => 'Submitted',
        ];
    }

    /**
     * Get human-readable type name
     *
     * @return string
     */
    public function getTypeNameAttribute(): string
    {
        return self::getTypes()[$this->type] ?? ucwords(str_replace('_', ' ', $this->type));
    }

    /**
     * Get display title (use title or generate from type and period)
     *
     * @return string
     */
    public function getDisplayTitleAttribute(): string
    {
        if ($this->title) {
            return $this->title;
        }

        $typeName = $this->type_name;
        if ($this->period) {
            return $typeName . ' - Week ' . $this->period->week_number;
        }

        return $typeName;
    }

    /**
     * Get human-readable state name
     *
     * @return string
     */
    public function getStateNameAttribute(): string
    {
        return self::getStates()[$this->state] ?? ucfirst($this->state);
    }

    /**
     * Check if document is draft
     *
     * @return bool
     */
    public function isDraft(): bool
    {
        return $this->state === self::STATE_DRAFT;
    }

    /**
     * Check if document is submitted
     *
     * @return bool
     */
    public function isSubmitted(): bool
    {
        return $this->state === self::STATE_SUBMITTED;
    }

    /**
     * Submit the document
     *
     * @return bool
     */
    public function submit(): bool
    {
        return $this->update(['state' => self::STATE_SUBMITTED]);
    }

    /**
     * Revert document to draft
     *
     * @return bool
     */
    public function revertToDraft(): bool
    {
        return $this->update(['state' => self::STATE_DRAFT]);
    }

    /**
     * Get the full file path (URL)
     *
     * @return string|null
     */
    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    /**
     * Get the file path for storage
     *
     * @return string|null
     */
    public function getStoragePathAttribute(): ?string
    {
        return $this->file_path ? storage_path('app/public/' . $this->file_path) : null;
    }

    /**
     * Check if document has a file
     *
     * @return bool
     */
    public function hasFile(): bool
    {
        return !empty($this->file_path) && file_exists($this->storage_path);
    }

    /**
     * Get human-readable file size
     *
     * @return string
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    /**
     * Relationship: Document belongs to User
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Document belongs to WorkPeriod
     *
     * @return BelongsTo
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(WorkPeriod::class);
    }

    /**
     * Relationship: Document has many Comments
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comments()
    {
        return $this->hasMany(Comment::class)->latest();
    }

    /**
     * Scope: Filter by type
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: Filter by state
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $state
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfState($query, string $state)
    {
        return $query->where('state', $state);
    }

    /**
     * Scope: Filter by user
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Get only draft documents
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDrafts($query)
    {
        return $query->where('state', self::STATE_DRAFT);
    }

    /**
     * Scope: Get only submitted documents
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSubmitted($query)
    {
        return $query->where('state', self::STATE_SUBMITTED);
    }

    /**
     * Scope: Get weekly plans
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWeeklyPlans($query)
    {
        return $query->where('type', self::TYPE_WEEKLY_PLAN);
    }

    /**
     * Scope: Get weekly reports
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWeeklyReports($query)
    {
        return $query->where('type', self::TYPE_WEEKLY_REPORT);
    }

    /**
     * Scope: Get monthly reports
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMonthlyReports($query)
    {
        return $query->where('type', self::TYPE_MONTHLY_REPORT);
    }

    /**
     * Scope: Get action logs
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActionLogs($query)
    {
        return $query->where('type', self::TYPE_ACTION_LOG);
    }

    /**
     * Scope: Get documents for employees (plans and reports)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEmployeeDocuments($query)
    {
        return $query->whereIn('type', [
            self::TYPE_WEEKLY_PLAN,
            self::TYPE_WEEKLY_REPORT,
            self::TYPE_MONTHLY_REPORT,
        ]);
    }

    /**
     * Scope: Recent documents (last N days)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $days
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: Order by most recent
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Scope: Filter by period
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $periodId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPeriod($query, int $periodId)
    {
        return $query->where('period_id', $periodId);
    }

    /**
     * Check if a user can create this document type based on their role
     *
     * @param User $user
     * @param string $type
     * @param WorkPeriod|null $period
     * @return bool
     */
    public static function canUserCreateType(User $user, string $type, ?WorkPeriod $period = null): bool
    {
        // For weekly plans, reports, and minutes, check if period is open
        if (in_array($type, [self::TYPE_WEEKLY_PLAN, self::TYPE_WEEKLY_REPORT, self::TYPE_ACTION_LOG])) {
            // If period is provided, check if it's open
            if ($period && !$period->isOpen()) {
                return false;
            }
            
            // If no period provided, try to get current period
            if (!$period) {
                $period = WorkPeriod::getCurrent();
                if (!$period || !$period->isOpen()) {
                    return false;
                }
            }
        }

        // Users with approve documents permission can create any type
        if ($user->hasPermissionTo('approve documents')) {
            return true;
        }

        // Check specific create permissions for each document type
        switch ($type) {
            case self::TYPE_WEEKLY_PLAN:
            case self::TYPE_WEEKLY_REPORT:
            case self::TYPE_MONTHLY_REPORT:
                return $user->hasAnyPermission([
                    'create weekly plan',
                    'create weekly report',
                    'create monthly report'
                ]);
            
            case self::TYPE_ACTION_LOG:
                return $user->hasPermissionTo('create action logs');
        }

        return false;
    }

    /**
     * Get document types available for a specific user role
     *
     * @param User $user
     * @return array
     */
    public static function getAvailableTypesForUser(User $user): array
    {
        $allTypes = self::getTypes();
        $availableTypes = [];

        foreach ($allTypes as $type => $name) {
            if (self::canUserCreateType($user, $type)) {
                $availableTypes[$type] = $name;
            }
        }

        return $availableTypes;
    }

    /**
     * Check if document can be edited (only drafts can be edited)
     *
     * @return bool
     */
    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    /**
     * Check if document can be submitted (only drafts can be submitted)
     *
     * @return bool
     */
    public function canBeSubmitted(): bool
    {
        return $this->isDraft();
    }

    /**
     * Check if document can be deleted (only drafts can be deleted)
     *
     * @return bool
     */
    public function canBeDeleted(): bool
    {
        return $this->isDraft();
    }

    /**
     * Get latest rejection reason if document was returned to draft.
     */
    public function latestRejectionReason(): ?string
    {
        $latestComment = $this->relationLoaded('comments')
            ? $this->comments->first()
            : $this->comments()->first();

        if (!$latestComment || !is_string($latestComment->comment)) {
            return null;
        }

        if (!str_starts_with($latestComment->comment, self::REVIEW_REJECTION_PREFIX)) {
            return null;
        }

        return trim(substr($latestComment->comment, strlen(self::REVIEW_REJECTION_PREFIX)));
    }

    /**
     * Check if this draft document was returned for revision by reviewer.
     */
    public function isReturnedForRevision(): bool
    {
        return $this->isDraft() && $this->latestRejectionReason() !== null;
    }
}
