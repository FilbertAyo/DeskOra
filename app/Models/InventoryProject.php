<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryProject extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'donor_id',
        'status',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory_projects')
            ->logOnly(['name', 'description', 'donor_id', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function donor()
    {
        return $this->belongsTo(InventoryDonor::class, 'donor_id');
    }
}
