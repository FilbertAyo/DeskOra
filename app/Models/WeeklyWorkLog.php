<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyWorkLog extends Model
{
    use HasFactory, HasShortId;

    protected $fillable = [
        'user_id',
        'period_id',
        'log_date',
        'done',
        'blocker',
        'next',
        'action_task_uid',
    ];

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(WorkPeriod::class, 'period_id');
    }

    public function hasBlocker(): bool
    {
        return filled(trim((string) $this->blocker));
    }

    public function hasLinkedAction(): bool
    {
        return filled($this->action_task_uid);
    }
}
