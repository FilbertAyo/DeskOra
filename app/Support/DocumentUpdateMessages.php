<?php

namespace App\Support;

use App\Models\Document;

class DocumentUpdateMessages
{
    public static function closedPeriodMessage(Document $document): string
    {
        $periodLabel = $document->period
            ? $document->period->display_name
            : 'that work period';

        $typeLabel = match ($document->type) {
            Document::TYPE_WEEKLY_REPORT => 'weekly report',
            Document::TYPE_WEEKLY_PLAN => 'weekly plan',
            Document::TYPE_ACTION_LOG => 'action log',
            Document::TYPE_MONTHLY_REPORT => 'monthly report',
            default => 'document',
        };

        return "This {$typeLabel} draft is for {$periodLabel}. That work period is now closed, so you can no longer edit or submit it. Please contact your administrator if you still need to complete it.";
    }

    public static function alreadySubmittedMessage(): string
    {
        return 'This document has already been submitted and can no longer be edited.';
    }

    public static function readOnlyAccessMessage(): string
    {
        return 'Your account has read-only access to documents and cannot make changes.';
    }

    public static function genericDeniedMessage(): string
    {
        return 'You do not have permission to edit this document.';
    }

    public static function isClosedPeriodMessage(string $message): bool
    {
        return str_contains($message, 'work period is now closed');
    }
}
