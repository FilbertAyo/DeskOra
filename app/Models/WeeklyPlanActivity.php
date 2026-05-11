<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WeeklyPlanActivity extends Model
{
    use HasUlids;

    protected $fillable = [
        'weekly_plan_id',
        'activity_uid',
        'day',
        'activity',
        'location',
        'notes_inclusion',
        'carry_over_source_report_id',
        'sort_order',
    ];

    public function weeklyPlan(): BelongsTo
    {
        return $this->belongsTo(WeeklyPlan::class);
    }

    public function carryOverSourceReport(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'carry_over_source_report_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'weekly_plan_activity_assignees', 'weekly_plan_activity_id', 'user_id')
            ->withTimestamps();
    }
}
