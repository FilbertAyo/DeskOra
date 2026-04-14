<?php

declare(strict_types=1);

namespace App\Data;

final readonly class WeeklySubmissionReminder
{
    /**
     * @param  list<string>  $missedMessages
     */
    public function __construct(
        public bool $showToast,
        public ?WeeklyReminderSegment $plan,
        public ?WeeklyReminderSegment $report,
        public array $missedMessages,
    ) {}
}
