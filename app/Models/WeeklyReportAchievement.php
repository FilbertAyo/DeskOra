<?php

namespace App\Models;

use App\Concerns\HasShortId;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyReportAchievement extends Model
{
    use HasShortId;

    protected $fillable = [
        'weekly_report_id',
        'achievement',
        'sort_order',
    ];

    public function weeklyReport(): BelongsTo
    {
        return $this->belongsTo(WeeklyReport::class);
    }
}
