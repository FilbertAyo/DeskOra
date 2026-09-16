<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryAsset extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_BORROWED = 'borrowed';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_UNDER_REPAIR = 'under_repair';

    public const STATUS_MISSING = 'missing';

    public const STATUS_DISPOSED = 'disposed';

    public const CONDITION_NEW = 'new';

    public const CONDITION_GOOD = 'good';

    public const CONDITION_FAIR = 'fair';

    public const CONDITION_NEEDS_REPAIR = 'needs_repair';

    public const CONDITION_DAMAGED = 'damaged';

    public const SOURCE_PURCHASE = 'purchase';

    public const SOURCE_DONOR = 'donor';

    public const SOURCE_GRANT_PROJECT = 'grant_project';

    public const SOURCE_TRANSFER = 'transfer';

    public const SOURCE_OTHER = 'other';

    protected $fillable = [
        'asset_code',
        'name',
        'serial_number',
        'category_id',
        'description',
        'date_received',
        'source_type',
        'donor_id',
        'project_id',
        'supplier_id',
        'purchase_value',
        'estimated_value',
        'warranty_info',
        'location_id',
        'department_id',
        'responsible_user_id',
        'condition',
        'status',
        'notes',
        'receipt_id',
    ];

    protected function casts(): array
    {
        return [
            'date_received' => 'date',
            'purchase_value' => 'decimal:2',
            'estimated_value' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory_assets')
            ->logOnly([
                'asset_code', 'name', 'serial_number', 'category_id', 'description',
                'date_received', 'source_type', 'donor_id', 'project_id', 'supplier_id',
                'purchase_value', 'estimated_value', 'warranty_info', 'location_id',
                'department_id', 'responsible_user_id', 'condition', 'status', 'notes',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function donor()
    {
        return $this->belongsTo(InventoryDonor::class, 'donor_id');
    }

    public function project()
    {
        return $this->belongsTo(InventoryProject::class, 'project_id');
    }

    public function supplier()
    {
        return $this->belongsTo(InventorySupplier::class, 'supplier_id');
    }

    public function location()
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function assignments()
    {
        return $this->hasMany(InventoryAssetAssignment::class, 'asset_id');
    }

    public function transfers()
    {
        return $this->hasMany(InventoryAssetTransfer::class, 'asset_id');
    }

    public function receipt()
    {
        return $this->belongsTo(InventoryReceipt::class, 'receipt_id');
    }

    public function borrowRequests()
    {
        return $this->hasMany(InventoryBorrowRequest::class, 'asset_id');
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(InventoryMaintenanceRecord::class, 'asset_id');
    }

    public function disposalRequests()
    {
        return $this->hasMany(InventoryDisposalRequest::class, 'asset_id');
    }

    public function dispatchItems()
    {
        return $this->hasMany(InventoryDispatchItem::class, 'asset_id');
    }

    /**
     * Items assigned to this user personally, or to a whole department / sub-department they belong to.
     * Does not include items assigned to another person in the same unit.
     */
    public function scopeInUserCustody($query, User $user)
    {
        $scopeIds = $user->inventoryCustodyScopeIds();

        return $query->where(function ($inner) use ($user, $scopeIds) {
            $inner->where('responsible_user_id', $user->id);

            if ($scopeIds !== []) {
                $inner->orWhere(function ($unit) use ($scopeIds) {
                    $unit->whereIn('department_id', $scopeIds)
                        ->whereNull('responsible_user_id');
                });
            }

            $inner->orWhereHas('assignments', function ($assignments) use ($user, $scopeIds) {
                $assignments->whereNull('ended_at')
                    ->where(function ($assignment) use ($user, $scopeIds) {
                        $assignment->where('assigned_to_user_id', $user->id);

                        if ($scopeIds !== []) {
                            $assignment->orWhere(function ($unitAssignment) use ($scopeIds) {
                                $unitAssignment->whereNull('assigned_to_user_id')
                                    ->whereIn('assigned_to_department_id', $scopeIds);
                            });
                        }
                    });
            });
        });
    }

    public function isDisposed(): bool
    {
        return $this->status === self::STATUS_DISPOSED;
    }

    public function isDispatched(): bool
    {
        return $this->status === self::STATUS_DISPATCHED;
    }

    public function isDispatchable(): bool
    {
        return in_array($this->status, self::dispatchableStatuses(), true);
    }

    /**
     * @return list<string>
     */
    public static function dispatchableStatuses(): array
    {
        return [
            self::STATUS_AVAILABLE,
            self::STATUS_ASSIGNED,
        ];
    }

    public function currentDispatch(): ?InventoryDispatch
    {
        $item = $this->dispatchItems()
            ->whereHas('dispatch', fn ($query) => $query->whereIn('status', InventoryDispatch::activeMovementStatuses()))
            ->with('dispatch.responsibleUsers')
            ->latest()
            ->first();

        return $item?->dispatch;
    }

    public function hasOpenMaintenance(): bool
    {
        return $this->maintenanceRecords()
            ->where('status', InventoryMaintenanceRecord::STATUS_REPORTED)
            ->exists();
    }

    public function hasPendingDisposal(): bool
    {
        return $this->disposalRequests()
            ->where('status', InventoryDisposalRequest::STATUS_REQUESTED)
            ->exists();
    }

    public static function underRepairCount(): int
    {
        return static::query()->where('status', self::STATUS_UNDER_REPAIR)->count();
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_AVAILABLE => 'Available',
            self::STATUS_ASSIGNED => 'Assigned',
            self::STATUS_BORROWED => 'Borrowed',
            self::STATUS_DISPATCHED => 'Dispatched',
            self::STATUS_UNDER_REPAIR => 'Under Repair',
            self::STATUS_MISSING => 'Missing',
            self::STATUS_DISPOSED => 'Disposed',
        ];
    }

    public static function conditionOptions(): array
    {
        return [
            self::CONDITION_NEW => 'New',
            self::CONDITION_GOOD => 'Good',
            self::CONDITION_FAIR => 'Fair',
            self::CONDITION_NEEDS_REPAIR => 'Needs Repair',
            self::CONDITION_DAMAGED => 'Damaged',
        ];
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
