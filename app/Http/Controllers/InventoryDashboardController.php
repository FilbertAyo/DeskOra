<?php

namespace App\Http\Controllers;

use App\Models\InventoryAsset;
use App\Models\InventoryBorrowRequest;
use App\Models\InventoryDispatch;
use App\Models\InventoryDisposalRequest;
use App\Models\InventoryMaintenanceRecord;
use App\Models\InventoryReceipt;
use App\Models\InventoryStockItem;
use App\Models\InventoryVerificationSession;
use Inertia\Inertia;
use Inertia\Response;

class InventoryDashboardController extends Controller
{
    public function index(): Response
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
            'open_verification_count' => InventoryVerificationSession::openCount(),
        ];

        $assetStatusCounts = InventoryAsset::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $assetHealth = collect(InventoryAsset::statusOptions())
            ->map(fn (string $label, string $status) => [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($assetStatusCounts[$status] ?? 0),
            ])
            ->values()
            ->all();

        $lowStockItems = InventoryStockItem::query()
            ->where('status', 'active')
            ->where('minimum_level', '>', 0)
            ->whereColumn('quantity_remaining', '<=', 'minimum_level')
            ->orderBy('quantity_remaining')
            ->limit(6)
            ->get(['id', 'name', 'unit', 'quantity_remaining', 'minimum_level'])
            ->map(fn (InventoryStockItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unit,
                'quantity_remaining' => (int) $item->quantity_remaining,
                'minimum_level' => (int) $item->minimum_level,
            ])
            ->all();

        $recentReceipts = InventoryReceipt::query()
            ->with(['supplier:id,name', 'donor:id,name', 'project:id,name'])
            ->latest('received_at')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (InventoryReceipt $receipt) => [
                'id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'source_type' => $receipt->source_type,
                'source_label' => InventoryReceipt::sourceTypeOptions()[$receipt->source_type] ?? $receipt->source_type,
                'received_at' => optional($receipt->received_at)?->toDateString(),
                'partner' => $receipt->supplier?->name
                    ?? $receipt->donor?->name
                    ?? $receipt->project?->name
                    ?? '—',
            ])
            ->all();

        $user = auth()->user();

        return Inertia::render('Inventory/Index', [
            'stats' => $stats,
            'assetHealth' => $assetHealth,
            'lowStockItems' => $lowStockItems,
            'recentReceipts' => $recentReceipts,
            'can' => [
                'create_assets' => $user?->can('manage inventory assets') ?? false,
                'create_receipts' => $user?->can('manage inventory receipts') ?? false,
                'manage_stock' => $user?->can('manage inventory stock') ?? false,
                'view_my_items' => $user?->can('view own inventory items') ?? false,
            ],
        ]);
    }
}
