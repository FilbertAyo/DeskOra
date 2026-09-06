<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Petty extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('petty')
            ->logOnly([
                'user_id',
                'department_id',
                'code',
                'request_for',
                'project_program',
                'activity_code',
                'activity_title',
                'budget_code',
                'date_of_request',
                'date_of_requirement',
                'comment',
                'amount',
                'reason',
                'request_type',
                'status',
                'replenishment_id',
                'reviewed_by_id',
                'reviewed_at',
                'approved_by_id',
                'approved_at',
                'authorized_by_id',
                'authorized_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = ['user_id', 'trans_mode_id', 'department_id', 'code', 'request_for', 'project_program', 'activity_code', 'activity_title', 'budget_code', 'date_of_request', 'date_of_requirement', 'comment', 'amount', 'reason', 'request_type', 'paid_date', 'attachment', 'is_transporter', 'status', 'replenishment_id', 'reviewed_by_id', 'reviewed_at', 'approved_by_id', 'approved_at', 'authorized_by_id', 'authorized_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'date_of_request' => 'date',
        'date_of_requirement' => 'date',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'authorized_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function approvalLogs()
    {
        return $this->hasMany(ApprovalLog::class);
    }

    public function requisitionItems()
    {
        return $this->hasMany(RequisitionItem::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function authorizedBy()
    {
        return $this->belongsTo(User::class, 'authorized_by_id');
    }
}
