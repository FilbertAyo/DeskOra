<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ApprovalLog extends Model
{
    use HasShortId, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('approval_logs')
            ->logOnly([
                'petty_id',
                'replenishment_id',
                'item_id',
                'user_id',
                'action',
                'comment',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Approval log {$eventName}");
    }

    protected $fillable = ['petty_id', 'replenishment_id', 'item_id', 'user_id', 'action', 'comment'];

    public function petty()
    {
        return $this->belongsTo(Petty::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
