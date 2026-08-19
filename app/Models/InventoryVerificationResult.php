<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryVerificationResult extends Model
{
    use HasFactory, HasShortId;

    public const RESULT_PENDING = 'pending';

    public const RESULT_FOUND = 'found';

    public const RESULT_MISSING = 'missing';

    public const RESULT_DAMAGED = 'damaged';

    public const RESULT_LOCATION_CHANGED = 'location_changed';

    public const RESULT_PERSON_CHANGED = 'person_changed';

    protected $fillable = [
        'session_id',
        'asset_id',
        'result',
        'recorded_location_id',
        'recorded_responsible_user_id',
        'notes',
        'recorded_by_user_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function session()
    {
        return $this->belongsTo(InventoryVerificationSession::class, 'session_id');
    }

    public function asset()
    {
        return $this->belongsTo(InventoryAsset::class, 'asset_id');
    }

    public function recordedLocation()
    {
        return $this->belongsTo(InventoryLocation::class, 'recorded_location_id');
    }

    public function recordedResponsibleUser()
    {
        return $this->belongsTo(User::class, 'recorded_responsible_user_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public static function resultOptions(): array
    {
        return [
            self::RESULT_PENDING => 'Not checked',
            self::RESULT_FOUND => 'Found',
            self::RESULT_MISSING => 'Missing',
            self::RESULT_DAMAGED => 'Damaged',
            self::RESULT_LOCATION_CHANGED => 'Location changed',
            self::RESULT_PERSON_CHANGED => 'Person changed',
        ];
    }
}
