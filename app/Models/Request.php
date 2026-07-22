<?php

namespace App\Models;

use App\Concerns\HasShortId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Request extends Model
{
    use HasFactory, HasShortId, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('requests')
            ->logOnly([
                'request_no',
                'request_type',
                'user_id',
                'department_id',
                'status',
                'submitted_at',
                'approved_at',
                'paid_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Request {$eventName}");
    }

    protected $fillable = [
        'request_no',
        'request_type',
        'user_id',
        'department_id',
        'status',
        'submitted_at',
        'approved_at',
        'paid_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function pettyDetails()
    {
        return $this->hasOne(PettyRequest::class, 'request_id');
    }

    public function requisitionDetails()
    {
        return $this->hasOne(RequisitionRequest::class, 'request_id');
    }

    public function requisitionItems()
    {
        return $this->hasMany(RequisitionItem::class, 'request_id');
    }

    public function approvals()
    {
        return $this->hasMany(RequestApproval::class, 'request_id');
    }

    public function approvalLogs()
    {
        return $this->approvals();
    }

    public function getCodeAttribute(): string
    {
        return (string) ($this->request_no ?: $this->id);
    }

    public function getRequestForAttribute(): ?string
    {
        if ($this->request_type === 'Staff Requisition') {
            return $this->requisitionDetails?->activity_title;
        }

        return $this->pettyDetails?->request_for;
    }

    public function getAmountAttribute(): float
    {
        if ($this->request_type === 'Staff Requisition') {
            return (float) ($this->requisitionDetails?->amount ?? 0);
        }

        return (float) ($this->pettyDetails?->amount ?? 0);
    }

    public function getReasonAttribute(): ?string
    {
        if ($this->request_type === 'Staff Requisition') {
            return $this->requisitionDetails?->reason;
        }

        return $this->pettyDetails?->reason;
    }

    public function getAttachmentAttribute(): ?string
    {
        return $this->pettyDetails?->attachment;
    }

    public function getIsTransporterAttribute(): bool
    {
        return (bool) ($this->pettyDetails?->is_transporter ?? false);
    }

    public function getPaidDateAttribute()
    {
        return $this->pettyDetails?->paid_date ?? $this->paid_at;
    }

    public function getProjectProgramAttribute(): ?string
    {
        return $this->requisitionDetails?->project_program;
    }

    public function getActivityCodeAttribute(): ?string
    {
        return $this->requisitionDetails?->activity_code;
    }

    public function getActivityTitleAttribute(): ?string
    {
        return $this->requisitionDetails?->activity_title;
    }

    public function getBudgetCodeAttribute(): ?string
    {
        return $this->requisitionDetails?->budget_code;
    }

    public function getDateOfRequestAttribute()
    {
        return $this->requisitionDetails?->date_of_request;
    }

    public function getDateOfRequirementAttribute()
    {
        return $this->requisitionDetails?->date_of_requirement;
    }

    public function getReviewedAtAttribute()
    {
        return $this->approvedAtForStep('review');
    }

    public function getApprovedAtAttribute()
    {
        return $this->approvedAtForStep('approve');
    }

    public function getAuthorizedAtAttribute()
    {
        return $this->approvedAtForStep('authorize');
    }

    public function getReviewedByAttribute()
    {
        return $this->approvedActorForStep('review');
    }

    public function getApprovedByAttribute()
    {
        return $this->approvedActorForStep('approve');
    }

    public function getAuthorizedByAttribute()
    {
        return $this->approvedActorForStep('authorize');
    }

    public function approvedAtForStep(string $step)
    {
        if ($this->relationLoaded('approvals')) {
            return optional(
                $this->approvals
                    ->where('step', $step)
                    ->where('action', 'approved')
                    ->sortByDesc('acted_at')
                    ->first()
            )->acted_at;
        }

        return optional($this->approvals()
            ->where('step', $step)
            ->where('action', 'approved')
            ->orderByDesc('acted_at')
            ->first())->acted_at;
    }

    public function approvedActorForStep(string $step): ?User
    {
        if ($this->relationLoaded('approvals')) {
            $approval = $this->approvals
                ->where('step', $step)
                ->where('action', 'approved')
                ->sortByDesc('acted_at')
                ->first();

            return $approval?->relationLoaded('actor') ? $approval->actor : optional($approval)->actor;
        }

        $approval = $this->approvals()->with('actor')->where('step', $step)->where('action', 'approved')->orderByDesc('acted_at')->first();

        return $approval?->actor;
    }

    public const APPROVAL_STAGES = ['review', 'approve', 'authorize'];

    public const REQUEST_TYPES = ['Petty Cash', 'Reimbursement', 'Staff Requisition'];

    /**
     * @return list<string>|null
     */
    public static function requestTypesForFilter(?string $filter): ?array
    {
        return match ($filter) {
            'petty' => ['Petty Cash', 'Reimbursement'],
            'requisition' => ['Staff Requisition'],
            default => null,
        };
    }

    public function scopeInDepartmentScope(Builder $query, User $user): Builder
    {
        $scopeIds = $user->departmentScopeIds();
        $parentScopeIds = Department::query()
            ->whereIn('id', $scopeIds)
            ->pluck('parent_id')
            ->filter()
            ->values()
            ->all();
        $allScopeIds = array_values(array_unique(array_merge($scopeIds, $parentScopeIds)));

        return $query->whereIn('department_id', $allScopeIds);
    }

    public function scopeAwaitingApprovalStage(Builder $query, string $stage): Builder
    {
        return match ($stage) {
            'review' => $query
                ->whereIn('status', ['pending', 'processing', 'resubmitted'])
                ->whereDoesntHave('approvals', fn (Builder $q) => $q
                    ->where('step', 'review')
                    ->where('action', 'approved')),
            'approve' => $query
                ->whereHas('approvals', fn (Builder $q) => $q
                    ->where('step', 'review')
                    ->where('action', 'approved'))
                ->whereDoesntHave('approvals', fn (Builder $q) => $q
                    ->where('step', 'approve')
                    ->where('action', 'approved')),
            'authorize' => $query
                ->whereHas('approvals', fn (Builder $q) => $q
                    ->where('step', 'approve')
                    ->where('action', 'approved'))
                ->whereDoesntHave('approvals', fn (Builder $q) => $q
                    ->where('step', 'authorize')
                    ->where('action', 'approved')),
            default => $query,
        };
    }

    public function scopeActionableForUserAtStage(Builder $query, User $user, string $stage): Builder
    {
        $types = self::requestTypesUserCanActOnAtStage($user, $stage);

        return $query
            ->inDepartmentScope($user)
            ->awaitingApprovalStage($stage)
            ->whereIn('request_type', $types);
    }

    public function approvalStage(): string
    {
        if ($this->authorized_at !== null) {
            return 'completed';
        }

        if ($this->approved_at !== null) {
            return 'authorize';
        }

        if ($this->reviewed_at !== null) {
            return 'approve';
        }

        return 'review';
    }

    public function userCanActAtStage(User $user, string $stage): bool
    {
        if ($this->approvalStage() !== $stage) {
            return false;
        }

        return $user->canAny(self::stagePermissionsForRequestType($this->request_type, $stage));
    }

    /**
     * @return list<string>
     */
    public static function stagePermissionsForRequestType(string $requestType, string $stage): array
    {
        if ($requestType === 'Staff Requisition') {
            return match ($stage) {
                'review' => ['review staff requisition'],
                'approve' => ['approve staff requisition'],
                'authorize' => ['authorize staff requisition'],
                default => [],
            };
        }

        return match ($stage) {
            'review' => ['first pettycash approval', 'review requests'],
            'approve' => ['last pettycash approval', 'approve requests'],
            'authorize' => ['authorize requests'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function permissionsForStage(string $stage): array
    {
        return match ($stage) {
            'review' => ['first pettycash approval', 'review staff requisition', 'review requests'],
            'approve' => ['last pettycash approval', 'approve staff requisition', 'approve requests'],
            'authorize' => ['authorize requests', 'authorize staff requisition'],
            default => [],
        };
    }

    public static function userCanAccessStage(User $user, string $stage): bool
    {
        return $user->canAny(self::permissionsForStage($stage));
    }

    /**
     * @return list<string>
     */
    public static function accessibleStagesFor(User $user): array
    {
        return array_values(array_filter(
            self::APPROVAL_STAGES,
            fn (string $stage) => self::userCanAccessStage($user, $stage)
        ));
    }

    /**
     * @return list<string>
     */
    public static function requestTypesUserCanActOnAtStage(User $user, string $stage): array
    {
        $types = [];

        foreach (self::REQUEST_TYPES as $type) {
            if ($user->canAny(self::stagePermissionsForRequestType($type, $stage))) {
                $types[] = $type;
            }
        }

        return $types;
    }

    public static function stageLabel(string $stage): string
    {
        return match ($stage) {
            'review' => 'Review',
            'approve' => 'Approve',
            'authorize' => 'Authorize',
            default => ucfirst($stage),
        };
    }

    public static function stageQueueTitle(string $stage): string
    {
        return match ($stage) {
            'review' => 'Review Queue',
            'approve' => 'Approve Queue',
            'authorize' => 'Authorize Queue',
            default => 'Requests Approval',
        };
    }

    public function scopeVisibleForUserAtStage(Builder $query, User $user, string $stage): Builder
    {
        $types = self::requestTypesUserCanActOnAtStage($user, $stage);

        return $query
            ->inDepartmentScope($user)
            ->whereIn('request_type', $types)
            ->where(function (Builder $q) use ($user, $stage) {
                $q->where(function (Builder $awaiting) use ($stage) {
                    $awaiting->awaitingApprovalStage($stage);
                })->orWhereHas('approvals', fn (Builder $approval) => $approval
                    ->where('step', $stage)
                    ->where('action', 'approved')
                    ->where('actor_id', $user->id));
            });
    }

    public function userHasCompletedStage(User $user, string $stage): bool
    {
        if ($this->relationLoaded('approvals')) {
            return $this->approvals
                ->where('step', $stage)
                ->where('action', 'approved')
                ->where('actor_id', $user->id)
                ->isNotEmpty();
        }

        return $this->approvals()
            ->where('step', $stage)
            ->where('action', 'approved')
            ->where('actor_id', $user->id)
            ->exists();
    }

    public static function stageEmptyMessage(string $stage): string
    {
        return match ($stage) {
            'review' => 'No review requests found.',
            'approve' => 'No approval requests found.',
            'authorize' => 'No authorization requests found.',
            default => 'No approval requests found.',
        };
    }
}
