<?php

namespace App\Http\Controllers\Concerns;

use App\Exports\WeeklyBudgetReviewExport;
use App\Models\Document;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanBudgetItem;
use App\Models\WeeklyPlanBudgetReview;
use App\Models\WorkPeriod;
use App\Services\MonthlyReportYearPlanActivities;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

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

        return view('documents.show', compact('document', 'relatedDocument', 'relatedAction', 'reviewerPresentation', 'showWeeklyRelatedPanel', 'assignedWeeklyActivities', 'carriedActivities', 'incomingMeetings', 'teamUsers'));
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

        return view('documents.weekly-report.action-tasks', compact('document', 'actionLogProgress', 'teamUsers'));
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

        return view('documents.weekly-report.review', compact(
            'document',
            'relatedPlan',
            'content',
            'actionLogProgress',
            'hasActionTasksStep'
        ));
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
            ->with('success', 'Document submitted successfully.');
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
            ->with('warning', 'Document returned to draft. Staff can now update and re-submit after addressing the review reason.');
    }
}
