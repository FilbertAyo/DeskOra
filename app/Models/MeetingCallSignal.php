<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingCallSignal extends Model
{
    use HasShortId;

    public const TYPE_OFFER = 'offer';

    public const TYPE_ANSWER = 'answer';

    public const TYPE_ICE = 'ice';

    protected $fillable = [
        'meeting_id',
        'from_user_id',
        'to_user_id',
        'type',
        'payload',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'consumed_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(InHouseMeeting::class, 'meeting_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
