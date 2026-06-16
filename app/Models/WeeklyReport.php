<?php

namespace App\Models;

use App\Concerns\HasDocumentShortId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeeklyReport extends Model
{
    use HasDocumentShortId, SoftDeletes;

    protected $fillable = [
        'user_id',
        'period_id',
        'title',
        'state',
        'summary',
        'target_accomplished',
        'target_accomplished_status',
        'target_explanation',
        'review_rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'target_accomplished' => 'boolean',
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

    public function activities(): HasMany
    {
        return $this->hasMany(WeeklyReportActivityProgress::class)->orderBy('sort_order');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(WeeklyReportMeeting::class)->orderBy('sort_order');
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(WeeklyReportAchievement::class)->orderBy('sort_order');
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(WeeklyReportChallenge::class)->orderBy('sort_order');
    }

    public function budgetStatuses(): HasMany
    {
        return $this->hasMany(WeeklyReportBudgetStatus::class)->orderBy('sort_order');
    }
}
