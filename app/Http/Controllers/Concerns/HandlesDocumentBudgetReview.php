<?php

namespace App\Http\Controllers\Concerns;

use App\Exports\WeeklyBudgetReviewExport;
use App\Models\Document;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanBudgetItem;
use App\Models\WeeklyPlanBudgetReview;
use App\Models\WorkPeriod;
use App\Services\PendingApprovalGate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Inertia\Inertia;

/**
 * Weekly plan budget review panel, exports and per-item decisions.
 */
trait HandlesDocumentBudgetReview
{
    /**
     * Weekly budget review panel.
     */
    public function weeklyBudgetReviewPanel(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $user->hasAnyPermission([
            'view weekly budgets',
            'approve documents',
            'review weekly budgets',
            'approve weekly budgets',
            'authorize weekly budgets',
        ])) {
            abort(403, 'Unauthorized access.');
        }

        $gateState = app(PendingApprovalGate::class)->queueState($user, $request->session());
        if ($gateState['has_pending'] && $gateState['budget_remaining'] > 0
            && ! $request->has('status')
            && ! $request->filled('period_id')) {
            return redirect()->route('documents.weekly-budget-review', ['status' => 'pending']);
        }

        $defaultPeriod = $this->resolveWeeklyBudgetReviewDefaultPeriod($request);
        $statusFilter = $request->input('status');
        $query = $this->buildWeeklyBudgetReviewQuery($request, $defaultPeriod, $statusFilter);

        if (! $this->weeklyBudgetReviewService->isAllPendingWeeksView($request)
            && ! ($request->has('period_id') && ! $request->filled('period_id'))) {
            $query->latest();
        }

        $documents = $query->paginate(100)->withQueryString();
        $documents->setCollection(
            $documents->getCollection()->filter(fn (Document $document) => $document->hasBudgetItems())->values()
        );

        // Build grouped data: department -> staff -> budget items
        $grouped = $this->groupBudgetDocumentsByDepartment($documents->getCollection(), $user);
        $aggregateStats = $this->weeklyBudgetReviewService->computeBudgetStatsFromGrouped($grouped);

        $usersWithBudgets = User::query()
            ->whereHas('documents', function ($documentsQuery) {
                $documentsQuery
                    ->weeklyPlans()
                    ->submitted()
                    ->where(function ($budgetQuery) {
                        $budgetQuery
                            ->whereHas('weeklyPlanDomain.budgetItems')
                            ->orWhere(function ($legacyQuery) {
                                $legacyQuery
                                    ->whereNotNull('budget_review_status')
                                    ->orWhere('content', 'like', '%"budget"%');
                            });
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $periodOptions = WorkPeriod::orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->take(52)
            ->get();

        $financeTargetUsers = User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $currentBudgetEntryPeriod = WorkPeriod::getCurrent();
        $years = WorkPeriod::select('year')->distinct()->orderBy('year', 'desc')->pluck('year');
        $activePeriodId = $request->filled('period_id')
            ? (string) $request->string('period_id')
            : ($defaultPeriod?->id ? (string) $defaultPeriod->id : null);

        return Inertia::render('Documents/WeeklyBudgetReview', [
            'documents' => $documents->through(fn (Document $document) => array_merge(
                $this->documentPayload($document),
                [
                    'budgetItems' => $document->budgetItems(),
                    'budgetReviews' => $document->budgetReviewItemsByUid(),
                ]
            )),
            'grouped' => $grouped,
            'stats' => $aggregateStats,
            'usersWithBudgets' => $usersWithBudgets->map(fn (User $budgetUser) => [
                'id' => (string) $budgetUser->id,
                'name' => $budgetUser->name,
            ])->values(),
            'periodOptions' => $periodOptions->map(fn (WorkPeriod $period) => [
                'id' => (string) $period->id,
                'weekNumber' => $period->week_number,
                'year' => $period->year,
                'dateRange' => $period->date_range,
            ])->values(),
            'financeTargetUsers' => $financeTargetUsers->map(fn (User $targetUser) => [
                'id' => (string) $targetUser->id,
                'name' => $targetUser->name,
            ])->values(),
            'currentBudgetEntryPeriod' => $currentBudgetEntryPeriod ? [
                'id' => (string) $currentBudgetEntryPeriod->id,
                'weekNumber' => $currentBudgetEntryPeriod->week_number,
                'year' => $currentBudgetEntryPeriod->year,
                'dateRange' => $currentBudgetEntryPeriod->date_range,
            ] : null,
            'years' => $years,
            'statusFilter' => $statusFilter,
            'defaultPeriod' => $defaultPeriod ? [
                'id' => (string) $defaultPeriod->id,
                'weekNumber' => $defaultPeriod->week_number,
                'year' => $defaultPeriod->year,
                'dateRange' => $defaultPeriod->date_range,
            ] : null,
            'activePeriodId' => $activePeriodId,
            'approvalFlowSteps' => \App\Support\WeeklyBudgetApprovalFlow::steps(),
            'userApprovalActions' => \App\Support\WeeklyBudgetApprovalFlow::userActionSummary($user),
            'showingAllPendingWeeks' => $this->weeklyBudgetReviewService->isAllPendingWeeksView($request)
                || ($request->has('period_id') && ! $request->filled('period_id')),
            'filters' => $request->only(['period_id', 'status', 'user_id', 'year']),
            'indexUrl' => route('documents.weekly-budget-review'),
            'exportPdfUrl' => route('documents.weekly-budget-review.export.pdf', $request->query()),
            'exportExcelUrl' => route('documents.weekly-budget-review.export.excel', $request->query()),
            'addMidweekUrl' => route('documents.weekly-budget-review.add-midweek-budget'),
        ]);
    }

    public function exportWeeklyBudgetReviewPdf(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $user->hasAnyPermission([
            'view weekly budgets',
            'approve documents',
            'review weekly budgets',
            'approve weekly budgets',
            'authorize weekly budgets',
        ])) {
            abort(403, 'Unauthorized access.');
        }

        $defaultPeriod = $this->resolveWeeklyBudgetReviewDefaultPeriod($request);
        $statusFilter = $request->input('status');
        $documents = $this->buildWeeklyBudgetReviewQuery($request, $defaultPeriod, $statusFilter)
            ->latest()
            ->get()
            ->filter(fn (Document $document) => $document->hasBudgetItems())
            ->values();

        $grouped = $this->groupBudgetDocumentsByDepartment($documents);
        $filterSummary = $this->weeklyBudgetReviewFilterSummary($request, $defaultPeriod, $statusFilter);
        $filename = $this->weeklyBudgetReviewExportFilename('pdf', $filterSummary);

        $pdf = Pdf::loadView('documents.reviewer.budget-weekly-pdf', [
            'grouped' => $grouped,
            'workPeriodRange' => $filterSummary['work_period_range'],
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    public function exportWeeklyBudgetReviewExcel(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $user->hasAnyPermission([
            'view weekly budgets',
            'approve documents',
            'review weekly budgets',
            'approve weekly budgets',
            'authorize weekly budgets',
        ])) {
            abort(403, 'Unauthorized access.');
        }

        $defaultPeriod = $this->resolveWeeklyBudgetReviewDefaultPeriod($request);
        $statusFilter = $request->input('status');
        $documents = $this->buildWeeklyBudgetReviewQuery($request, $defaultPeriod, $statusFilter)
            ->latest()
            ->get()
            ->filter(fn (Document $document) => $document->hasBudgetItems())
            ->values();

        $filterSummary = $this->weeklyBudgetReviewFilterSummary($request, $defaultPeriod, $statusFilter);
        $filename = $this->weeklyBudgetReviewExportFilename('xlsx', $filterSummary);

        return Excel::download(
            new WeeklyBudgetReviewExport($documents),
            $filename
        );
    }

    /**
     * Approve or reject a single weekly plan budget item.
     */
    public function processWeeklyBudgetReview(Request $request, Document $document)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $user->hasAnyPermission([
            'review weekly budgets',
            'approve weekly budgets',
            'authorize weekly budgets',
        ])) {
            abort(403, 'Unauthorized action.');
        }

        if ($document->type !== Document::TYPE_WEEKLY_PLAN || ! $document->hasBudgetItems()) {
            return $this->respondToBudgetReview($request, 'error', 'Only weekly plans with budget rows can be processed.');
        }

        $validated = $request->validate([
            'budget_uid' => ['required', 'string', 'max:255'],
            'decision' => ['required', 'in:approved,rejected'],
            'provided_amount' => ['nullable', 'numeric', 'min:0', 'required_if:decision,approved'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $budgetUid = trim($validated['budget_uid']);
        $decision = $validated['decision'];
        $providedAmount = $decision === Document::BUDGET_REVIEW_APPROVED
            ? (float) $validated['provided_amount']
            : 0.0;
        $note = isset($validated['note']) && trim($validated['note']) !== '' ? trim($validated['note']) : null;
        $now = now();

        $weeklyPlan = WeeklyPlan::query()
            ->where('id', $document->id)
            ->first();
        if (! $weeklyPlan) {
            // Legacy fallback path (pre-backfill/testing).
            $uidExists = collect($document->budgetItems())
                ->contains(fn ($item) => is_array($item) && ($item['budget_uid'] ?? null) === $budgetUid);
            if (! $uidExists) {
                return $this->respondToBudgetReview($request, 'error', 'Budget item not found in this plan.');
            }

            $existingReviews = is_array($document->budget_review_items) ? $document->budget_review_items : [];
            $found = false;
            foreach ($existingReviews as &$review) {
                if (is_array($review) && ($review['budget_uid'] ?? '') === $budgetUid) {
                    $currentStage = (string) ($review['approval_stage'] ?? Document::BUDGET_REVIEW_STAGE_PENDING);
                    if ($currentStage === '' || ! in_array($currentStage, Document::budgetReviewStages(), true)) {
                        $currentStage = Document::BUDGET_REVIEW_STAGE_PENDING;
                    }
                    $actionStage = $this->resolveBudgetApprovalActionStage($currentStage, $user);
                    if (! $actionStage) {
                        return $this->respondToBudgetReview(
                            $request,
                            'error',
                            'You are not allowed to process this item at its current approval stage.'
                        );
                    }

                    $newStage = $this->stageAfterAction($actionStage, $decision);
                    $review['decision'] = $decision;
                    $review['approval_stage'] = $newStage;
                    $review['provided_amount'] = $providedAmount;
                    $review['note'] = $note;
                    $review['reviewed_by'] = $user->id;
                    $review['reviewed_at'] = $now->toIso8601String();
                    if ($actionStage === 'review') {
                        $review['review_stage_by'] = $user->id;
                        $review['review_stage_at'] = $now->toIso8601String();
                        $review['review_provided_amount'] = $providedAmount;
                    } elseif ($actionStage === 'approve') {
                        $review['approve_stage_by'] = $user->id;
                        $review['approve_stage_at'] = $now->toIso8601String();
                        $review['review_provided_amount'] = $review['review_provided_amount'] ?? (float) ($review['provided_amount'] ?? 0);
                        $review['approve_provided_amount'] = $providedAmount;
                    } else {
                        // Final approval can bypass earlier steps and auto-complete them.
                        if ($decision === Document::BUDGET_REVIEW_APPROVED) {
                            $review['review_stage_by'] = $review['review_stage_by'] ?? $user->id;
                            $review['review_stage_at'] = $review['review_stage_at'] ?? $now->toIso8601String();
                            $review['approve_stage_by'] = $review['approve_stage_by'] ?? $user->id;
                            $review['approve_stage_at'] = $review['approve_stage_at'] ?? $now->toIso8601String();
                            $review['review_provided_amount'] = $review['review_provided_amount'] ?? $providedAmount;
                            $review['approve_provided_amount'] = $review['approve_provided_amount'] ?? $providedAmount;
                        }
                        $review['authorize_stage_by'] = $user->id;
                        $review['authorize_stage_at'] = $now->toIso8601String();
                    }
                    $found = true;
                    break;
                }
            }
            unset($review);
            if (! $found) {
                $actionStage = $this->resolveBudgetApprovalActionStage(Document::BUDGET_REVIEW_STAGE_PENDING, $user);
                if (! $actionStage) {
                    return $this->respondToBudgetReview(
                        $request,
                        'error',
                        'You are not allowed to process this item at its current approval stage.'
                    );
                }
                $newStage = $this->stageAfterAction($actionStage, $decision);
                $stageAmounts = [
                    'review_provided_amount' => null,
                    'approve_provided_amount' => null,
                ];
                if ($actionStage === 'review') {
                    $stageAmounts['review_provided_amount'] = $providedAmount;
                } elseif ($actionStage === 'approve') {
                    $stageAmounts['approve_provided_amount'] = $providedAmount;
                } elseif ($actionStage === 'authorize' && $decision === Document::BUDGET_REVIEW_APPROVED) {
                    $stageAmounts['review_provided_amount'] = $providedAmount;
                    $stageAmounts['approve_provided_amount'] = $providedAmount;
                }
                $existingReviews[] = array_merge([
                    'budget_uid' => $budgetUid,
                    'decision' => $decision,
                    'approval_stage' => $newStage,
                    'provided_amount' => $providedAmount,
                    'note' => $note,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => $now->toIso8601String(),
                    'review_stage_by' => ($actionStage === 'review' || ($actionStage === 'authorize' && $decision === Document::BUDGET_REVIEW_APPROVED)) ? $user->id : null,
                    'review_stage_at' => ($actionStage === 'review' || ($actionStage === 'authorize' && $decision === Document::BUDGET_REVIEW_APPROVED)) ? $now->toIso8601String() : null,
                    'approve_stage_by' => ($actionStage === 'approve' || ($actionStage === 'authorize' && $decision === Document::BUDGET_REVIEW_APPROVED)) ? $user->id : null,
                    'approve_stage_at' => ($actionStage === 'approve' || ($actionStage === 'authorize' && $decision === Document::BUDGET_REVIEW_APPROVED)) ? $now->toIso8601String() : null,
                    'authorize_stage_by' => $actionStage === 'authorize' ? $user->id : null,
                    'authorize_stage_at' => $actionStage === 'authorize' ? $now->toIso8601String() : null,
                ], $stageAmounts);
            }

            $document->budget_review_items = $existingReviews;
            $summary = $document->budgetReviewSummary();
            $planStatus = $this->determinePlanBudgetReviewStatus($summary);

            $document->update([
                'budget_review_items' => $existingReviews,
                'budget_review_status' => $planStatus,
                'budget_reviewed_by' => $user->id,
                'budget_reviewed_at' => $now,
                'budget_provided_amount' => $summary['provided_total'],
                'budget_review_note' => null,
            ]);

            $this->notifyWeeklyBudgetStageAdvance($document, $user, $newStage, $budgetUid, $note);

            return $this->respondToBudgetReview(
                $request,
                'success',
                'Budget item "'.$budgetUid.'" has been reviewed.'
            );
        }

        $budgetItem = WeeklyPlanBudgetItem::query()
            ->where('weekly_plan_id', $weeklyPlan->id)
            ->where('budget_uid', $budgetUid)
            ->first();
        if (! $budgetItem) {
            return $this->respondToBudgetReview($request, 'error', 'Budget item not found in this plan.');
        }

        $existingReview = WeeklyPlanBudgetReview::query()
            ->where('weekly_plan_budget_item_id', $budgetItem->id)
            ->first();
        $currentStage = $existingReview?->approval_stage ?? Document::BUDGET_REVIEW_STAGE_PENDING;
        $actionStage = $this->resolveBudgetApprovalActionStage((string) $currentStage, $user);
        if (! $actionStage) {
            return $this->respondToBudgetReview(
                $request,
                'error',
                'You are not allowed to process this item at its current approval stage.'
            );
        }
        $newStage = $this->stageAfterAction($actionStage, $decision);

        $updatePayload = [
            'decision' => $decision,
            'approval_stage' => $newStage,
            'provided_amount' => $providedAmount,
            'note' => $note,
            'reviewed_by' => $user->id,
            'reviewed_at' => $now,
        ];
        if ($actionStage === 'review') {
            $updatePayload['reviewed_by'] = $user->id;
            $updatePayload['reviewed_at'] = $now;
            $updatePayload['review_provided_amount'] = $providedAmount;
        } elseif ($actionStage === 'approve') {
            $updatePayload['approved_by'] = $user->id;
            $updatePayload['approved_at'] = $now;
            $updatePayload['review_provided_amount'] = $existingReview?->review_provided_amount ?? $existingReview?->provided_amount;
            $updatePayload['approve_provided_amount'] = $providedAmount;
        } else {
            if ($decision === Document::BUDGET_REVIEW_APPROVED) {
                $updatePayload['reviewed_by'] = $existingReview?->reviewed_by ?? $user->id;
                $updatePayload['reviewed_at'] = $existingReview?->reviewed_at ?? $now;
                $updatePayload['approved_by'] = $existingReview?->approved_by ?? $user->id;
                $updatePayload['approved_at'] = $existingReview?->approved_at ?? $now;
                $updatePayload['review_provided_amount'] = $existingReview?->review_provided_amount ?? $providedAmount;
                $updatePayload['approve_provided_amount'] = $existingReview?->approve_provided_amount ?? $providedAmount;
            }
            $updatePayload['authorized_by'] = $user->id;
            $updatePayload['authorized_at'] = $now;
        }

        WeeklyPlanBudgetReview::query()->updateOrCreate(
            ['weekly_plan_budget_item_id' => $budgetItem->id],
            $updatePayload
        );

        // Recompute plan-level summary from all item decisions
        $document->unsetRelation('weeklyPlanDomain');
        $summary = $document->budgetReviewSummary();
        $planStatus = $this->determinePlanBudgetReviewStatus($summary);

        $weeklyPlan->update([
            'budget_review_status' => $planStatus,
            'budget_reviewed_by' => $user->id,
            'budget_reviewed_at' => $now,
            'budget_provided_amount' => $summary['provided_total'],
            'budget_review_note' => null,
        ]);
        $document->update([
            'budget_review_status' => $planStatus,
            'budget_reviewed_by' => $user->id,
            'budget_reviewed_at' => $now,
            'budget_provided_amount' => $summary['provided_total'],
            'budget_review_note' => null,
        ]);

        $this->notifyWeeklyBudgetStageAdvance($document, $user, $newStage, $budgetUid, $note);

        $stageLabel = ucfirst($actionStage);

        return $this->respondToBudgetReview(
            $request,
            'success',
            'Budget item "'.$budgetUid.'" has passed '.$stageLabel.' stage.',
            (string) $budgetItem->id
        );
    }

    private function respondToBudgetReview(Request $request, string $type, string $message, ?string $itemId = null)
    {
        $success = $type === 'success';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => $success,
                'message' => $message,
            ], $success ? 200 : 422);
        }

        return redirect()->back()->with($type, $message);
    }

    private function notifyWeeklyBudgetStageAdvance(
        Document $document,
        User $actor,
        string $newStage,
        string $itemLabel,
        ?string $note = null,
    ): void {
        $notifier = $this->weeklyBudgetNotificationService;

        match ($newStage) {
            Document::BUDGET_REVIEW_STAGE_REVIEWED => $notifier->notifyStageApprovers(
                $document,
                'reviewed',
                $actor,
                $itemLabel,
                $note
            ),
            Document::BUDGET_REVIEW_STAGE_APPROVED => $notifier->notifyStageApprovers(
                $document,
                'approved',
                $actor,
                $itemLabel,
                $note
            ),
            Document::BUDGET_REVIEW_STAGE_AUTHORIZED => $notifier->notifyOwner(
                $document,
                'authorized',
                $actor,
                $itemLabel,
                $note
            ),
            Document::BUDGET_REVIEW_STAGE_REJECTED => $notifier->notifyOwner(
                $document,
                'rejected',
                $actor,
                $itemLabel,
                $note
            ),
            default => null,
        };
    }

    public function addMidWeekBudgetEntry(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $user->hasPermissionTo('process weekly budgets')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'user_id' => ['required', 'string', 'exists:users,id'],
            'period_id' => ['required', 'string', 'exists:work_periods,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $targetDocument = Document::query()
            ->weeklyPlans()
            ->submitted()
            ->where('user_id', (string) $validated['user_id'])
            ->where('period_id', (string) $validated['period_id'])
            ->latest()
            ->first();

        if (! $targetDocument) {
            return redirect()
                ->back()
                ->with('error', 'No submitted weekly plan found for the selected staff and work period.');
        }

        $weeklyPlan = WeeklyPlan::query()
            ->where('id', $targetDocument->id)
            ->first();
        if (! $weeklyPlan) {
            // Legacy fallback path (pre-backfill/testing).
            $content = is_array($targetDocument->content) ? $targetDocument->content : [];
            $budgetRows = is_array($content['budget'] ?? null) ? $content['budget'] : [];
            $budgetUid = (string) Str::ulid();
            $amountNumeric = (float) $validated['amount'];
            $budgetRows[] = [
                'budget_uid' => $budgetUid,
                'item' => 'Mid-week Budget',
                'description_purpose' => trim((string) $validated['reason']),
                'amount' => number_format($amountNumeric, 2, '.', ''),
            ];
            $content['budget'] = $budgetRows;
            $existingReviews = is_array($targetDocument->budget_review_items) ? $targetDocument->budget_review_items : [];
            $existingReviews[] = [
                'budget_uid' => $budgetUid,
                'decision' => Document::BUDGET_REVIEW_APPROVED,
                'approval_stage' => Document::BUDGET_REVIEW_STAGE_AUTHORIZED,
                'provided_amount' => $amountNumeric,
                'note' => null,
                'reviewed_by' => $user->id,
                'reviewed_at' => now()->toIso8601String(),
                'review_stage_by' => $user->id,
                'review_stage_at' => now()->toIso8601String(),
                'approve_stage_by' => $user->id,
                'approve_stage_at' => now()->toIso8601String(),
                'authorize_stage_by' => $user->id,
                'authorize_stage_at' => now()->toIso8601String(),
            ];
            $targetDocument->content = $content;
            $targetDocument->budget_review_items = $existingReviews;
            $summary = $targetDocument->budgetReviewSummary();
            $planStatus = $this->determinePlanBudgetReviewStatus($summary);
            $targetDocument->update([
                'content' => $content,
                'budget_review_items' => $existingReviews,
                'budget_review_status' => $planStatus,
                'budget_reviewed_by' => $user->id,
                'budget_reviewed_at' => now(),
                'budget_provided_amount' => $summary['provided_total'],
                'budget_review_note' => null,
            ]);

            return redirect()
                ->back()
                ->with('success', 'Mid-week budget entry added successfully.');
        }

        $budgetUid = (string) Str::ulid();
        $amountNumeric = (float) $validated['amount'];
        $budgetItem = WeeklyPlanBudgetItem::query()->create([
            'weekly_plan_id' => $weeklyPlan->id,
            'budget_uid' => $budgetUid,
            'item' => 'Mid-week Budget',
            'description_purpose' => trim((string) $validated['reason']),
            'amount_raw' => number_format($amountNumeric, 2, '.', ''),
            'amount_numeric' => $amountNumeric,
            'sort_order' => (int) WeeklyPlanBudgetItem::query()->where('weekly_plan_id', $weeklyPlan->id)->count(),
        ]);
        WeeklyPlanBudgetReview::query()->updateOrCreate(
            ['weekly_plan_budget_item_id' => $budgetItem->id],
            [
                'decision' => Document::BUDGET_REVIEW_APPROVED,
                'approval_stage' => Document::BUDGET_REVIEW_STAGE_AUTHORIZED,
                'provided_amount' => $amountNumeric,
                'note' => null,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'approved_by' => $user->id,
                'approved_at' => now(),
                'authorized_by' => $user->id,
                'authorized_at' => now(),
            ]
        );

        $targetDocument->unsetRelation('weeklyPlanDomain');
        $summary = $targetDocument->budgetReviewSummary();

        $planStatus = $this->determinePlanBudgetReviewStatus($summary);

        $weeklyPlan->update([
            'budget_review_status' => $planStatus,
            'budget_reviewed_by' => $user->id,
            'budget_reviewed_at' => now(),
            'budget_provided_amount' => $summary['provided_total'],
            'budget_review_note' => null,
        ]);
        $targetDocument->update([
            'budget_review_status' => $planStatus,
            'budget_reviewed_by' => $user->id,
            'budget_reviewed_at' => now(),
            'budget_provided_amount' => $summary['provided_total'],
            'budget_review_note' => null,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Mid-week budget entry added successfully.');
    }
}
