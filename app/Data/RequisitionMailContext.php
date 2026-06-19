<?php

namespace App\Data;

class RequisitionMailContext
{
    public function __construct(
        public readonly string $event,
        public readonly string $subject,
        public readonly string $headline,
        public readonly string $intro,
        public readonly string $ref,
        public readonly string $requesterName,
        public readonly string $summary,
        public readonly string $amountFormatted,
        public readonly ?string $departmentName,
        public readonly string $actionUrl,
        public readonly string $actionLabel,
        public readonly string $view,
        public readonly ?string $actorName = null,
        public readonly ?string $comment = null,
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
            'ref' => $this->ref,
            'requesterName' => $this->requesterName,
            'summary' => $this->summary,
            'amountFormatted' => $this->amountFormatted,
            'departmentName' => $this->departmentName,
            'actionUrl' => $this->actionUrl,
            'actionLabel' => $this->actionLabel,
            'actorName' => $this->actorName,
            'comment' => $this->comment,
        ];
    }
}
