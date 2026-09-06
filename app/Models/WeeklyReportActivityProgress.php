<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyReportActivityProgress extends Model
{
    use HasShortId;

    protected $table = 'weekly_report_activity_progress';

    protected $fillable = [
        'weekly_report_id',
        'activity_uid',
        'activity_output',
        'off_plan_activity',
        'progress',
        'remarks_challenges',
        'next_step',
        'carry_to_next_plan',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'carry_to_next_plan' => 'boolean',
        ];
    }

    public function weeklyReport(): BelongsTo
    {
        return $this->belongsTo(WeeklyReport::class);
    }
}
