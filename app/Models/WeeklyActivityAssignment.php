<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyActivityAssignment extends Model
{
    use HasFactory, HasUlids;

    public const STATUS_PREVIEW = 'preview';

    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'source_document_id',
        'source_user_id',
        'assignee_user_id',
        'period_id',
        'activity_uid',
        'status',
        'activity_payload',
    ];

    protected function casts(): array
    {
        return [
            'activity_payload' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(WorkPeriod::class, 'period_id');
    }
}
