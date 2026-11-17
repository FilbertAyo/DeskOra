<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InHouseMeeting extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public const TYPE_WEEKLY = 'weekly';

    public const TYPE_ADHOC = 'adhoc';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    protected $table = 'in_house_meetings';

    protected $fillable = [
        'organizer_id',
        'title',
        'agenda',
        'starts_at',
        'ends_at',
        'location',
        'join_url',
        'meeting_type',
        'period_id',
        'status',
        'minutes_notes',
        'discussion_points',
        'decisions',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('in_house_meetings')
            ->logOnly([
                'organizer_id',
                'title',
                'agenda',
                'starts_at',
                'ends_at',
                'location',
                'join_url',
                'meeting_type',
                'period_id',
                'status',
                'minutes_notes',
                'discussion_points',
                'decisions',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "In-house meeting {$eventName}");
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(WorkPeriod::class, 'period_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(InHouseMeetingParticipant::class, 'meeting_id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(InHouseMeetingActionItem::class, 'meeting_id')->orderBy('sort_order');
    }

    public function isOrganizer(User $user): bool
    {
        return (string) $this->organizer_id === (string) $user->id;
    }

    public function isParticipant(User $user): bool
    {
        if ($this->relationLoaded('participants')) {
            return $this->participants->contains(fn (InHouseMeetingParticipant $row) => (string) $row->user_id === (string) $user->id);
        }

        return $this->participants()->where('user_id', $user->id)->exists();
    }

    public function participantFor(User $user): ?InHouseMeetingParticipant
    {
        if ($this->relationLoaded('participants')) {
            return $this->participants->first(fn (InHouseMeetingParticipant $row) => (string) $row->user_id === (string) $user->id);
        }

        return $this->participants()->where('user_id', $user->id)->first();
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $builder) use ($user) {
            $builder
                ->where('organizer_id', $user->id)
                ->orWhere('meeting_type', self::TYPE_WEEKLY)
                ->orWhereHas('participants', fn (Builder $participants) => $participants->where('user_id', $user->id));
        });
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_SCHEDULED)
            ->where('ends_at', '>=', now());
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where(function (Builder $builder) {
            $builder
                ->where('status', self::STATUS_COMPLETED)
                ->orWhere('status', self::STATUS_CANCELLED)
                ->orWhere(function (Builder $ended) {
                    $ended
                        ->where('status', self::STATUS_SCHEDULED)
                        ->where('ends_at', '<', now());
                });
        });
    }

    /**
     * @return array{accepted: int, declined: int, pending: int, total: int}
     */
    public function rsvpSummary(): array
    {
        $participants = $this->relationLoaded('participants')
            ? $this->participants
            : $this->participants()->get();

        $accepted = $participants->where('response', InHouseMeetingParticipant::RESPONSE_ACCEPTED)->count();
        $declined = $participants->where('response', InHouseMeetingParticipant::RESPONSE_DECLINED)->count();
        $pending = $participants->where('response', InHouseMeetingParticipant::RESPONSE_PENDING)->count();

        return [
            'accepted' => $accepted,
            'declined' => $declined,
            'pending' => $pending,
            'total' => $participants->count(),
        ];
    }
}
