<?php

namespace App\Services\Inventory;

use App\Models\InventoryReceipt;

class InventoryReceiptNumberGenerator
{
    public function generate(): string
    {
        $year = now()->year;
        $prefix = "WH-RCV-{$year}-";

        $lastNumber = InventoryReceipt::query()
            ->where('receipt_number', 'like', $prefix.'%')
            ->orderByDesc('receipt_number')
            ->value('receipt_number');

        $nextNumber = 1;
        if (is_string($lastNumber) && str_starts_with($lastNumber, $prefix)) {
            $nextNumber = ((int) substr($lastNumber, strlen($prefix))) + 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
