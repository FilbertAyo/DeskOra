<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RequestApproval extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('request_approvals')
            ->logOnly([
                'request_id',
                'step',
                'action',
                'actor_id',
                'comment',
                'acted_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Request approval {$eventName}");
    }

    protected $fillable = [
        'request_id',
        'step',
        'action',
        'actor_id',
        'comment',
        'acted_at',
        'meta',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
        'meta' => 'array',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function user()
    {
        return $this->actor();
    }
}
