<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActionLogTask extends Model
{
    use HasUlids;

    protected $fillable = [
        'action_log_id',
        'task_uid',
        'task_no',
        'task',
        'progress',
        'due_date',
        'compilation_date',
        'notes_progress',
        'status_reason',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'compilation_date' => 'date',
        ];
    }

    public function actionLog(): BelongsTo
    {
        return $this->belongsTo(ActionLog::class);
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'action_log_task_assignees', 'action_log_task_id', 'user_id')
            ->withTimestamps();
    }

    public function history(): HasMany
    {
        return $this->hasMany(ActionLogTaskHistory::class)->orderBy('id');
    }
}
