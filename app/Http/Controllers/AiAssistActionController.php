<?php

namespace App\Http\Controllers;

use App\Services\AiAssistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

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
        ]);

        $result = $this->aiAssist->draftWeeklyReportFromActionTasks(
            $user,
            $validated['period_id'] ?? null
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
        } catch (RuntimeException $exception) {
            return response()->json([
                'available' => false,
                'suggestion' => null,
                'message' => 'AI currently unavailable.',
            ]);
        }
    }
}
