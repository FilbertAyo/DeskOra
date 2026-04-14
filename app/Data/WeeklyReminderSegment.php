<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Document;
use App\Models\WorkPeriod;

final readonly class WeeklyReminderSegment
{
    public function __construct(
        public WorkPeriod $period,
        public ?Document $document,
        public bool $isMissed,
        public bool $showCta,
    ) {}
}
