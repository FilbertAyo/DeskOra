<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryReceipt extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public const SOURCE_PURCHASE = 'purchase';

    public const SOURCE_DONOR = 'donor';

    public const SOURCE_GRANT_PROJECT = 'grant_project';

    public const SOURCE_TRANSFER = 'transfer';

    public const SOURCE_OTHER = 'other';

    protected $fillable = [
        'receipt_number',
        'source_type',
        'received_at',
        'supplier_id',
        'donor_id',
        'project_id',
        'location_id',
        'received_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory_receipts')
            ->logOnly([
                'receipt_number', 'source_type', 'received_at',
                'supplier_id', 'donor_id', 'project_id', 'location_id', 'notes',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function lines()
    {
        return $this->hasMany(InventoryReceiptLine::class, 'receipt_id');
    }

    public function assets()
    {
        return $this->hasMany(InventoryAsset::class, 'receipt_id');
    }

    public function supplier()
    {
        return $this->belongsTo(InventorySupplier::class, 'supplier_id');
    }

    public function donor()
    {
        return $this->belongsTo(InventoryDonor::class, 'donor_id');
    }

    public function project()
    {
        return $this->belongsTo(InventoryProject::class, 'project_id');
    }

    public function location()
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function receivedByUser()
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function isReceivedToday(): bool
    {
        return $this->received_at?->isToday() ?? false;
    }

    public static function sourceTypeOptions(): array
    {
        return [
            self::SOURCE_PURCHASE => 'Purchase',
            self::SOURCE_DONOR => 'Donor',
            self::SOURCE_GRANT_PROJECT => 'Grant / Project',
            self::SOURCE_TRANSFER => 'Transfer',
            self::SOURCE_OTHER => 'Other',
        ];
    }
}
