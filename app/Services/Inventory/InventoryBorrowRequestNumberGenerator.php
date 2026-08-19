<?php

namespace App\Services\Inventory;

use App\Models\InventoryBorrowRequest;

class InventoryBorrowRequestNumberGenerator
{
    public function generate(): string
    {
        $year = now()->year;
        $prefix = "WH-BRW-{$year}-";

        $lastNumber = InventoryBorrowRequest::query()
            ->where('request_number', 'like', $prefix.'%')
            ->orderByDesc('request_number')
            ->value('request_number');

        $nextNumber = 1;
        if (is_string($lastNumber) && str_starts_with($lastNumber, $prefix)) {
            $nextNumber = ((int) substr($lastNumber, strlen($prefix))) + 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
