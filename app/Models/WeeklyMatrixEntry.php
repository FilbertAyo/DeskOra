<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WeeklyMatrixEntry extends Model
{
    use HasShortId;

    protected $fillable = [
        'weekly_matrix_id',
        'sort_order',
        'key_activity',
        'output',
        'outcome',
        'impact',
        'indicators',
        'baseline',
        'target',
        'means_of_verification',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function weeklyMatrix(): BelongsTo
    {
        return $this->belongsTo(WeeklyMatrix::class);
    }

    public function yearPlanActivities(): BelongsToMany
    {
        return $this->belongsToMany(
            YearPlanActivity::class,
            'weekly_matrix_entry_year_plan_activity',
            'weekly_matrix_entry_id',
            'year_plan_activity_id'
        );
    }
}
