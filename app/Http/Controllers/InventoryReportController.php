<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

use App\Exports\InventoryAssetsExport;
use App\Exports\InventoryStockItemsExport;
use App\Models\Department;
use App\Models\InventoryAsset;
use App\Models\InventoryBorrowRequest;
use App\Models\InventoryLocation;
use App\Models\InventoryStockItem;
use App\Models\InventoryVerificationResult;
use App\Models\InventoryVerificationSession;
use App\Models\User;
use App\Services\Inventory\InventoryAssignmentReportService;
use App\Support\InventoryHierarchy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class InventoryReportController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', InventoryAsset::class);

        $statusCounts = InventoryAsset::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $conditionCounts = InventoryAsset::query()
            ->select('condition', DB::raw('count(*) as total'))
            ->groupBy('condition')
            ->pluck('total', 'condition');

        $sourceCounts = InventoryAsset::query()
            ->select('source_type', DB::raw('count(*) as total'))
            ->groupBy('source_type')
            ->pluck('total', 'source_type');

        $categoryCounts = InventoryAsset::query()
            ->leftJoin('inventory_categories', 'inventory_categories.id', '=', 'inventory_assets.category_id')
            ->select(DB::raw("COALESCE(inventory_categories.name, 'Uncategorized') as category_name"), DB::raw('count(*) as total'))
            ->groupBy('category_name')
            ->orderBy('category_name')
            ->get();

        $latestSession = InventoryVerificationSession::query()
            ->where('status', InventoryVerificationSession::STATUS_COMPLETED)
            ->orderByDesc('completed_at')
            ->first();

        $missingFromLatest = collect();
        if ($latestSession) {
            $missingFromLatest = $latestSession->results()
                ->with(['asset.location', 'asset.responsibleUser'])
                ->where('result', InventoryVerificationResult::RESULT_MISSING)
                ->get();
        }

        return Inertia::render('Inventory/Reports/Index', [
            'statusCounts' => $statusCounts,
            'conditionCounts' => $conditionCounts,
            'sourceCounts' => $sourceCounts,
            'categoryCounts' => $categoryCounts,
            'statusOptions' => InventoryAsset::statusOptions(),
            'conditionOptions' => InventoryAsset::conditionOptions(),
            'sourceTypeOptions' => InventoryAsset::sourceTypeOptions(),
            'lowStockCount' => InventoryStockItem::query()
                ->where('status', 'active')
                ->where('minimum_level', '>', 0)
                ->whereColumn('quantity_remaining', '<=', 'minimum_level')
                ->count(),
            'overdueBorrowCount' => InventoryBorrowRequest::overdueCount(),
            'latestSession' => $latestSession,
            'missingFromLatest' => $missingFromLatest,
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'parent_id']),
            'staffUsers' => User::query()->active()->orderBy('name')->get(['id', 'name', 'email']),
            'inventoryPickerData' => InventoryHierarchy::pickerPayload(),
        ]);
    }

    public function exportAssets(Request $request)
    {
        $this->authorize('viewAny', InventoryAsset::class);

        $assets = $this->filteredAssetsQuery($request)->orderBy('asset_code')->get();

        return Excel::download(new InventoryAssetsExport($assets), $this->exportFilename('inventory-assets', $request).'.xlsx');
    }

    public function exportStock()
    {
        $this->authorize('viewAny', InventoryStockItem::class);

        return Excel::download(new InventoryStockItemsExport, 'inventory-stock-items.xlsx');
    }

    public function notifyAssignments(Request $request, InventoryAssignmentReportService $service)
    {
        abort_unless($request->user()?->can('manage inventory assets'), 403);

        $validated = $request->validate([
            'user_id' => ['nullable', 'string', 'short_id', 'exists:users,id'],
            'send_to_all' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('send_to_all')) {
            $result = $service->sendToAllStaff();

            return redirect()
                ->route('inventory.reports.index')
                ->with('success', "Sent {$result['sent']} assignment report(s). Skipped {$result['skipped']} staff with no assigned assets.");
        }

        if (empty($validated['user_id'])) {
            return redirect()
                ->route('inventory.reports.index')
                ->with('error', 'Select a staff member or choose to notify all staff.');
        }

        /** @var User $user */
        $user = User::query()->findOrFail($validated['user_id']);

        if (! $service->sendToUser($user)) {
            return redirect()
                ->route('inventory.reports.index')
                ->with('error', "{$user->name} has no assigned assets to report, or has no email on file.");
        }

        return redirect()
            ->route('inventory.reports.index')
            ->with('success', "Assignment report emailed to {$user->name}.");
    }

    private function filteredAssetsQuery(Request $request)
    {
        $query = InventoryAsset::query()
            ->with(['category', 'location.parent', 'department.parent', 'responsibleUser']);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        if ($request->filled('location_id')) {
            InventoryHierarchy::applyLocationFilter($query, $request->input('location_id'));
        }

        return $query;
    }

    private function exportFilename(string $prefix, Request $request): string
    {
        $parts = [$prefix, now()->format('Y-m-d')];

        if ($request->filled('department_id')) {
            $department = Department::query()->find($request->input('department_id'));
            if ($department) {
                $parts[] = 'dept-'.str($department->name)->slug('-');
            }
        }

        if ($request->filled('location_id')) {
            $location = InventoryLocation::query()->find($request->input('location_id'));
            if ($location) {
                $parts[] = 'loc-'.str($location->pathLabel())->slug('-');
            }
        }

        return implode('-', $parts);
    }
}
