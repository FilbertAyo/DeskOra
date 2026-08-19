<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryCategory extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory_categories')
            ->logOnly(['name', 'description', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
