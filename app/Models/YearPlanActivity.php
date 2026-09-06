<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class YearPlanActivity extends Model
{
    use HasShortId, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('year_plans')
            ->logOnly([
                'year_plan_id',
                'department_id',
                'sort_order',
                'description',
                'frequency',
                'unit_count',
                'unit_value_tzs',
                'total_cost_tzs',
                'planned_months',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'year_plan_id',
        'department_id',
        'sort_order',
        'description',
        'frequency',
        'unit_count',
        'unit_value_tzs',
        'total_cost_tzs',
        'planned_months',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'frequency' => 'decimal:2',
            'unit_count' => 'decimal:2',
            'unit_value_tzs' => 'decimal:2',
            'total_cost_tzs' => 'decimal:2',
            'planned_months' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (YearPlanActivity $activity) {
            $activity->total_cost_tzs = number_format(
                round(
                    (float) $activity->frequency * (float) $activity->unit_count * (float) $activity->unit_value_tzs,
                    2
                ),
                2,
                '.',
                ''
            );
        });
    }

    public function yearPlan(): BelongsTo
    {
        return $this->belongsTo(YearPlan::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function displayCode(): string
    {
        $section = $this->department?->plan_section_number;

        if ($section === null) {
            return '—.'.$this->sort_order;
        }

        return $section.'.'.$this->sort_order;
    }

    public function isPlannedInMonth(int $month): bool
    {
        $months = array_map('intval', $this->planned_months ?? []);

        return in_array($month, $months, true);
    }
}
