<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyReportOffPlanItem extends Model
{
    use HasShortId;

    protected $fillable = [
        'monthly_report_id',
        'title',
        'description',
        'status',
        'notes',
        'sort_order',
    ];

    public function monthlyReport(): BelongsTo
    {
        return $this->belongsTo(MonthlyReport::class);
    }
}
