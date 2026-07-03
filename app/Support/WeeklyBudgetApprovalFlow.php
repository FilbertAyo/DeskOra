<?php

namespace App\Support;

use App\Models\Document;
use App\Models\User;

class WeeklyBudgetApprovalFlow
{
    public const STAGE_REVIEW = 'review';

    public const STAGE_APPROVE = 'approve';

    public const STAGE_AUTHORIZE = 'authorize';

    /**
     * @return list<array{key: string, label: string, permission: string}>
     */
    public static function steps(): array
    {
        return [
            ['key' => self::STAGE_REVIEW, 'label' => 'Review', 'permission' => 'review weekly budgets'],
            ['key' => self::STAGE_APPROVE, 'label' => 'Approve', 'permission' => 'approve weekly budgets'],
            ['key' => self::STAGE_AUTHORIZE, 'label' => 'Authorize', 'permission' => 'authorize weekly budgets'],
        ];
    }

    public static function canProcessStage(User $user, string $stage): bool
    {
        return match ($stage) {
            self::STAGE_REVIEW => $user->can('review weekly budgets'),
            self::STAGE_APPROVE => $user->can('approve weekly budgets'),
            self::STAGE_AUTHORIZE => $user->can('authorize weekly budgets'),
            default => false,
        };
    }

    public static function resolveNextActionStage(string $currentStage, User $user): ?string
    {
        if (! in_array($currentStage, Document::budgetReviewStages(), true)) {
            $currentStage = Document::BUDGET_REVIEW_STAGE_PENDING;
        }

        return match ($currentStage) {
            Document::BUDGET_REVIEW_STAGE_PENDING => self::canProcessStage($user, self::STAGE_REVIEW)
                ? self::STAGE_REVIEW
                : (self::canProcessStage($user, self::STAGE_AUTHORIZE) ? self::STAGE_AUTHORIZE : null),
            Document::BUDGET_REVIEW_STAGE_REVIEWED => self::canProcessStage($user, self::STAGE_APPROVE)
                ? self::STAGE_APPROVE
                : (self::canProcessStage($user, self::STAGE_AUTHORIZE) ? self::STAGE_AUTHORIZE : null),
            Document::BUDGET_REVIEW_STAGE_APPROVED => self::canProcessStage($user, self::STAGE_AUTHORIZE)
                ? self::STAGE_AUTHORIZE
                : null,
            default => null,
        };
    }

    public static function actionLabel(?string $nextStage): ?string
    {
        return match ($nextStage) {
            self::STAGE_REVIEW => 'Review',
            self::STAGE_APPROVE => 'Approve',
            self::STAGE_AUTHORIZE => 'Authorize',
            default => null,
        };
    }

    public static function awaitingLabel(string $approvalStage): ?string
    {
        return match ($approvalStage) {
            Document::BUDGET_REVIEW_STAGE_PENDING => 'Awaiting Review',
            Document::BUDGET_REVIEW_STAGE_REVIEWED => 'Awaiting Approval',
            Document::BUDGET_REVIEW_STAGE_APPROVED => 'Awaiting Authorization',
            default => null,
        };
    }

    public static function statusLabel(string $approvalStage, bool $isFinalApproved, bool $isRejected): string
    {
        if ($isFinalApproved) {
            return 'Approved';
        }

        if ($isRejected) {
            return 'Rejected';
        }

        return match ($approvalStage) {
            Document::BUDGET_REVIEW_STAGE_APPROVED => 'Pending Authorization',
            Document::BUDGET_REVIEW_STAGE_REVIEWED => 'Reviewed',
            default => 'Pending Review',
        };
    }

    public static function statusBadgeClass(string $approvalStage, bool $isFinalApproved, bool $isRejected): string
    {
        if ($isFinalApproved) {
            return 'bg-light-success border border-success';
        }

        if ($isRejected) {
            return 'bg-light-danger border border-danger';
        }

        return match ($approvalStage) {
            Document::BUDGET_REVIEW_STAGE_APPROVED => 'bg-light-primary border border-primary',
            Document::BUDGET_REVIEW_STAGE_REVIEWED => 'bg-light-success border border-success',
            default => 'bg-light-warning border border-warning text-dark',
        };
    }

    public static function currentStepIndex(string $approvalStage, bool $isFinalApproved, bool $isRejected): int
    {
        if ($isFinalApproved || $isRejected) {
            return 3;
        }

        return match ($approvalStage) {
            Document::BUDGET_REVIEW_STAGE_REVIEWED => 2,
            Document::BUDGET_REVIEW_STAGE_APPROVED => 3,
            default => 1,
        };
    }

    public static function userActionSummary(User $user): ?string
    {
        $roles = [];

        if ($user->can('review weekly budgets')) {
            $roles[] = 'Review';
        }
        if ($user->can('approve weekly budgets')) {
            $roles[] = 'Approve';
        }
        if ($user->can('authorize weekly budgets')) {
            $roles[] = 'Authorize';
        }

        if ($roles === []) {
            return null;
        }

        return implode(' · ', $roles);
    }

    public static function isDirectAuthorization(?string $nextStage, string $approvalStage): bool
    {
        return $nextStage === self::STAGE_AUTHORIZE
            && $approvalStage !== Document::BUDGET_REVIEW_STAGE_APPROVED;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function itemViewContext(array $item, User $user): array
    {
        $isRejected = ($item['decision'] ?? '') === Document::BUDGET_REVIEW_REJECTED
            || ($item['approval_stage'] ?? '') === Document::BUDGET_REVIEW_STAGE_REJECTED;
        $approvalStage = $item['approval_stage'] ?? Document::BUDGET_REVIEW_STAGE_PENDING;
        $isFinalApproved = ($item['decision'] ?? '') === Document::BUDGET_REVIEW_APPROVED
            && $approvalStage === Document::BUDGET_REVIEW_STAGE_AUTHORIZED;

        $nextStage = null;
        if (! $isRejected && ! $isFinalApproved) {
            $nextStage = self::resolveNextActionStage($approvalStage, $user);
        }

        $reviewAmount = $item['review_provided_amount'] ?? null;
        if ($reviewAmount === null && in_array($approvalStage, [
            Document::BUDGET_REVIEW_STAGE_REVIEWED,
            Document::BUDGET_REVIEW_STAGE_APPROVED,
            Document::BUDGET_REVIEW_STAGE_AUTHORIZED,
        ], true) && ($item['decision'] ?? '') === Document::BUDGET_REVIEW_APPROVED) {
            $reviewAmount = (float) ($item['provided_amount'] ?? 0);
        }

        $approveAmount = $item['approve_provided_amount'] ?? null;
        $displayProvided = $isFinalApproved
            ? (float) ($item['provided_amount'] ?? 0)
            : ($approveAmount ?? $reviewAmount);
        $showProvided = ! $isRejected && $displayProvided !== null && $displayProvided > 0;

        $defaultProvidedInput = $nextStage === self::STAGE_APPROVE
            ? ($reviewAmount ?? (float) ($item['amount_numeric'] ?? 0))
            : ($approveAmount ?? $reviewAmount ?? (float) ($item['amount_numeric'] ?? 0));

        return [
            'isRejected' => $isRejected,
            'approvalStage' => $approvalStage,
            'isFinalApproved' => $isFinalApproved,
            'nextStage' => $nextStage,
            'actionLabel' => self::actionLabel($nextStage),
            'awaitingLabel' => self::awaitingLabel($approvalStage),
            'statusLabel' => self::statusLabel($approvalStage, $isFinalApproved, $isRejected),
            'statusBadgeClass' => self::statusBadgeClass($approvalStage, $isFinalApproved, $isRejected),
            'currentStep' => self::currentStepIndex($approvalStage, $isFinalApproved, $isRejected),
            'isDirectAuthorization' => self::isDirectAuthorization($nextStage, $approvalStage),
            'reviewAmount' => $reviewAmount,
            'approveAmount' => $approveAmount,
            'displayProvided' => $displayProvided,
            'showProvided' => $showProvided,
            'defaultProvidedInput' => $defaultProvidedInput,
            'modalTitle' => match ($nextStage) {
                self::STAGE_REVIEW => 'Review Budget Item',
                self::STAGE_APPROVE => 'Approve Budget Item',
                self::STAGE_AUTHORIZE => 'Authorize Budget Item',
                default => 'Budget Item',
            },
            'submitLabel' => match ($nextStage) {
                self::STAGE_REVIEW => 'Save Review',
                self::STAGE_APPROVE => 'Save Approval',
                self::STAGE_AUTHORIZE => 'Save Authorization',
                default => 'Save',
            },
            'providedAmountLabel' => match ($nextStage) {
                self::STAGE_REVIEW => 'Provided Amount',
                self::STAGE_APPROVE => 'Approved Amount',
                self::STAGE_AUTHORIZE => 'Authorized Amount',
                default => 'Amount',
            },
        ];
    }
}
