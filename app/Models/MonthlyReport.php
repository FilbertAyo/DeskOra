<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MonthlyReport extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'month',
        'year',
        'title',
        'state',
        'file_path',
        'attachment',
        'file_size',
        'original_filename',
        'month_summary',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'year' => 'integer',
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

    public function plannedItems(): HasMany
    {
        return $this->hasMany(MonthlyReportPlannedItem::class)->orderBy('sort_order');
    }

    public function offPlanItems(): HasMany
    {
        return $this->hasMany(MonthlyReportOffPlanItem::class)->orderBy('sort_order');
    }
}
