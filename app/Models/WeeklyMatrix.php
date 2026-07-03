<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeeklyMatrix extends Model
{
    use HasShortId, SoftDeletes;

    protected $fillable = [
        'period_id',
        'department_id',
        'created_by',
        'updated_by',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(WorkPeriod::class, 'period_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(WeeklyMatrixEntry::class)->orderBy('sort_order');
    }
}
