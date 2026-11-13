<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Reviewer view and submit/reject/wizard-step workflow actions.
 */
trait HandlesDocumentWorkflow
{
    /**
     * Reviewer-facing document view: same UI as {@see self::show} with related weekly plan/report panel.
     */
    public function reviewerShow(Document $document)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $user->hasPermissionTo('view all documents')) {
            abort(403, 'Unauthorized access.');
        }

        if (! in_array($document->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true)) {
            abort(403, 'This view is only available for weekly plans and weekly reports.');
        }

        $this->authorize('view', $document);

        $document->load(['user', 'period', 'sharedUsers']);
        $carriedActivities = $this->carriedActivitiesForWeeklyPlanDocument($document);
        $incomingMeetings = $this->incomingMeetingsForWeeklyPlanDocument($document);

        [$relatedDocument] = $this->resolveWeeklyRelatedDocumentContext($document, includeCreateAction: false);
        $relatedAction = null;
        $reviewerPresentation = true;
        $showWeeklyRelatedPanel = $this->shouldShowWeeklyRelatedPanel($document, $relatedDocument, $relatedAction, reviewerPresentation: true);

        if ($relatedDocument) {
            $relatedDocument->loadMissing('user');
        }

        $assignedWeeklyActivities = $this->assignedWeeklyActivitiesForWeeklyPlanDocument($document);

        $teamUsers = $this->teamUsersForSelection();

        $reportMeetingRows = [];
        if ($document->type === Document::TYPE_WEEKLY_REPORT && $document->period_id) {
            $existingMeetingRows = is_array($document->content) ? ($document->content['key_meetings'] ?? []) : [];
            $reportMeetingRows = $this->weeklyReportMeetingRowsForUserPeriod(
                (string) $document->period_id,
                (string) $document->user_id,
                is_array($existingMeetingRows) ? $existingMeetingRows : []
            );
        }

        return Inertia::render('Documents/Show', [
            'document' => $this->documentPayload($document),
            'relatedDocument' => $relatedDocument ? $this->documentPayload($relatedDocument) : null,
            'relatedAction' => $relatedAction,
            'reviewerPresentation' => $reviewerPresentation,
            'showWeeklyRelatedPanel' => $showWeeklyRelatedPanel,
            'assignedWeeklyActivities' => $assignedWeeklyActivities,
            'carriedActivities' => $carriedActivities,
            'incomingMeetings' => $incomingMeetings,
            'teamUsers' => $teamUsers->map(fn ($teamUser) => [
                'id' => (string) $teamUser->id,
                'name' => $teamUser->name,
            ])->values(),
            'reportMeetingRows' => $reportMeetingRows,
            'indexUrl' => route('documents.reviewer-dashboard'),
        ]);
    }

    /**
     * Weekly report wizard — step 2: update assigned action tasks.
     */
    public function weeklyReportActionTasks(Document $document)
    {
        $this->ensureWeeklyReportWizardDocument($document);
        $this->authorize('update', $document);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $actionLogProgress = $this->actionLogProgressService->buildForUser($user);

        if (! $this->actionLogProgressService->hasPendingUpdates($user)) {
            return redirect()->route('documents.weekly-report.review', $document);
        }

        $document->loadMissing(['user', 'period']);
        $teamUsers = $this->teamUsersForSelection();

        return Inertia::render('Documents/WeeklyReportActionTasks', [
            'document' => $this->documentPayload($document),
            'actionLogProgress' => $actionLogProgress,
            'teamUsers' => $teamUsers->map(fn ($teamUser) => [
                'id' => (string) $teamUser->id,
                'name' => $teamUser->name,
            ])->values(),
        ]);
    }

    /**
     * Weekly report wizard — step 3: review and submit.
     */
    public function weeklyReportReview(Document $document)
    {
        $this->ensureWeeklyReportWizardDocument($document);
        $this->authorize('update', $document);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($this->actionLogProgressService->hasPendingUpdates($user)) {
            return redirect()->route('documents.weekly-report.action-tasks', $document)
                ->with('error', 'Please update all assigned action tasks before reviewing your report.');
        }

        $document->loadMissing(['user', 'period', 'sharedUsers']);
        $relatedPlan = Document::findWeeklyPlanForUserAndPeriod(
            (string) $document->period_id,
            (string) $document->user_id
        );
        $content = is_array($document->content) ? $document->content : [];
        $actionLogProgress = $this->actionLogProgressService->buildForUser($user);
        $hasActionTasksStep = ! empty($actionLogProgress['activeTasks'] ?? []);

        return Inertia::render('Documents/WeeklyReportReview', [
            'document' => $this->documentPayload($document),
            'relatedPlan' => $relatedPlan ? $this->documentPayload($relatedPlan) : null,
            'content' => $content,
            'actionLogProgress' => $actionLogProgress,
            'hasActionTasksStep' => $hasActionTasksStep,
            'submitUrl' => route('documents.update', $document),
        ]);
    }

    /**
     * Submit a document (change state from draft to submitted)
     */
    public function submit(Request $request, Document $document)
    {
        /** @var \App\Models\User $actingUser */
        $actingUser = Auth::user();
        $wasSubmitted = $document->isSubmitted();

        // Authorize submit action
        $this->authorize('submit', $document);

        if ($document->type === Document::TYPE_WEEKLY_REPORT) {
            return redirect()->route('documents.weekly-report.review', $document)
                ->with('error', 'Please complete the review step to submit your weekly report.');
        }

        try {
            DB::transaction(function () use ($document) {
                $document->submit();
                $this->documentDomainSyncService->syncFromDocument($document);
                $this->syncWeeklyPlanAssignments($document);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            logger()->error('WP_SAVE_FAILED', [
                'user_id' => (string) $actingUser->id,
                'document_id' => (string) $document->id,
                'type' => (string) $document->type,
                'period_id' => (string) ($document->period_id ?? ''),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('documents.show', $document)
                ->with('error', $document->type === Document::TYPE_WEEKLY_PLAN
                    ? 'Unable to submit your weekly plan due to a server error. Please try again.'
                    : 'Unable to submit your document due to a server error. Please try again.');
        }
        if (! $wasSubmitted) {
            $document->loadMissing('period');
            $this->workflowEmailNotificationService->notifyWeeklyDocumentSubmitted($document, $actingUser);
            $this->workflowEmailNotificationService->notifyWeeklyBudgetSubmitted($document, $actingUser);
        }

        return redirect()->route('documents.show', $document)
            ->withGooeyToast('success', 'Document submitted', [
                'message' => $document->type_name.' is now submitted.',
                'details' => array_values(array_filter([
                    ['label' => 'Type', 'value' => $document->type_name],
                    $document->period
                        ? ['label' => 'Period', 'value' => $document->period->display_name]
                        : null,
                ])),
                'actions' => [
                    [
                        'label' => 'View',
                        'event' => 'gooey-navigate',
                        'icon' => 'eye',
                        'data' => ['url' => route('documents.show', $document)],
                    ],
                ],
            ]);
    }

    /**
     * Return submitted weekly plan/report to draft with required review reason.
     */
    public function reject(Request $request, Document $document)
    {
        /** @var \App\Models\User $actingUser */
        $actingUser = Auth::user();

        $this->authorize('reject', $document);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $reason = trim($validated['reason']);

        $document->revertToDraft($reason);
        $this->documentDomainSyncService->syncFromDocument($document);
        $this->syncWeeklyPlanAssignments($document);
        $document->loadMissing('user', 'period');
        $this->workflowEmailNotificationService->notifyWeeklyDocumentReturnedToDraft($document, $actingUser, $reason);

        return redirect()->route('documents.show', $document->id)
            ->withGooeyToast('warning', 'Returned to draft', [
                'message' => 'Staff can update and re-submit after addressing the review reason.',
                'details' => array_values(array_filter([
                    ['label' => 'Type', 'value' => $document->type_name],
                    $document->period
                        ? ['label' => 'Period', 'value' => $document->period->display_name]
                        : null,
                ])),
                'actions' => [
                    [
                        'label' => 'View',
                        'event' => 'gooey-navigate',
                        'icon' => 'eye',
                        'data' => ['url' => route('documents.show', $document)],
                    ],
                ],
            ]);
    }
}
