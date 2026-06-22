<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActionLog extends Model
{
    use HasShortId, SoftDeletes;

    protected $fillable = [
        'user_id',
        'period_id',
        'title',
        'state',
        'submission_email_sent',
    ];

    protected function casts(): array
    {
        return [
            'submission_email_sent' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(WorkPeriod::class, 'period_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ActionLogTask::class)->orderBy('sort_order');
    }
}
