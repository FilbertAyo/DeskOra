<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OfficialLetter extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public const SIGNATURE_MODE_DIGITAL = 'digital';

    public const SIGNATURE_MODE_MANUAL = 'manual';

    protected $fillable = [
        'department_code',
        'letter_date',
        'recipient',
        'subject',
        'salutation',
        'body',
        'closing',
        'signatory_id',
        'signatory_name',
        'signatory_title',
        'signature_mode',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'letter_date' => 'date',
            'sequence' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('official_letters')
            ->logOnly([
                'reference_number',
                'department_code',
                'letter_date',
                'sequence',
                'recipient',
                'subject',
                'salutation',
                'body',
                'closing',
                'signatory_id',
                'signatory_name',
                'signatory_title',
                'signature_mode',
                'created_by',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Official letter {$eventName}");
    }

    public function usesDigitalSignature(): bool
    {
        return $this->signature_mode === self::SIGNATURE_MODE_DIGITAL;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signatory(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signatory_id');
    }

    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'official_letter_user_shares')
            ->withPivot(['shared_by'])
            ->withTimestamps();
    }

    public function isSharedWith(User $user): bool
    {
        return $this->sharedUsers()
            ->where('users.id', $user->id)
            ->exists();
    }

    public function isOwnedBy(User $user): bool
    {
        return (string) $this->created_by === (string) $user->id;
    }

    public function userHasFullAccess(User $user): bool
    {
        return $user->can('manage official letters')
            || $this->isOwnedBy($user)
            || $this->isSharedWith($user);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('manage official letters')) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user) {
            $inner->where('created_by', $user->id)
                ->orWhereHas('sharedUsers', function (Builder $shared) use ($user) {
                    $shared->where('users.id', $user->id);
                });
        });
    }

    public function departmentLabel(): string
    {
        $departments = config('official-letters.departments', []);

        return is_array($departments)
            ? (string) ($departments[$this->department_code] ?? $this->department_code)
            : $this->department_code;
    }
}
