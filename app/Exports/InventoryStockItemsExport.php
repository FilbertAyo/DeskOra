<?php

namespace App\Exports;

use App\Models\InventoryStockItem;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InventoryStockItemsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
    {
        return InventoryStockItem::query()
            ->with(['category', 'location', 'supplier'])
            ->orderBy('name')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Name',
            'Category',
            'Unit',
            'Received',
            'Issued',
            'Remaining',
            'Minimum',
            'Location',
            'Supplier',
            'Status',
        ];
    }

    /**
     * @param  InventoryStockItem  $item
     */
    public function map($item): array
    {
        return [
            $item->name,
            $item->category?->name,
            $item->unit,
            $item->quantity_received,
            $item->quantity_issued,
            $item->quantity_remaining,
            $item->minimum_level,
            $item->location?->name,
            $item->supplier?->name,
            $item->status,
        ];
    }
}
