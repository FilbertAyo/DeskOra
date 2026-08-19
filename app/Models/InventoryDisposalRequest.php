<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryDisposalRequest extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const METHOD_DONATED = 'donated';

    public const METHOD_SOLD = 'sold';

    public const METHOD_DESTROYED = 'destroyed';

    public const METHOD_RECYCLED = 'recycled';

    public const METHOD_OTHER = 'other';

    protected $fillable = [
        'request_number',
        'asset_id',
        'reason',
        'method',
        'status',
        'requested_by_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
        'disposed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'disposed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory_disposals')
            ->logOnly(['request_number', 'asset_id', 'status', 'method'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function asset()
    {
        return $this->belongsTo(InventoryAsset::class, 'asset_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public static function pendingReviewCount(): int
    {
        return static::query()->where('status', self::STATUS_REQUESTED)->count();
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_REQUESTED => 'Pending Review',
            self::STATUS_APPROVED => 'Approved / Disposed',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    public static function methodOptions(): array
    {
        return [
            self::METHOD_DONATED => 'Donated',
            self::METHOD_SOLD => 'Sold',
            self::METHOD_DESTROYED => 'Destroyed',
            self::METHOD_RECYCLED => 'Recycled',
            self::METHOD_OTHER => 'Other',
        ];
    }
}
