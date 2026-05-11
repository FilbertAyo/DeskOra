<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WeeklyPlanBudgetItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'weekly_plan_id',
        'budget_uid',
        'item',
        'description_purpose',
        'amount_raw',
        'amount_numeric',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount_numeric' => 'decimal:2',
        ];
    }

    public function weeklyPlan(): BelongsTo
    {
        return $this->belongsTo(WeeklyPlan::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(WeeklyPlanBudgetReview::class, 'weekly_plan_budget_item_id');
    }
}
