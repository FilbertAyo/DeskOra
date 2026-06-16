<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Rules\DocumentShortId;
use App\Services\AiAssistService;
use App\Services\DocumentAiSummaryBuilder;
use App\Services\DocumentAiSummaryCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DocumentAiSummaryController extends Controller
{
    public function __construct(
        private readonly AiAssistService $aiAssistService,
        private readonly DocumentAiSummaryBuilder $summaryBuilder,
        private readonly DocumentAiSummaryCache $summaryCache,
    ) {}

    public function summarize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['nullable', 'string', new DocumentShortId, 'exists:documents,id'],
            'report_id' => ['nullable', 'string', new DocumentShortId, 'exists:documents,id'],
        ]);

        try {
            [$plan, $report] = $this->resolveWeeklyDocuments($validated);
            $summary = $this->summaryCache->rememberSummary(
                $plan,
                $report,
                function () use ($plan, $report): string {
                    $sourceText = $this->summaryBuilder->buildCombined($plan, $report);

                    return $this->aiAssistService->summarizeWeeklySubmission(
                        $sourceText,
                        $plan !== null,
                        $report !== null
                    );
                }
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $contextDocument = $plan ?? $report;

        return response()->json([
            'title' => $this->summaryTitle($plan, $report),
            'type' => 'Weekly Plan & Report',
            'staff' => $contextDocument?->user?->name,
            'summary' => $summary,
            'cached' => $this->summaryCache->wasLastSummaryCached(),
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['nullable', 'string', new DocumentShortId, 'exists:documents,id'],
            'report_id' => ['nullable', 'string', new DocumentShortId, 'exists:documents,id'],
            'question' => ['required', 'string', 'max:500'],
            'summary' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            [$plan, $report] = $this->resolveWeeklyDocuments($validated);
            $answer = $this->summaryCache->rememberAnswer(
                $plan,
                $report,
                $validated['question'],
                function () use ($plan, $report, $validated): string {
                    $sourceText = $this->summaryBuilder->buildCombined($plan, $report);

                    return $this->aiAssistService->answerWeeklySubmissionQuestion(
                        $sourceText,
                        $validated['question'],
                        $validated['summary'] ?? null
                    );
                }
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'question' => trim($validated['question']),
            'answer' => $answer,
            'cached' => $this->summaryCache->wasLastAnswerCached(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: ?Document, 1: ?Document}
     */
    private function resolveWeeklyDocuments(array $validated): array
    {
        if (empty($validated['plan_id']) && empty($validated['report_id'])) {
            throw new RuntimeException('A weekly plan or report is required.');
        }

        $plan = isset($validated['plan_id'])
            ? Document::query()->with(['user', 'period'])->find($validated['plan_id'])
            : null;
        $report = isset($validated['report_id'])
            ? Document::query()->with(['user', 'period'])->find($validated['report_id'])
            : null;

        if ($plan && $plan->type !== Document::TYPE_WEEKLY_PLAN) {
            throw new RuntimeException('Invalid weekly plan selected.');
        }

        if ($report && $report->type !== Document::TYPE_WEEKLY_REPORT) {
            throw new RuntimeException('Invalid weekly report selected.');
        }

        if ($plan) {
            $this->authorize('view', $plan);
        }

        if ($report) {
            $this->authorize('view', $report);
        }

        return [$plan, $report];
    }

    private function summaryTitle(?Document $plan, ?Document $report): string
    {
        $period = $plan?->period ?? $report?->period;

        if ($period) {
            return 'Week '.$period->week_number.' of '.$period->year;
        }

        return 'Weekly submission summary';
    }
}
