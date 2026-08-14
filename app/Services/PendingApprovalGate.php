<?php

namespace App\Services;

use App\Data\PendingApprovalGateSnapshot;
use App\Models\Request as FinanceRequest;
use App\Models\User;
use App\Services\Documents\WeeklyBudgetReviewService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Schema;

class PendingApprovalGate
{
    public function __construct(
        private readonly RequestApprovalNavService $requestApprovalNavService,
        private readonly WeeklyBudgetReviewService $weeklyBudgetReviewService,
    ) {}

    public function snapshotFor(User $user, Session $session, bool $includeDetails = true): PendingApprovalGateSnapshot
    {
        if (! $this->userIsResponsible($user)) {
            return PendingApprovalGateSnapshot::idle();
        }

        $state = $this->queueState($user, $session);
        $requisitions = [];
        $requisitionActionUrl = null;

        if ($includeDetails && $state['requisition_count'] > 0) {
            $requisitions = $this->pendingRequisitionsFor($user)
                ->map(fn (FinanceRequest $request) => $this->presentRequisition($request, $user))
                ->all();
            $first = $requisitions[0] ?? null;
            $stage = is_array($first) && in_array($first['stage'] ?? null, FinanceRequest::APPROVAL_STAGES, true)
                ? $first['stage']
                : 'review';
            $requisitionActionUrl = route('petty.list', ['stage' => $stage, 'type' => 'requisition']);
        } elseif ($state['requisition_count'] > 0) {
            $requisitionActionUrl = route('petty.list', ['stage' => 'review', 'type' => 'requisition']);
        }

        return new PendingApprovalGateSnapshot(
            blocks: $state['show_modal'],
            showModal: $state['show_modal'],
            primaryQueue: $state['primary_queue'],
            requisitionCount: $state['requisition_count'],
            budgetRemaining: $state['budget_remaining'],
            requisitions: $requisitions,
            requisitionActionUrl: $requisitionActionUrl,
            budgetActionUrl: $state['budget_remaining'] > 0
                ? route('documents.weekly-budget-review', ['status' => 'pending'])
                : null,
        );
    }

    public function isSnoozed(Session $session): bool
    {
        return (bool) $session->get((string) config('approval_gate.session_snooze_key'), false);
    }

    public function snooze(Session $session): void
    {
        $session->put((string) config('approval_gate.session_snooze_key'), true);
    }

    public function isOnWorkRoute(?string $routeName): bool
    {
        return is_string($routeName)
            && $routeName !== ''
            && in_array($routeName, config('approval_gate.work_routes', []), true);
    }

    /**
     * @return array{
     *     primary_queue: string,
     *     requisition_count: int,
     *     budget_remaining: int,
     *     has_pending: bool,
     *     snoozed: bool,
     *     show_modal: bool
     * }
     */
    public function queueState(User $user, Session $session): array
    {
        $requisitionCount = 0;
        foreach (FinanceRequest::APPROVAL_STAGES as $stage) {
            $requisitionCount += $this->requestApprovalNavService->countForStageAndTypes(
                $user,
                $stage,
                ['Staff Requisition']
            );
        }

        $budgetRemaining = $this->weeklyBudgetReviewService->actionableItemCountFor($user);
        $hasPending = $requisitionCount > 0 || $budgetRemaining > 0;
        $snoozed = $this->isSnoozed($session);
        $primaryQueue = $requisitionCount > 0
            ? 'requisition'
            : ($budgetRemaining > 0 ? 'budget' : 'none');

        return [
            'primary_queue' => $primaryQueue,
            'requisition_count' => $requisitionCount,
            'budget_remaining' => $budgetRemaining,
            'has_pending' => $hasPending,
            'snoozed' => $snoozed,
            'show_modal' => $hasPending && ! $snoozed,
        ];
    }

    public function userIsResponsible(User $user): bool
    {
        return $user->canAny([
            'review staff requisition',
            'approve staff requisition',
            'authorize staff requisition',
            'review weekly budgets',
            'approve weekly budgets',
            'authorize weekly budgets',
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, FinanceRequest>
     */
    public function pendingRequisitionsFor(User $user)
    {
        if (! Schema::hasTable('requests')) {
            return collect();
        }

        $items = collect();

        foreach (FinanceRequest::APPROVAL_STAGES as $stage) {
            if ($this->requestApprovalNavService->countForStageAndTypes($user, $stage, ['Staff Requisition']) < 1) {
                continue;
            }

            $items = $items->merge(
                FinanceRequest::query()
                    ->with(['user', 'department', 'requisitionDetails'])
                    ->actionableForUserAtStage($user, $stage)
                    ->where('request_type', 'Staff Requisition')
                    ->orderBy('created_at')
                    ->get()
            );
        }

        return $items->unique('id')->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRequisition(FinanceRequest $request, User $user): array
    {
        $stage = $request->approvalStage();

        return [
            'id' => (string) $request->id,
            'ref' => (string) ($request->request_no ?: $request->id),
            'summary' => (string) ($request->request_for ?: 'Staff requisition'),
            'requester_name' => $request->user?->name,
            'department_name' => $request->department?->name,
            'amount' => number_format((float) $request->amount, 2),
            'stage' => $stage,
            'stage_label' => FinanceRequest::stageLabel(
                in_array($stage, FinanceRequest::APPROVAL_STAGES, true) ? $stage : 'review'
            ),
            'url' => route('petty.details', [
                'petty' => $request->id,
                'stage' => in_array($stage, FinanceRequest::APPROVAL_STAGES, true) ? $stage : 'review',
                'type' => 'requisition',
            ]),
            'can_act' => $request->userCanActAtStage($user, $stage),
        ];
    }
}
