<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Petty extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('petty')
            ->logOnly([
                'user_id',
                'department_id',
                'code',
                'request_for',
                'comment',
                'amount',
                'reason',
                'request_type',
                'status',
                'replenishment_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = ['user_id', 'trans_mode_id', 'department_id', 'code', 'request_for', 'comment', 'amount', 'reason', 'request_type', 'paid_date', 'attachment', 'is_transporter', 'status', 'replenishment_id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function lists()
    {
        return $this->hasMany(PettyList::class, 'petty_id');
    }

    public function attachments()
    {
        return $this->hasMany(PettyAttachment::class, 'petty_id');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class, 'petty_id');
    }

    public function approvalLogs()
    {
        return $this->hasMany(ApprovalLog::class);
    }

    public function transMode()
    {
        return $this->belongsTo(TransMode::class, 'trans_mode_id');
    }

    public function items()
    {
        return $this->hasMany(PettyList::class, 'petty_id');
    }
}
