<?php

declare(strict_types=1);

namespace App\Data;

final readonly class PendingApprovalGateSnapshot
{
    /**
     * @param  list<array<string, mixed>>  $requisitions
     */
    public function __construct(
        public bool $blocks,
        public bool $showModal,
        public string $primaryQueue,
        public int $requisitionCount,
        public int $budgetRemaining,
        public array $requisitions,
        public ?string $requisitionActionUrl,
        public ?string $budgetActionUrl,
    ) {}

    public static function idle(): self
    {
        return new self(
            blocks: false,
            showModal: false,
            primaryQueue: 'none',
            requisitionCount: 0,
            budgetRemaining: 0,
            requisitions: [],
            requisitionActionUrl: null,
            budgetActionUrl: null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'blocks' => $this->blocks,
            'show_modal' => $this->showModal,
            'primary_queue' => $this->primaryQueue,
            'requisition_count' => $this->requisitionCount,
            'budget_remaining' => $this->budgetRemaining,
            'requisitions' => $this->requisitions,
            'requisition_action_url' => $this->requisitionActionUrl,
            'budget_action_url' => $this->budgetActionUrl,
        ];
    }
}
