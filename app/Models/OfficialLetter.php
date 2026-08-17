<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OfficialLetter extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public const SIGNATURE_MODE_DIGITAL = 'digital';

    public const SIGNATURE_MODE_MANUAL = 'manual';

    public const PERMISSION_MANAGE = 'manage official letters';

    public const PERMISSION_AUTHORIZE_DOWNLOADS = 'authorize official letter downloads';

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

    public function downloadRequests(): HasMany
    {
        return $this->hasMany(OfficialLetterDownloadRequest::class);
    }

    public function latestDownloadRequest(): HasOne
    {
        return $this->hasOne(OfficialLetterDownloadRequest::class)->latestOfMany();
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
        return $this->isOwnedBy($user) || $this->isSharedWith($user);
    }

    public function isDownloadAuthorized(): bool
    {
        if ($this->relationLoaded('latestDownloadRequest')) {
            return $this->latestDownloadRequest?->status === OfficialLetterDownloadRequest::STATUS_AUTHORIZED;
        }

        return $this->downloadRequests()
            ->where('status', OfficialLetterDownloadRequest::STATUS_AUTHORIZED)
            ->exists();
    }

    public function hasPendingDownloadRequest(): bool
    {
        if ($this->relationLoaded('latestDownloadRequest')) {
            return $this->latestDownloadRequest?->status === OfficialLetterDownloadRequest::STATUS_PENDING;
        }

        return $this->downloadRequests()
            ->where('status', OfficialLetterDownloadRequest::STATUS_PENDING)
            ->exists();
    }

    public function hasDownloadRequest(): bool
    {
        if ($this->relationLoaded('latestDownloadRequest')) {
            return $this->latestDownloadRequest !== null;
        }

        if ($this->relationLoaded('downloadRequests')) {
            return $this->downloadRequests->isNotEmpty();
        }

        return $this->downloadRequests()->exists();
    }

    public function downloadAuthorizationStatus(): ?string
    {
        $latest = $this->relationLoaded('latestDownloadRequest')
            ? $this->latestDownloadRequest
            : $this->latestDownloadRequest()->first();

        return $latest?->status;
    }

    public function userCanAuthorizeDownloads(User $user): bool
    {
        return $user->can(self::PERMISSION_AUTHORIZE_DOWNLOADS);
    }

    public function userCanReviewDownloadRequest(User $user): bool
    {
        return $this->userCanAuthorizeDownloads($user) && $this->hasDownloadRequest();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        // Only the creator and explicitly shared users can list/see full letter content.
        // Having "manage official letters" (create permission) does not reveal others' letters.
        return $query->where(function (Builder $inner) use ($user) {
            $inner->where('created_by', $user->id)
                ->orWhereHas('sharedUsers', function (Builder $shared) use ($user) {
                    $shared->where('users.id', $user->id);
                });
        });
    }

    public function scopeWithDownloadRequests(Builder $query): Builder
    {
        return $query->whereHas('downloadRequests');
    }

    public function departmentLabel(): string
    {
        $departments = config('official-letters.departments', []);

        return is_array($departments)
            ? (string) ($departments[$this->department_code] ?? $this->department_code)
            : $this->department_code;
    }
}
