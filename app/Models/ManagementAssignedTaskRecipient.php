<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagementAssignedTaskRecipient extends Model
{
    use HasShortId;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_OVERDUE = 'overdue';

    public const PROGRESS_NOT_STARTED = 'Not started';

    public const PROGRESS_IN_PROGRESS = 'In progress';

    public const PROGRESS_DONE = 'Done';

    public const PROGRESS_PARTIALLY_DONE = 'Partially done';

    protected $fillable = [
        'management_assigned_task_id',
        'assignee_user_id',
        'status',
        'progress',
        'report_feedback',
        'weekly_report_id',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ManagementAssignedTask::class, 'management_assigned_task_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function weeklyReport(): BelongsTo
    {
        return $this->belongsTo(WeeklyReport::class, 'weekly_report_id');
    }

    /**
     * @return list<string>
     */
    public static function progressOptions(): array
    {
        return [
            self::PROGRESS_NOT_STARTED,
            self::PROGRESS_IN_PROGRESS,
            self::PROGRESS_DONE,
            self::PROGRESS_PARTIALLY_DONE,
        ];
    }
}
