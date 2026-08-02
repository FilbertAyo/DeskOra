<?php

namespace App\Data;

class WeeklyBudgetMailContext
{
    public function __construct(
        public readonly string $event,
        public readonly string $subject,
        public readonly string $headline,
        public readonly string $intro,
        public readonly string $periodLabel,
        public readonly string $submitterName,
        public readonly ?string $departmentName,
        public readonly string $actionUrl,
        public readonly string $actionLabel,
        public readonly string $view,
        public readonly ?string $actorName = null,
        public readonly ?string $itemLabel = null,
        public readonly ?string $note = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toViewData(?string $recipientName = null): array
    {
        return [
            'context' => $this,
            'recipientName' => $recipientName,
            'headline' => $this->headline,
            'intro' => $this->intro,
            'periodLabel' => $this->periodLabel,
            'submitterName' => $this->submitterName,
            'departmentName' => $this->departmentName,
            'actionUrl' => $this->actionUrl,
            'actionLabel' => $this->actionLabel,
            'actorName' => $this->actorName,
            'itemLabel' => $this->itemLabel,
            'note' => $this->note,
        ];
    }
}
