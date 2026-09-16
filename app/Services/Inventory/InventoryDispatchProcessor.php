<?php

namespace App\Services\Inventory;

use App\Models\InventoryAsset;
use App\Models\InventoryDispatch;
use App\Models\InventoryDispatchItem;
use App\Models\InventoryMaintenanceRecord;
use App\Models\InventoryStockItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryDispatchProcessor
{
    public function __construct(
        private readonly InventoryNumberGenerator $numberGenerator,
    ) {}

    public function confirm(InventoryDispatch $dispatch, User $user): void
    {
        if (! $dispatch->canConfirm()) {
            throw ValidationException::withMessages([
                'status' => 'This dispatch cannot be confirmed in its current state.',
            ]);
        }

        $dispatch->load(['items.asset', 'items.stockItem', 'responsibleUsers']);

        if ($dispatch->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one item before confirming the dispatch.',
            ]);
        }

        if ($dispatch->responsibleUsers->isEmpty()) {
            throw ValidationException::withMessages([
                'responsible_user_ids' => 'Select at least one temporary responsible person.',
            ]);
        }

        DB::transaction(function () use ($dispatch, $user) {
            $dispatch = InventoryDispatch::query()->lockForUpdate()->findOrFail($dispatch->id);
            $dispatch->load(['items']);

            foreach ($dispatch->items as $item) {
                if ($item->isAsset()) {
                    $this->dispatchAsset($item);
                } else {
                    $this->dispatchStock($item);
                }
            }

            $dispatch->update([
                'status' => InventoryDispatch::STATUS_DISPATCHED,
                'dispatched_by_user_id' => $user->id,
                'dispatched_at' => now(),
            ]);
        });
    }

    public function startReturn(InventoryDispatch $dispatch, User $user): void
    {
        if (! $dispatch->canStartReturn()) {
            throw ValidationException::withMessages([
                'status' => 'Return can only be started after the items have been dispatched.',
            ]);
        }

        $dispatch->update([
            'status' => InventoryDispatch::STATUS_RETURNING,
            'return_started_by_user_id' => $user->id,
            'return_started_at' => now(),
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $payload
     */
    public function saveReturnOutcomes(InventoryDispatch $dispatch, array $payload): void
    {
        if (! $dispatch->canRecordReturn()) {
            throw ValidationException::withMessages([
                'status' => 'Return outcomes can only be recorded while the dispatch is returning.',
            ]);
        }

        DB::transaction(function () use ($dispatch, $payload) {
            foreach ($dispatch->items as $item) {
                $row = $payload[$item->id] ?? null;
                if (! is_array($row)) {
                    continue;
                }

                $this->applyReturnRow($item, $row);
            }
        });
    }

    public function markAllReceivedGood(InventoryDispatch $dispatch): void
    {
        if (! $dispatch->canRecordReturn()) {
            throw ValidationException::withMessages([
                'status' => 'Return outcomes can only be recorded while the dispatch is returning.',
            ]);
        }

        DB::transaction(function () use ($dispatch) {
            foreach ($dispatch->items as $item) {
                if ($item->isAsset()) {
                    $item->update([
                        'return_outcome' => InventoryDispatchItem::OUTCOME_RECEIVED_GOOD,
                    ]);
                } else {
                    $item->update([
                        'quantity_returned' => $item->quantity,
                        'quantity_consumed' => 0,
                    ]);
                }
            }
        });
    }

    public function completeReturn(InventoryDispatch $dispatch, User $user): void
    {
        if (! $dispatch->canCompleteReturn()) {
            throw ValidationException::withMessages([
                'status' => 'The return process is not open for this dispatch.',
            ]);
        }

        $dispatch->load('items');

        $pending = $dispatch->items->first(fn (InventoryDispatchItem $item) => ! $item->hasReturnOutcome());
        if ($pending) {
            throw ValidationException::withMessages([
                'items' => 'Record a return outcome for every item before completing the return.',
            ]);
        }

        $dispatch->update([
            'status' => InventoryDispatch::STATUS_RETURNED,
            'returned_by_user_id' => $user->id,
            'returned_at' => now(),
        ]);
    }

    public function close(InventoryDispatch $dispatch, User $user): void
    {
        if (! $dispatch->canClose()) {
            throw ValidationException::withMessages([
                'status' => 'Close the dispatch only after the return process is complete.',
            ]);
        }

        DB::transaction(function () use ($dispatch, $user) {
            $dispatch = InventoryDispatch::query()->lockForUpdate()->findOrFail($dispatch->id);
            $dispatch->load(['items.asset', 'items.stockItem']);

            foreach ($dispatch->items as $item) {
                if ($item->isAsset()) {
                    $this->restoreAsset($item, $user);
                } else {
                    $this->restoreStock($item);
                }
            }

            $dispatch->update([
                'status' => InventoryDispatch::STATUS_CLOSED,
                'closed_by_user_id' => $user->id,
                'closed_at' => now(),
            ]);
        });
    }

    private function dispatchAsset(InventoryDispatchItem $item): void
    {
        $asset = InventoryAsset::query()->lockForUpdate()->find($item->asset_id);
        if (! $asset) {
            throw ValidationException::withMessages([
                'items' => 'An asset on this dispatch is no longer available.',
            ]);
        }

        if (! $asset->isDispatchable()) {
            throw ValidationException::withMessages([
                'items' => "{$asset->asset_code} cannot be dispatched in its current status.",
            ]);
        }

        $item->update([
            'original_status' => $asset->status,
            'original_location_id' => $asset->location_id,
            'original_responsible_user_id' => $asset->responsible_user_id,
            'original_department_id' => $asset->department_id,
            'original_condition' => $asset->condition,
            'condition_at_dispatch' => $asset->condition,
        ]);

        $asset->update([
            'status' => InventoryAsset::STATUS_DISPATCHED,
        ]);
    }

    private function dispatchStock(InventoryDispatchItem $item): void
    {
        $stockItem = InventoryStockItem::query()->lockForUpdate()->find($item->stock_item_id);
        if (! $stockItem) {
            throw ValidationException::withMessages([
                'items' => 'A stock item on this dispatch is no longer available.',
            ]);
        }

        if ($item->quantity > $stockItem->quantity_remaining) {
            throw ValidationException::withMessages([
                'items' => "Not enough remaining stock for {$stockItem->name}.",
            ]);
        }

        $stockItem->update([
            'quantity_issued' => $stockItem->quantity_issued + $item->quantity,
            'quantity_remaining' => $stockItem->quantity_remaining - $item->quantity,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function applyReturnRow(InventoryDispatchItem $item, array $row): void
    {
        if ($item->isAsset()) {
            $outcome = $row['return_outcome'] ?? null;
            if (! is_string($outcome) || $outcome === '') {
                $item->update([
                    'return_outcome' => null,
                    'return_notes' => $row['return_notes'] ?? null,
                ]);

                return;
            }

            if (! array_key_exists($outcome, InventoryDispatchItem::returnOutcomeOptions())) {
                throw ValidationException::withMessages([
                    "items.{$item->id}.return_outcome" => 'Choose a valid return outcome.',
                ]);
            }

            $notes = isset($row['return_notes']) ? (string) $row['return_notes'] : null;
            if ($outcome === InventoryDispatchItem::OUTCOME_RECEIVED_DAMAGED && trim((string) $notes) === '') {
                throw ValidationException::withMessages([
                    "items.{$item->id}.return_notes" => 'Add a note describing the damage.',
                ]);
            }

            $item->update([
                'return_outcome' => $outcome,
                'return_notes' => $notes,
            ]);

            return;
        }

        $returnedRaw = $row['quantity_returned'] ?? null;
        $consumedRaw = $row['quantity_consumed'] ?? null;
        $returnedBlank = $returnedRaw === null || $returnedRaw === '';
        $consumedBlank = $consumedRaw === null || $consumedRaw === '';

        if ($returnedBlank && $consumedBlank) {
            return;
        }

        $returnedQty = $returnedBlank ? 0 : max(0, (int) $returnedRaw);
        $consumedQty = $consumedBlank ? 0 : max(0, (int) $consumedRaw);

        if ($returnedQty + $consumedQty !== (int) $item->quantity) {
            throw ValidationException::withMessages([
                "items.{$item->id}.quantity_returned" => "Returned and consumed quantities for {$item->itemName()} must add up to {$item->quantity}.",
            ]);
        }

        $item->update([
            'quantity_returned' => $returnedQty,
            'quantity_consumed' => $consumedQty,
            'return_notes' => $row['return_notes'] ?? $item->return_notes,
        ]);
    }

    private function restoreAsset(InventoryDispatchItem $item, User $user): void
    {
        $asset = InventoryAsset::query()->lockForUpdate()->find($item->asset_id);
        if (! $asset) {
            return;
        }

        $originalStatus = $this->restoredStatus($item, $asset);
        $updates = [
            'location_id' => $item->original_location_id,
            'responsible_user_id' => $item->original_responsible_user_id,
            'department_id' => $item->original_department_id,
        ];

        switch ($item->return_outcome) {
            case InventoryDispatchItem::OUTCOME_RECEIVED_GOOD:
                $updates['status'] = $originalStatus;
                $updates['condition'] = $item->original_condition ?: $asset->condition;
                $asset->update($updates);
                break;
            case InventoryDispatchItem::OUTCOME_RECEIVED_DAMAGED:
                $updates['status'] = InventoryAsset::STATUS_UNDER_REPAIR;
                $updates['condition'] = InventoryAsset::CONDITION_DAMAGED;
                $asset->update($updates);
                $this->openMaintenanceForDamagedAsset($item, $asset, $user);
                break;
            case InventoryDispatchItem::OUTCOME_MISSING:
                $updates['status'] = InventoryAsset::STATUS_MISSING;
                $asset->update($updates);
                break;
            default:
                throw ValidationException::withMessages([
                    'items' => "{$item->itemName()} is missing a return outcome.",
                ]);
        }
    }

    private function restoreStock(InventoryDispatchItem $item): void
    {
        $stockItem = InventoryStockItem::query()->lockForUpdate()->find($item->stock_item_id);
        if (! $stockItem) {
            return;
        }

        $returned = (int) ($item->quantity_returned ?? 0);
        if ($returned <= 0) {
            return;
        }

        $stockItem->update([
            'quantity_issued' => max(0, $stockItem->quantity_issued - $returned),
            'quantity_remaining' => $stockItem->quantity_remaining + $returned,
        ]);
    }

    private function restoredStatus(InventoryDispatchItem $item, InventoryAsset $asset): string
    {
        $original = $item->original_status;

        if ($original === InventoryAsset::STATUS_ASSIGNED || filled($item->original_responsible_user_id) || filled($asset->responsible_user_id)) {
            return InventoryAsset::STATUS_ASSIGNED;
        }

        if ($original === InventoryAsset::STATUS_AVAILABLE) {
            return InventoryAsset::STATUS_AVAILABLE;
        }

        return filled($asset->responsible_user_id)
            ? InventoryAsset::STATUS_ASSIGNED
            : InventoryAsset::STATUS_AVAILABLE;
    }

    private function openMaintenanceForDamagedAsset(InventoryDispatchItem $item, InventoryAsset $asset, User $user): void
    {
        if ($asset->hasOpenMaintenance()) {
            return;
        }

        $dispatch = $item->dispatch;
        $notes = trim((string) $item->return_notes);
        $description = $notes !== ''
            ? $notes
            : "Damaged during dispatch {$dispatch?->dispatch_number}: {$dispatch?->title}";

        InventoryMaintenanceRecord::query()->create([
            'record_number' => $this->numberGenerator->generate(
                InventoryMaintenanceRecord::class,
                'record_number',
                'MNT'
            ),
            'asset_id' => $asset->id,
            'status' => InventoryMaintenanceRecord::STATUS_REPORTED,
            'problem_description' => $description,
            'reported_by_user_id' => $user->id,
            'reported_at' => now(),
            'previous_status' => $item->original_status ?: InventoryAsset::STATUS_AVAILABLE,
            'previous_condition' => $item->original_condition ?: $asset->condition,
        ]);
    }
}
