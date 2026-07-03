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
        'reassigned_from_user_ids',
        'reassigned_to_user_ids',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'reassigned_from_user_ids' => 'array',
            'reassigned_to_user_ids' => 'array',
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
