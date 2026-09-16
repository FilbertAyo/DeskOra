<?php

namespace App\Http\Controllers;

use App\Models\InventoryAsset;
use App\Models\InventoryBorrowRequest;
use App\Models\InventoryDispatch;
use App\Models\InventoryDisposalRequest;
use App\Models\InventoryMaintenanceRecord;
use App\Models\InventoryStockItem;

class InventoryDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'asset_count' => InventoryAsset::query()->count(),
            'stock_count' => InventoryStockItem::query()->count(),
            'low_stock_count' => InventoryStockItem::query()
                ->where('status', 'active')
                ->where('minimum_level', '>', 0)
                ->whereColumn('quantity_remaining', '<=', 'minimum_level')
                ->count(),
            'pending_borrow_count' => InventoryBorrowRequest::pendingReviewCount(),
            'overdue_borrow_count' => InventoryBorrowRequest::overdueCount(),
            'under_repair_count' => InventoryMaintenanceRecord::openCount(),
            'pending_disposal_count' => InventoryDisposalRequest::pendingReviewCount(),
            'open_dispatch_count' => InventoryDispatch::openMovementCount(),
            'overdue_dispatch_count' => InventoryDispatch::overdueCount(),
        ];

        return view('inventory.index', compact('stats'));
    }
}
