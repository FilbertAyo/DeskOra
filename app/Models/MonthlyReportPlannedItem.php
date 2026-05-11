<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyReportPlannedItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'monthly_report_id',
        'year_plan_activity_id',
        'implementation_status',
        'achievements',
        'challenges',
        'next_steps',
        'sort_order',
    ];

    public function monthlyReport(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class);
    }

    public function yearPlanActivity(): BelongsTo
    {
        return $this->belongsTo(YearPlanActivity::class);
    }
}
