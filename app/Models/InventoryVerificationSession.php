<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryVerificationSession extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public const STATUS_OPEN = 'open';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'session_number',
        'name',
        'location_id',
        'department_id',
        'status',
        'notes',
        'started_by_user_id',
        'started_at',
        'completed_by_user_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory_verification')
            ->logOnly(['session_number', 'name', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function location()
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by_user_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function results()
    {
        return $this->hasMany(InventoryVerificationResult::class, 'session_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public static function openCount(): int
    {
        return static::query()->where('status', self::STATUS_OPEN)->count();
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN => 'Open',
            self::STATUS_COMPLETED => 'Completed',
        ];
    }
}
