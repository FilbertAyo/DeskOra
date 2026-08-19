<?php

namespace App\Services\Inventory;

use App\Models\InventoryAsset;

class InventoryAssetCodeGenerator
{
    public function generate(): string
    {
        $year = now()->year;
        $prefix = "WH-INV-{$year}-";

        $lastCode = InventoryAsset::query()
            ->where('asset_code', 'like', $prefix.'%')
            ->orderByDesc('asset_code')
            ->value('asset_code');

        $nextNumber = 1;
        if (is_string($lastCode) && str_starts_with($lastCode, $prefix)) {
            $nextNumber = ((int) substr($lastCode, strlen($prefix))) + 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
