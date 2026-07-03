<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class NominationPeriod extends Model
{
    use HasShortId, LogsActivity;

    /**
     * Only period-level lifecycle changes are logged. Individual nominations and
     * participations are never logged, so the acting user (causer) is never
     * recorded against a nomination.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('nomination_periods')
            ->logOnly(['year', 'month', 'status', 'opens_at', 'closes_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'year',
        'month',
        'opens_at',
        'closes_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function participations(): HasMany
    {
        return $this->hasMany(NominationParticipation::class);
    }

    public function nominations(): HasMany
    {
        return $this->hasMany(Nomination::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Whether the calendar window is currently active (26th 00:00 -> month end 23:59).
     */
    public function isWindowOpenNow(?Carbon $now = null): bool
    {
        $now ??= Carbon::now(config('app.timezone'));

        return $now->betweenIncluded($this->opens_at, $this->closes_at);
    }

    /**
     * Human friendly label, e.g. "July 2026".
     */
    public function label(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    public function getLabelAttribute(): string
    {
        return $this->label();
    }
}
