<?php

namespace App\Exports;

use App\Models\InventoryStockIssue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InventoryIssuedStockExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Item',
            'Quantity',
            'Issued to',
            'Department',
            'Location',
            'Purpose',
            'Issued on',
            'Issued by',
        ];
    }

    /**
     * @param  InventoryStockIssue  $issue
     */
    public function map($issue): array
    {
        return [
            $issue->stockItem?->name,
            $issue->quantity,
            $issue->issuedToUser?->name,
            $issue->issuedToDepartment?->pathLabel() ?? $issue->issuedToDepartment?->name,
            $issue->stockItem?->location?->pathLabel(),
            $issue->purpose,
            $issue->issued_at?->toDateString(),
            $issue->issuedByUser?->name,
        ];
    }
}
