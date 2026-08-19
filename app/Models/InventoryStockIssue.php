<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryStockIssue extends Model
{
    use HasFactory, HasShortId;

    protected $fillable = [
        'stock_item_id',
        'quantity',
        'issued_to_user_id',
        'issued_to_department_id',
        'purpose',
        'issued_by_user_id',
        'issued_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    public function stockItem()
    {
        return $this->belongsTo(InventoryStockItem::class, 'stock_item_id');
    }

    public function issuedToUser()
    {
        return $this->belongsTo(User::class, 'issued_to_user_id');
    }

    public function issuedToDepartment()
    {
        return $this->belongsTo(Department::class, 'issued_to_department_id');
    }

    public function issuedByUser()
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }
}
