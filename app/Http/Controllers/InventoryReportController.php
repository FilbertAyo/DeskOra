<?php

namespace App\Http\Controllers;

use App\Exports\InventoryAssetsExport;
use App\Exports\InventoryStockItemsExport;
use App\Models\InventoryAsset;
use App\Models\InventoryBorrowRequest;
use App\Models\InventoryStockItem;
use App\Models\InventoryVerificationResult;
use App\Models\InventoryVerificationSession;
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

        return view('inventory.reports.index', [
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
        ]);
    }

    public function exportAssets()
    {
        $this->authorize('viewAny', InventoryAsset::class);

        return Excel::download(new InventoryAssetsExport, 'inventory-assets.xlsx');
    }

    public function exportStock()
    {
        $this->authorize('viewAny', InventoryStockItem::class);

        return Excel::download(new InventoryStockItemsExport, 'inventory-stock-items.xlsx');
    }
}
