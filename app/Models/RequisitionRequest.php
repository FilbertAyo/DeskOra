<?php

namespace App\Models;

use App\Concerns\HasShortId;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RequisitionRequest extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('requisition_requests')
            ->logOnly([
                'request_id',
                'project_program',
                'activity_code',
                'activity_title',
                'budget_code',
                'is_off_plan',
                'date_of_request',
                'date_of_requirement',
                'reason',
                'amount',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Staff requisition {$eventName}");
    }

    protected $fillable = [
        'request_id',
        'project_program',
        'activity_code',
        'activity_title',
        'budget_code',
        'is_off_plan',
        'date_of_request',
        'date_of_requirement',
        'reason',
        'amount',
    ];

    protected $casts = [
        'date_of_request' => 'date',
        'date_of_requirement' => 'date',
        'amount' => 'decimal:2',
        'is_off_plan' => 'boolean',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }
}
