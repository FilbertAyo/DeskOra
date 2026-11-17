<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InHouseMeetingActionItem extends Model
{
    use HasFactory, HasShortId;

    public const STATUS_OPEN = 'open';

    public const STATUS_DONE = 'done';

    protected $table = 'in_house_meeting_action_items';

    protected $fillable = [
        'meeting_id',
        'title',
        'assignee_id',
        'due_date',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(InHouseMeeting::class, 'meeting_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
