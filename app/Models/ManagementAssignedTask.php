<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManagementAssignedTask extends Model
{
    use HasShortId, SoftDeletes;

    public const PERMISSION = 'task assignment';

    protected $fillable = [
        'assigned_by_user_id',
        'period_id',
        'title',
        'description',
        'deadline',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
        ];
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(WorkPeriod::class, 'period_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ManagementAssignedTaskRecipient::class);
    }
}
