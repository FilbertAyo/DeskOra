<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureUsageEvent extends Model
{
    use HasShortId;

    public const UPDATED_AT = null;

    public const EVENT_PAGE_VIEW = 'page_view';

    public const EVENT_FEATURE_CLICK = 'feature_click';

    protected $fillable = [
        'user_id',
        'feature_key',
        'event_type',
        'route_name',
        'path',
        'module',
        'properties',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
