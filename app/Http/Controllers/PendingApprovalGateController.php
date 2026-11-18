<?php

namespace App\Http\Controllers;

use App\Services\PendingApprovalGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PendingApprovalGateController extends Controller
{
    public function snapshot(Request $request, PendingApprovalGate $gate): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json(
            $gate->snapshotFor($user, $request->session())->toArray()
        );
    }

    public function remindLater(Request $request, PendingApprovalGate $gate): JsonResponse
    {
        $gate->snooze($request->session());
        // Persist before the browser navigates away from this JSON request.
        $request->session()->save();

        return response()->json(['ok' => true]);
    }
}
