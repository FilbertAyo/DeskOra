<?php

namespace App\Services\Inventory;

use App\Models\InventoryAsset;
use App\Models\InventoryReceipt;
use App\Models\InventoryReceiptLine;
use App\Models\InventoryStockItem;
use App\Models\User;

class InventoryReceiptProcessor
{
    public function __construct(
        private readonly InventoryAssetCodeGenerator $assetCodeGenerator,
        private readonly InventoryReceiptNumberGenerator $receiptNumberGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function process(array $header, array $lines, User $user): InventoryReceipt
    {
        $receipt = InventoryReceipt::query()->create([
            'receipt_number' => $this->receiptNumberGenerator->generate(),
            'source_type' => $header['source_type'],
            'received_at' => $header['received_at'],
            'supplier_id' => $header['supplier_id'] ?? null,
            'donor_id' => $header['donor_id'] ?? null,
            'project_id' => $header['project_id'] ?? null,
            'location_id' => $header['location_id'] ?? null,
            'received_by_user_id' => $user->id,
            'notes' => $header['notes'] ?? null,
        ]);

        $this->processLines($receipt, $lines, $header);

        return $receipt->fresh(['lines']);
    }

    /**
     * Append extra lines to an existing receipt (same-day additions).
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function addLines(InventoryReceipt $receipt, array $lines): InventoryReceipt
    {
        $this->processLines($receipt, $lines, [
            'location_id' => $receipt->location_id,
        ]);

        return $receipt->fresh(['lines']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $header
     */
    private function processLines(InventoryReceipt $receipt, array $lines, array $header): void
    {
        foreach ($lines as $lineData) {
            $line = $receipt->lines()->create([
                'item_type' => $lineData['item_type'],
                'name' => $lineData['name'],
                'quantity' => (int) $lineData['quantity'],
                'category_id' => $lineData['category_id'] ?? null,
                'stock_item_id' => $lineData['stock_item_id'] ?? null,
                'serial_number' => $lineData['serial_number'] ?? null,
                'unit' => $lineData['unit'] ?? null,
                'minimum_level' => $lineData['minimum_level'] ?? null,
                'unit_value' => $lineData['unit_value'] ?? null,
                'condition' => $lineData['condition'] ?? null,
                'notes' => $lineData['notes'] ?? null,
            ]);

            if ($line->item_type === InventoryReceiptLine::TYPE_ASSET) {
                $this->createAssets($receipt, $line, $header);
            } else {
                $this->applyStock($receipt, $line, $header);
            }
        }
    }

    private function createAssets(InventoryReceipt $receipt, InventoryReceiptLine $line, array $header): void
    {
        $quantity = max(1, (int) $line->quantity);

        for ($i = 0; $i < $quantity; $i++) {
            InventoryAsset::query()->create([
                'asset_code' => $this->assetCodeGenerator->generate(),
                'name' => $line->name,
                'serial_number' => $quantity === 1 ? $line->serial_number : null,
                'category_id' => $line->category_id,
                'date_received' => $receipt->received_at,
                'source_type' => $receipt->source_type,
                'donor_id' => $receipt->donor_id,
                'project_id' => $receipt->project_id,
                'supplier_id' => $receipt->supplier_id,
                'purchase_value' => $line->unit_value,
                'location_id' => $header['location_id'] ?? null,
                'condition' => $line->condition ?: InventoryAsset::CONDITION_NEW,
                'status' => InventoryAsset::STATUS_AVAILABLE,
                'notes' => $line->notes,
                'receipt_id' => $receipt->id,
            ]);
        }
    }

    private function applyStock(InventoryReceipt $receipt, InventoryReceiptLine $line, array $header): void
    {
        $quantity = max(1, (int) $line->quantity);

        if ($line->stock_item_id) {
            $stockItem = InventoryStockItem::query()->findOrFail($line->stock_item_id);
            $stockItem->update([
                'quantity_received' => $stockItem->quantity_received + $quantity,
                'quantity_remaining' => $stockItem->quantity_remaining + $quantity,
            ]);

            $line->update(['stock_item_id' => $stockItem->id]);

            return;
        }

        $stockItem = InventoryStockItem::query()->create([
            'name' => $line->name,
            'category_id' => $line->category_id,
            'unit' => $line->unit,
            'quantity_received' => $quantity,
            'quantity_issued' => 0,
            'quantity_remaining' => $quantity,
            'minimum_level' => (int) ($line->minimum_level ?? 0),
            'location_id' => $header['location_id'] ?? null,
            'supplier_id' => $receipt->supplier_id,
            'status' => 'active',
        ]);

        $line->update(['stock_item_id' => $stockItem->id]);
    }
}
