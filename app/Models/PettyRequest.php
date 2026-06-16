<?php

namespace App\Models;

use App\Concerns\HasShortId;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PettyRequest extends Model
{
    use HasFactory, HasShortId;

    protected $fillable = [
        'request_id',
        'replenishment_id',
        'request_for',
        'amount',
        'reason',
        'is_transporter',
        'attachment',
        'paid_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_transporter' => 'boolean',
        'paid_date' => 'date',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }
}
