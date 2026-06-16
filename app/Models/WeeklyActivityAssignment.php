<?php

namespace App\Models;

use App\Concerns\HasShortId;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyActivityAssignment extends Model
{
    use HasFactory, HasShortId;

    public const STATUS_PREVIEW = 'preview';

    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'source_weekly_plan_id',
        'source_user_id',
        'assignee_user_id',
        'period_id',
        'activity_uid',
        'status',
        'activity_payload',
    ];

    protected function casts(): array
    {
        return [
            'activity_payload' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_weekly_plan_id', 'id');
    }

    public function sourceWeeklyPlan(): BelongsTo
    {
        return $this->belongsTo(WeeklyPlan::class, 'source_weekly_plan_id');
    }

    public function scopeForSourceWeeklyPlan(Builder $query, string $sourceWeeklyPlanId): Builder
    {
        return $query->where('source_weekly_plan_id', $sourceWeeklyPlanId);
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(WorkPeriod::class, 'period_id');
    }
}
