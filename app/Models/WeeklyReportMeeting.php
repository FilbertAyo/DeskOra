<?php

namespace App\Models;

use App\Concerns\HasShortId;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WeeklyReportMeeting extends Model
{
    use HasShortId;

    protected $fillable = [
        'weekly_report_id',
        'meeting_uid',
        'date',
        'meeting',
        'status',
        'location',
        'reactions_notes',
        'source_type',
        'source_plan_id',
        'source_owner_user_id',
        'is_readonly_for_current_user',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_readonly_for_current_user' => 'boolean',
        ];
    }

    public function weeklyReport(): BelongsTo
    {
        return $this->belongsTo(WeeklyReport::class);
    }

    public function sourcePlan(): BelongsTo
    {
        return $this->belongsTo(WeeklyPlan::class, 'source_plan_id');
    }

    public function sourceOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_owner_user_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'weekly_report_meeting_participants', 'weekly_report_meeting_id', 'user_id')
            ->withTimestamps();
    }
}
