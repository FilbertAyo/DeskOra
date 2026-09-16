<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryDispatchItem extends Model
{
    use HasFactory, HasShortId;

    public const TYPE_ASSET = 'asset';

    public const TYPE_STOCK = 'stock';

    public const OUTCOME_RECEIVED_GOOD = 'received_good';

    public const OUTCOME_RECEIVED_DAMAGED = 'received_damaged';

    public const OUTCOME_MISSING = 'missing';

    protected $fillable = [
        'dispatch_id',
        'item_type',
        'asset_id',
        'stock_item_id',
        'quantity',
        'original_status',
        'original_location_id',
        'original_responsible_user_id',
        'original_department_id',
        'original_condition',
        'condition_at_dispatch',
        'return_outcome',
        'quantity_returned',
        'quantity_consumed',
        'return_notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_returned' => 'integer',
            'quantity_consumed' => 'integer',
        ];
    }

    public function dispatch()
    {
        return $this->belongsTo(InventoryDispatch::class, 'dispatch_id');
    }

    public function asset()
    {
        return $this->belongsTo(InventoryAsset::class, 'asset_id');
    }

    public function stockItem()
    {
        return $this->belongsTo(InventoryStockItem::class, 'stock_item_id');
    }

    public function originalLocation()
    {
        return $this->belongsTo(InventoryLocation::class, 'original_location_id');
    }

    public function originalResponsibleUser()
    {
        return $this->belongsTo(User::class, 'original_responsible_user_id');
    }

    public function originalDepartment()
    {
        return $this->belongsTo(Department::class, 'original_department_id');
    }

    public function isAsset(): bool
    {
        return $this->item_type === self::TYPE_ASSET;
    }

    public function isStock(): bool
    {
        return $this->item_type === self::TYPE_STOCK;
    }

    public function hasReturnOutcome(): bool
    {
        if ($this->isAsset()) {
            return filled($this->return_outcome);
        }

        return $this->quantity_returned !== null && $this->quantity_consumed !== null;
    }

    public function itemName(): string
    {
        if ($this->isAsset()) {
            $asset = $this->asset;

            return $asset
                ? trim($asset->asset_code.' — '.$asset->name)
                : 'Asset';
        }

        return $this->stockItem?->name ?? 'Stock item';
    }

    public static function returnOutcomeOptions(): array
    {
        return [
            self::OUTCOME_RECEIVED_GOOD => 'Received — Good',
            self::OUTCOME_RECEIVED_DAMAGED => 'Received — Damaged',
            self::OUTCOME_MISSING => 'Missing',
        ];
    }
}
