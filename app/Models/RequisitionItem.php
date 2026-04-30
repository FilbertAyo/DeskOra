<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequisitionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'petty_id',
        'serial_no',
        'description',
        'unit',
        'cost',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
    ];

    public function petty()
    {
        return $this->belongsTo(Petty::class);
    }

    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }
}
