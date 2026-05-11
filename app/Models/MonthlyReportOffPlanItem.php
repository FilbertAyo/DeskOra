<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyReportOffPlanItem extends Model
{
    use HasUlids;

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
