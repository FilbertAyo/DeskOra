<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RequisitionRecordsExport implements FromArray, WithHeadings
{
    protected $transactions;

    protected $isFiltered;

    public function __construct($transactions, bool $isFiltered)
    {
        $this->transactions = $transactions;
        $this->isFiltered = $isFiltered;
    }

    public function array(): array
    {
        if (empty($this->transactions) || $this->transactions->isEmpty()) {
            return [['No data available']];
        }

        if ($this->isFiltered) {
            return collect($this->transactions)->map(function ($tx) {
                return [
                    'Period' => $tx['label'] ?? '-',
                    'Requisitions' => $tx['count'] ?? 0,
                    'Amount' => $tx['amount'] ?? 0,
                    'Cumulative Total' => $tx['cumulative'] ?? 0,
                ];
            })->toArray();
        }

        return collect($this->transactions)->map(function ($tx) {
            return [
                'Date Authorized' => \Carbon\Carbon::parse($tx['date'])->format('Y-m-d'),
                'Reference' => $tx['reference'] ?? '-',
                'Requester' => $tx['requester'] ?? '-',
                'Activity' => $tx['activity'] ?? '-',
                'Project' => $tx['project'] ?? '-',
                'Budget Code' => $tx['budget_code'] ?? '-',
                'Amount' => $tx['amount'] ?? 0,
                'Cumulative Total' => $tx['cumulative'] ?? 0,
                'Authorized By' => $tx['authorized_by'] ?? '-',
            ];
        })->toArray();
    }

    public function headings(): array
    {
        if ($this->isFiltered) {
            return ['Period', 'Requisitions', 'Amount', 'Cumulative Total'];
        }

        return [
            'Date Authorized',
            'Reference',
            'Requester',
            'Activity',
            'Project',
            'Budget Code',
            'Amount',
            'Cumulative Total',
            'Authorized By',
        ];
    }
}
