<?php

namespace App\Http\Controllers;

use App\Services\ApprovalQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalQueueController extends Controller
{
    public function __construct(
        private readonly ApprovalQueueService $approvalQueue,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && ($user->status ?? '') === 'active', 403);
        abort_unless($this->userCanAccessQueue($user), 403);

        $filter = $request->query('filter', 'all');
        if (! in_array($filter, ['all', 'finance', 'budget'], true)) {
            $filter = 'all';
        }

        $items = $this->approvalQueue->itemsFor($user, $filter);
        $summary = $this->approvalQueue->summaryCounts($user);

        return view('approvals.queue', compact('items', 'summary', 'filter'));
    }

    public function counts(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->status ?? '') === 'active', 403);

        if (! $this->userCanAccessQueue($user)) {
            return response()->json([
                'finance' => 0,
                'budget' => 0,
                'total' => 0,
            ]);
        }

        return response()->json($this->approvalQueue->summaryCounts($user));
    }

    private function userCanAccessQueue($user): bool
    {
        return $user->can('review pettycash')
            || $user->can('approve pettycash')
            || $user->can('authorize pettycash')
            || $user->can('review staff requisition')
            || $user->can('approve staff requisition')
            || $user->can('authorize staff requisition')
            || $user->can('view weekly budgets')
            || $user->can('review weekly budgets')
            || $user->can('approve weekly budgets')
            || $user->can('authorize weekly budgets');
    }
}
