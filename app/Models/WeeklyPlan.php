<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeeklyPlan extends Model
{
    use HasShortId, SoftDeletes;

    public const STATE_DRAFT = 'draft';

    public const STATE_SUBMITTED = 'submitted';

    protected $fillable = [
        'user_id',
        'period_id',
        'title',
        'state',
        'main_target',
        'objective_reflection_progress',
        'review_rejection_reason',
        'budget_review_status',
        'budget_reviewed_by',
        'budget_reviewed_at',
        'budget_provided_amount',
        'budget_review_note',
    ];

    protected function casts(): array
    {
        return [
            'budget_reviewed_at' => 'datetime',
            'budget_provided_amount' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(WorkPeriod::class, 'period_id');
    }

    public function budgetReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'budget_reviewed_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(WeeklyPlanActivity::class)->orderBy('sort_order');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(WeeklyPlanMeeting::class)->orderBy('sort_order');
    }

    public function budgetItems(): HasMany
    {
        return $this->hasMany(WeeklyPlanBudgetItem::class)->orderBy('sort_order');
    }
}
