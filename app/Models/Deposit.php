<?php

namespace App\Models;

use App\Concerns\HasShortId;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Deposit extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('finance')
            ->logOnly(['deposit', 'user_id', 'department_id', 'description', 'remaining'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'deposit',
        'user_id',
        'department_id',
        'description',
        'created_at',
        'remaining',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
