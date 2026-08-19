<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryAssetAssignment extends Model
{
    use HasFactory, HasShortId;

    protected $fillable = [
        'asset_id',
        'assigned_to_user_id',
        'assigned_to_department_id',
        'assigned_by_user_id',
        'notes',
        'assigned_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(InventoryAsset::class, 'asset_id');
    }

    public function assignedToUser()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedToDepartment()
    {
        return $this->belongsTo(Department::class, 'assigned_to_department_id');
    }

    public function assignedByUser()
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
