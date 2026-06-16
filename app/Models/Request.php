<?php

namespace App\Models;

use App\Concerns\HasShortId;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Request extends Model
{
    use HasFactory, HasShortId;

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
}
