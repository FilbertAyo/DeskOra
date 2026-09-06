<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Replenishment extends Model
{
    use HasShortId, LogsActivity;

    protected $fillable = [
        'from',
        'to',
        'total_amount',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'from' => 'date',
            'to' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('replenishment')
            ->logOnly(['from', 'to', 'total_amount', 'description', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function petties(): HasMany
    {
        return $this->hasMany(Petty::class);
    }
}
