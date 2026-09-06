<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PettyRequest extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('petty_requests')
            ->logOnly([
                'request_id',
                'replenishment_id',
                'request_for',
                'amount',
                'reason',
                'is_transporter',
                'attachment',
                'paid_date',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Petty cash request {$eventName}");
    }

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
