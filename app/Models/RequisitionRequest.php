<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequisitionRequest extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'request_id',
        'project_program',
        'activity_code',
        'activity_title',
        'budget_code',
        'date_of_request',
        'date_of_requirement',
        'reason',
        'amount',
    ];

    protected $casts = [
        'date_of_request' => 'date',
        'date_of_requirement' => 'date',
        'amount' => 'decimal:2',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }
}
