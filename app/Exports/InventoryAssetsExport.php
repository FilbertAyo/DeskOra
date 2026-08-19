<?php

namespace App\Exports;

use App\Models\InventoryAsset;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InventoryAssetsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
    {
        return InventoryAsset::query()
            ->with(['category', 'location', 'department', 'responsibleUser', 'donor', 'project', 'supplier'])
            ->orderBy('asset_code')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Asset ID',
            'Name',
            'Serial',
            'Category',
            'Status',
            'Condition',
            'Location',
            'Department',
            'Responsible',
            'Source',
            'Donor',
            'Project',
            'Supplier',
            'Purchase value (TZS)',
            'Date received',
        ];
    }

    /**
     * @param  InventoryAsset  $asset
     */
    public function map($asset): array
    {
        return [
            $asset->asset_code,
            $asset->name,
            $asset->serial_number,
            $asset->category?->name,
            InventoryAsset::statusOptions()[$asset->status] ?? $asset->status,
            InventoryAsset::conditionOptions()[$asset->condition] ?? $asset->condition,
            $asset->location?->name,
            $asset->department?->name,
            $asset->responsibleUser?->name,
            InventoryAsset::sourceTypeOptions()[$asset->source_type] ?? $asset->source_type,
            $asset->donor?->name,
            $asset->project?->name,
            $asset->supplier?->name,
            $asset->purchase_value,
            $asset->date_received?->toDateString(),
        ];
    }
}
