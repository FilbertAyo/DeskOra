<?php

namespace App\Http\Controllers;

use App\Services\AiAssistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAssistController extends Controller
{
    public function __construct(private readonly AiAssistService $aiAssistService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
            'action' => ['required', 'string', 'in:improve,expand'],
        ]);

        try {
            $result = $this->aiAssistService->assist(
                $validated['text'],
                $validated['action']
            );
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }
}
