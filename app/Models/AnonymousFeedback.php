<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AnonymousFeedback extends Model
{
    use HasUlids, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('anonymous_feedback')
            ->logOnly(['category', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'anonymous_feedback';

    public const CATEGORY_COMPLAINT = 'complaint';

    public const CATEGORY_SUGGESTION = 'suggestion';

    public const CATEGORY_ASSISTANCE = 'assistance';

    public const CATEGORY_OTHER = 'other';

    public const STATUS_NEW = 'new';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'category',
        'body',
        'status',
    ];

    public static function categories(): array
    {
        return [
            self::CATEGORY_COMPLAINT,
            self::CATEGORY_SUGGESTION,
            self::CATEGORY_ASSISTANCE,
            self::CATEGORY_OTHER,
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_IN_PROGRESS,
            self::STATUS_RESOLVED,
        ];
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NEW);
    }
}
