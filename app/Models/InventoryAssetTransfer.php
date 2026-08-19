<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryAssetTransfer extends Model
{
    use HasFactory, HasShortId;

    protected $fillable = [
        'asset_id',
        'from_location_id',
        'to_location_id',
        'reason',
        'transferred_by_user_id',
        'transferred_at',
    ];

    protected function casts(): array
    {
        return [
            'transferred_at' => 'datetime',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(InventoryAsset::class, 'asset_id');
    }

    public function fromLocation()
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    public function transferredByUser()
    {
        return $this->belongsTo(User::class, 'transferred_by_user_id');
    }
}
