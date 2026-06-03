<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Document;
use App\Models\WorkPeriod;
use Carbon\Carbon;

/**
 * Describes whether a staff member can create or continue a weekly plan/report for a work period.
 */
final readonly class WeeklySubmissionSlot
{
    public function __construct(
        public ?WorkPeriod $period,
        public ?Document $document,
        public bool $graceActive = false,
        public ?Carbon $graceEndsAt = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->period === null;
    }

    public function canCreateNew(): bool
    {
        return $this->period !== null
            && $this->period->isOpen()
            && $this->document === null;
    }

    public function canContinueDraft(): bool
    {
        return $this->period !== null
            && $this->period->isOpen()
            && $this->document !== null
            && $this->document->isDraft();
    }

    public function showAction(): bool
    {
        return $this->canCreateNew() || $this->canContinueDraft();
    }

    public function actionUrl(string $documentType, ?string $returnTo = null): ?string
    {
        if (! $this->showAction()) {
            return null;
        }

        if ($this->document) {
            return route('documents.edit', [
                'document' => $this->document->id,
                'return_to' => $returnTo,
            ]);
        }

        return route('documents.create', [
            'type' => $documentType,
            'period_id' => $this->period->id,
            'return_to' => $returnTo,
        ]);
    }

    public function actionLabel(string $documentType): string
    {
        $weekLabel = $this->period
            ? 'Week '.$this->period->week_number
            : '';

        if ($this->document?->isReturnedForRevision()) {
            return $documentType === Document::TYPE_WEEKLY_PLAN
                ? 'Revise weekly plan'
                : 'Revise weekly report';
        }

        if ($this->document) {
            return $documentType === Document::TYPE_WEEKLY_PLAN
                ? 'Continue weekly plan draft'
                : 'Continue weekly report draft';
        }

        $base = $documentType === Document::TYPE_WEEKLY_PLAN
            ? 'Create weekly plan'
            : 'Create weekly report';

        return $weekLabel !== '' ? $base.' for '.$weekLabel : $base;
    }

    public function actionIcon(): string
    {
        return $this->document ? 'ti-edit' : 'ti-circle-plus';
    }
}
