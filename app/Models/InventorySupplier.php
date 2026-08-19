<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventorySupplier extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    protected $fillable = [
        'name',
        'contact_name',
        'phone',
        'email',
        'address',
        'notes',
        'status',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory_suppliers')
            ->logOnly(['name', 'contact_name', 'phone', 'email', 'address', 'notes', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
