<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionLogTaskHistory extends Model
{
    protected $table = 'action_log_task_history';

    protected $fillable = [
        'action_log_task_id',
        'period_id',
        'period_label',
        'updated_by',
        'from_progress',
        'to_progress',
        'notes_progress',
        'status_reason',
        'changed_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ActionLogTask::class, 'action_log_task_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(WorkPeriod::class, 'period_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
