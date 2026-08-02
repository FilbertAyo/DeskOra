<?php

namespace App\Http\Controllers;

use App\Services\FeatureUsage\FeatureUsageRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureUsageEventController extends Controller
{
    public function store(Request $request, FeatureUsageRecorder $recorder): JsonResponse
    {
        $validated = $request->validate([
            'feature_key' => ['required', 'string', 'max:120'],
            'properties' => ['nullable', 'array'],
        ]);

        $recorded = $recorder->recordFeatureClick(
            user: $request->user(),
            featureKey: $validated['feature_key'],
            properties: $validated['properties'] ?? null,
        );

        if (! $recorded) {
            return response()->json([
                'message' => 'Unknown feature key.',
            ], 422);
        }

        return response()->json(['ok' => true]);
    }
}
