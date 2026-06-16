<?php

namespace App\Models;

use App\Concerns\HasShortId;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyPlanBudgetReview extends Model
{
    use HasShortId;

    protected $fillable = [
        'weekly_plan_budget_item_id',
        'decision',
        'approval_stage',
        'provided_amount',
        'note',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'authorized_by',
        'authorized_at',
    ];

    protected function casts(): array
    {
        return [
            'provided_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'authorized_at' => 'datetime',
        ];
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(WeeklyPlanBudgetItem::class, 'weekly_plan_budget_item_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
