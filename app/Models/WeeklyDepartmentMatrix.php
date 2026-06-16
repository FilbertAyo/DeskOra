<?php

namespace App\Models;

use App\Concerns\HasShortId;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeeklyDepartmentMatrix extends Model
{
    use HasShortId, SoftDeletes;

    protected $fillable = [
        'period_id',
        'department_id',
        'created_by',
        'updated_by',
        'overall_budget',
        'notes',
        'entries',
    ];

    protected function casts(): array
    {
        return [
            'overall_budget' => 'decimal:2',
            'entries' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(WorkPeriod::class, 'period_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
