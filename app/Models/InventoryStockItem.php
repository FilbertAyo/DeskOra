<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryStockItem extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    protected $fillable = [
        'name',
        'category_id',
        'description',
        'unit',
        'quantity_received',
        'quantity_issued',
        'quantity_remaining',
        'minimum_level',
        'location_id',
        'supplier_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'integer',
            'quantity_issued' => 'integer',
            'quantity_remaining' => 'integer',
            'minimum_level' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory_stock_items')
            ->logOnly([
                'name', 'category_id', 'description', 'unit',
                'quantity_received', 'quantity_issued', 'quantity_remaining',
                'minimum_level', 'location_id', 'supplier_id', 'status',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function location()
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function supplier()
    {
        return $this->belongsTo(InventorySupplier::class, 'supplier_id');
    }

    public function issues()
    {
        return $this->hasMany(InventoryStockIssue::class, 'stock_item_id');
    }

    public function adjustments()
    {
        return $this->hasMany(InventoryStockAdjustment::class, 'stock_item_id');
    }

    public function isLowStock(): bool
    {
        return $this->minimum_level > 0 && $this->quantity_remaining <= $this->minimum_level;
    }
}
