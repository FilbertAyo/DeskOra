<?php

namespace App\Services\Inventory;

use Illuminate\Database\Eloquent\Model;

class InventoryNumberGenerator
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function generate(string $modelClass, string $column, string $code): string
    {
        $year = now()->year;
        $prefix = "WH-{$code}-{$year}-";

        $lastNumber = $modelClass::query()
            ->where($column, 'like', $prefix.'%')
            ->orderByDesc($column)
            ->value($column);

        $nextNumber = 1;
        if (is_string($lastNumber) && str_starts_with($lastNumber, $prefix)) {
            $nextNumber = ((int) substr($lastNumber, strlen($prefix))) + 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
