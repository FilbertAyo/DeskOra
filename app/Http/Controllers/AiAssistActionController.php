<?php

namespace App\Http\Controllers;

use App\Services\AiAssistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiAssistActionController extends Controller
{
    public function __construct(
        private readonly AiAssistService $aiAssist,
    ) {}

    public function status(): JsonResponse
    {
        return response()->json([
            'available' => $this->aiAssist->isConfigured(),
        ]);
    }

    public function draftWeeklyReport(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->status ?? '') === 'active', 403);

        $validated = $request->validate([
            'period_id' => ['nullable', 'string', 'max:40'],
            'activity_progress' => ['nullable', 'array'],
            'activity_progress.*.activity_output' => ['nullable', 'string', 'max:2000'],
            'activity_progress.*.progress' => ['nullable', 'string', 'max:100'],
            'activity_progress.*.remarks_challenges' => ['nullable', 'string', 'max:4000'],
            'activity_progress.*.next_step' => ['nullable', 'string', 'max:4000'],
        ]);

        $result = $this->aiAssist->draftWeeklyReportFromActionTasks(
            $user,
            $validated['period_id'] ?? null,
            is_array($validated['activity_progress'] ?? null) ? $validated['activity_progress'] : []
        );

        return response()->json($result);
    }

    public function polishPerformanceTips(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->status ?? '') === 'active', 403);

        $validated = $request->validate([
            'tips' => ['required', 'array', 'max:5'],
            'tips.*' => ['required', 'string', 'max:500'],
            'metrics' => ['nullable', 'array'],
        ]);

        $result = $this->aiAssist->polishPerformanceTips(
            $validated['tips'],
            is_array($validated['metrics'] ?? null) ? $validated['metrics'] : []
        );

        return response()->json($result);
    }

    public function suggestRejection(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->status ?? '') === 'active', 403);

        $validated = $request->validate([
            'context_type' => ['required', 'string', 'in:finance,budget,document'],
            'title' => ['nullable', 'string', 'max:500'],
            'details' => ['nullable', 'string', 'max:4000'],
            'amount' => ['nullable', 'string', 'max:100'],
            'requester' => ['nullable', 'string', 'max:200'],
            'outcome' => ['nullable', 'string', 'in:rejected,resubmission'],
        ]);

        if (! $this->aiAssist->isConfigured()) {
            return response()->json([
                'available' => false,
                'suggestion' => null,
                'message' => 'AI currently unavailable.',
            ]);
        }

        try {
            $suggestion = $this->aiAssist->suggestRejectionReason($validated);

            return response()->json([
                'available' => true,
                'suggestion' => $suggestion,
                'message' => null,
            ]);
        } catch (Throwable $exception) {
            Log::warning('AI rejection suggestion failed.', ['message' => $exception->getMessage()]);

            return response()->json([
                'available' => false,
                'suggestion' => null,
                'message' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'AI currently unavailable.',
            ]);
        }
    }
}
