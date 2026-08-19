<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryBorrowRequest extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public const TYPE_ASSET = 'asset';

    public const TYPE_STOCK = 'stock';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_RETURNED = 'returned';

    public const RETURN_GOOD = 'good';

    public const RETURN_DAMAGED = 'damaged';

    public const RETURN_MISSING = 'missing';

    protected $fillable = [
        'request_number',
        'item_type',
        'asset_id',
        'stock_item_id',
        'quantity',
        'borrower_user_id',
        'purpose',
        'expected_return_date',
        'status',
        'requested_by_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
        'issued_by_user_id',
        'issued_at',
        'returned_by_user_id',
        'returned_at',
        'quantity_returned',
        'return_condition',
        'return_notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_return_date' => 'date',
            'reviewed_at' => 'datetime',
            'issued_at' => 'datetime',
            'returned_at' => 'datetime',
            'quantity' => 'integer',
            'quantity_returned' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory_borrow_requests')
            ->logOnly(['request_number', 'status', 'item_type', 'asset_id', 'stock_item_id', 'quantity', 'expected_return_date'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function asset()
    {
        return $this->belongsTo(InventoryAsset::class, 'asset_id');
    }

    public function stockItem()
    {
        return $this->belongsTo(InventoryStockItem::class, 'stock_item_id');
    }

    public function borrower()
    {
        return $this->belongsTo(User::class, 'borrower_user_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function returnedBy()
    {
        return $this->belongsTo(User::class, 'returned_by_user_id');
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_ISSUED
            && $this->expected_return_date
            && $this->expected_return_date->lt(now()->startOfDay());
    }

    public static function pendingReviewCount(): int
    {
        return static::query()->where('status', self::STATUS_REQUESTED)->count();
    }

    public static function overdueCount(): int
    {
        return static::query()
            ->where('status', self::STATUS_ISSUED)
            ->whereDate('expected_return_date', '<', now()->toDateString())
            ->count();
    }

    public function itemName(): string
    {
        return $this->item_type === self::TYPE_ASSET
            ? ($this->asset?->name ?? 'Asset')
            : ($this->stockItem?->name ?? 'Stock item');
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_REQUESTED => 'Requested',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_ISSUED => 'Issued',
            self::STATUS_RETURNED => 'Returned',
        ];
    }

    public static function returnConditionOptions(): array
    {
        return [
            self::RETURN_GOOD => 'Good',
            self::RETURN_DAMAGED => 'Damaged',
            self::RETURN_MISSING => 'Missing',
        ];
    }
}
