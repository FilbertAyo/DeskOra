<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryMaintenanceRecord extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public const STATUS_REPORTED = 'reported';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'record_number',
        'asset_id',
        'status',
        'problem_description',
        'reported_by_user_id',
        'reported_at',
        'previous_status',
        'previous_condition',
        'repair_provider',
        'repair_cost',
        'repaired_on',
        'repair_notes',
        'condition_after',
        'completed_by_user_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'completed_at' => 'datetime',
            'repaired_on' => 'date',
            'repair_cost' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory_maintenance')
            ->logOnly(['record_number', 'asset_id', 'status', 'repair_provider', 'repair_cost'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function asset()
    {
        return $this->belongsTo(InventoryAsset::class, 'asset_id');
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_REPORTED;
    }

    public static function openCount(): int
    {
        return static::query()->where('status', self::STATUS_REPORTED)->count();
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_REPORTED => 'Under Repair',
            self::STATUS_COMPLETED => 'Completed',
        ];
    }
}
