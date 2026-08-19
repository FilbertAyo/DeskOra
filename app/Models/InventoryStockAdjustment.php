<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryStockAdjustment extends Model
{
    use HasFactory, HasShortId;

    public const REASON_DAMAGED = 'damaged';

    public const REASON_EXPIRED = 'expired';

    public const REASON_MISCOUNT = 'miscount_correction';

    public const REASON_TRANSFERRED = 'transferred';

    public const REASON_OTHER = 'other';

    protected $fillable = [
        'stock_item_id',
        'direction',
        'quantity',
        'reason_code',
        'notes',
        'adjusted_by_user_id',
        'adjusted_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'adjusted_at' => 'datetime',
        ];
    }

    public function stockItem()
    {
        return $this->belongsTo(InventoryStockItem::class, 'stock_item_id');
    }

    public function adjustedByUser()
    {
        return $this->belongsTo(User::class, 'adjusted_by_user_id');
    }

    public static function reasonOptions(): array
    {
        return [
            self::REASON_DAMAGED => 'Damaged',
            self::REASON_EXPIRED => 'Expired',
            self::REASON_MISCOUNT => 'Miscount / Correction',
            self::REASON_TRANSFERRED => 'Transferred',
            self::REASON_OTHER => 'Other',
        ];
    }
}
