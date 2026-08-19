<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryReceiptLine extends Model
{
    use HasFactory, HasShortId;

    public const TYPE_ASSET = 'asset';

    public const TYPE_STOCK = 'stock';

    protected $fillable = [
        'receipt_id',
        'item_type',
        'name',
        'quantity',
        'category_id',
        'stock_item_id',
        'serial_number',
        'unit',
        'minimum_level',
        'unit_value',
        'condition',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'minimum_level' => 'integer',
            'unit_value' => 'decimal:2',
        ];
    }

    public function receipt()
    {
        return $this->belongsTo(InventoryReceipt::class, 'receipt_id');
    }

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function stockItem()
    {
        return $this->belongsTo(InventoryStockItem::class, 'stock_item_id');
    }
}
