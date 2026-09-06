<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RunningBalanceExport implements FromArray, WithHeadings
{
    protected $transactions;

    protected $department;

    protected $isFiltered;

    public function __construct($transactions, $department, $isFiltered)
    {
        $this->transactions = $transactions;
        $this->department = $department;
        $this->isFiltered = $isFiltered;
    }

    public function array(): array
    {
        if (empty($this->transactions)) {
            return [['No data available']];
        }

        return collect($this->transactions)->map(function ($tx) {
            $row = [
                'Date' => $tx['label'] ?? \Carbon\Carbon::parse($tx['date'])->format('Y-m-d'),
                'Deduction' => $tx['deduction'] ?? 0,
                'Running Balance' => $tx['remaining'],
            ];

            if (! $this->isFiltered) {
                $row = array_merge(
                    ['Name' => $tx['requested_by'] ?? '-', 'Deposit' => $tx['deposit'] ?? 0],
                    $row
                );
            }

            return $row;
        })->toArray();
    }

    public function headings(): array
    {
        $headings = ['Date', 'Deduction', 'Running Balance'];

        if (! $this->isFiltered) {
            array_unshift($headings, 'Deposit');
            array_unshift($headings, 'Name');
        }

        return $headings;
    }
}
