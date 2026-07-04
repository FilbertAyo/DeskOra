<?php

namespace App\Http\Controllers;

use App\Services\AiAssistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PortalAiAssistantController extends Controller
{
    public function __construct(
        private readonly AiAssistService $aiAssistService,
    ) {}

    public function ask(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $answer = $this->aiAssistService->answerPortalQuestion(
                $validated['question'],
                $request->user()
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'question' => trim($validated['question']),
            'answer' => $answer,
        ]);
    }
}
