<?php

namespace App\Models;

use App\Concerns\HasShortId;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderDispatchLog extends Model
{
    use HasFactory;
    use HasShortId;

    protected $fillable = [
        'reminder_config_id',
        'user_id',
        'activity_type',
        'window_key',
        'channels',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'meta' => 'array',
        ];
    }

    public function reminderConfig(): BelongsTo
    {
        return $this->belongsTo(ReminderConfig::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
