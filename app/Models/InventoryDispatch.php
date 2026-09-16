<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryDispatch extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_RETURNING = 'returning';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'dispatch_number',
        'title',
        'purpose',
        'destination',
        'dispatch_date',
        'expected_return_date',
        'status',
        'notes',
        'created_by_user_id',
        'dispatched_by_user_id',
        'dispatched_at',
        'return_started_by_user_id',
        'return_started_at',
        'returned_by_user_id',
        'returned_at',
        'closed_by_user_id',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'dispatch_date' => 'date',
            'expected_return_date' => 'date',
            'dispatched_at' => 'datetime',
            'return_started_at' => 'datetime',
            'returned_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory_dispatches')
            ->logOnly([
                'dispatch_number', 'title', 'purpose', 'destination',
                'dispatch_date', 'expected_return_date', 'status',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function items()
    {
        return $this->hasMany(InventoryDispatchItem::class, 'dispatch_id');
    }

    public function assetItems()
    {
        return $this->items()->where('item_type', InventoryDispatchItem::TYPE_ASSET);
    }

    public function stockItems()
    {
        return $this->items()->where('item_type', InventoryDispatchItem::TYPE_STOCK);
    }

    public function responsibleUsers()
    {
        return $this->belongsToMany(User::class, 'inventory_dispatch_responsible_users', 'dispatch_id', 'user_id')
            ->withTimestamps();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function dispatchedBy()
    {
        return $this->belongsTo(User::class, 'dispatched_by_user_id');
    }

    public function returnStartedBy()
    {
        return $this->belongsTo(User::class, 'return_started_by_user_id');
    }

    public function returnedBy()
    {
        return $this->belongsTo(User::class, 'returned_by_user_id');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_READY], true);
    }

    public function canEditItems(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canConfirm(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_READY], true);
    }

    public function canStartReturn(): bool
    {
        return $this->status === self::STATUS_DISPATCHED;
    }

    public function canRecordReturn(): bool
    {
        return $this->status === self::STATUS_RETURNING;
    }

    public function canCompleteReturn(): bool
    {
        return $this->status === self::STATUS_RETURNING;
    }

    public function canClose(): bool
    {
        return $this->status === self::STATUS_RETURNED;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::openStatuses(), true);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [self::STATUS_DISPATCHED, self::STATUS_RETURNING], true)
            && $this->expected_return_date
            && $this->expected_return_date->lt(now()->startOfDay());
    }

    /**
     * @return list<string>
     */
    public static function openStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_READY,
            self::STATUS_DISPATCHED,
            self::STATUS_RETURNING,
        ];
    }

    /**
     * @return list<string>
     */
    public static function activeMovementStatuses(): array
    {
        return [
            self::STATUS_DISPATCHED,
            self::STATUS_RETURNING,
        ];
    }

    public static function overdueCount(): int
    {
        return static::query()
            ->whereIn('status', self::activeMovementStatuses())
            ->whereDate('expected_return_date', '<', now()->toDateString())
            ->count();
    }

    public static function openMovementCount(): int
    {
        return static::query()
            ->whereIn('status', self::activeMovementStatuses())
            ->count();
    }

    public function responsiblePeopleLabel(): string
    {
        $names = $this->responsibleUsers->pluck('name')->filter()->values();

        return $names->isEmpty() ? '—' : $names->join(', ');
    }

    /**
     * @return array{assets: int, stock: int, total: int}
     */
    public function itemCounts(): array
    {
        $assets = $this->items->where('item_type', InventoryDispatchItem::TYPE_ASSET)->count();
        $stock = $this->items->where('item_type', InventoryDispatchItem::TYPE_STOCK)->count();

        return [
            'assets' => $assets,
            'stock' => $stock,
            'total' => $assets + $stock,
        ];
    }

    /**
     * @return array{received_good: int, received_damaged: int, missing: int, pending: int}
     */
    public function assetReturnCounts(): array
    {
        $assets = $this->items->where('item_type', InventoryDispatchItem::TYPE_ASSET);

        return [
            'received_good' => $assets->where('return_outcome', InventoryDispatchItem::OUTCOME_RECEIVED_GOOD)->count(),
            'received_damaged' => $assets->where('return_outcome', InventoryDispatchItem::OUTCOME_RECEIVED_DAMAGED)->count(),
            'missing' => $assets->where('return_outcome', InventoryDispatchItem::OUTCOME_MISSING)->count(),
            'pending' => $assets->whereNull('return_outcome')->count(),
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_READY => 'Ready',
            self::STATUS_DISPATCHED => 'Dispatched',
            self::STATUS_RETURNING => 'Returning',
            self::STATUS_RETURNED => 'Returned',
            self::STATUS_CLOSED => 'Closed',
        ];
    }
}
