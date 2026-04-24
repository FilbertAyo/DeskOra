<?php

namespace App\Exports;

use App\Models\Document;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WeeklyBudgetReviewExport implements FromCollection, WithHeadings, WithMapping
{
    private int $index = 0;

    public function __construct(
        private readonly Collection $documents
    ) {}

    public function collection(): Collection
    {
        return $this->documents->flatMap(function (Document $document) {
            $rows = [];
            $reviewsByUid = $document->budgetReviewItemsByUid();

            foreach ($document->budgetItems() as $item) {
                $uid = (string) ($item['budget_uid'] ?? '');
                $review = $reviewsByUid[$uid] ?? [];
                $decision = (string) ($review['decision'] ?? Document::BUDGET_REVIEW_PENDING);
                $providedAmount = $decision === Document::BUDGET_REVIEW_APPROVED
                    ? (float) ($review['provided_amount'] ?? 0)
                    : 0.0;

                if ($decision !== Document::BUDGET_REVIEW_APPROVED) {
                    continue;
                }

                $rows[] = [
                    'department' => $document->user?->department?->name ?? 'No Department',
                    'staff' => $document->user?->name ?? 'Unknown',
                    'week' => $document->period ? 'Week '.$document->period->week_number : '-',
                    'year' => $document->period?->year ?? '-',
                    'item' => (string) ($item['item'] ?? ''),
                    'description_purpose' => (string) ($item['description_purpose'] ?? ''),
                    'requested_amount' => (float) ($item['amount_numeric'] ?? 0),
                    'provided_amount' => $providedAmount,
                ];
            }

            return $rows;
        })->values();
    }

    public function headings(): array
    {
        return [
            '#',
            'Department',
            'Staff',
            'Week',
            'Year',
            'Item',
            'Description / Purpose',
            'Requested Amount',
            'Provided Amount',
        ];
    }

    public function map($row): array
    {
        return [
            ++$this->index,
            $row['department'],
            $row['staff'],
            $row['week'],
            $row['year'],
            $row['item'],
            $row['description_purpose'],
            number_format((float) $row['requested_amount'], 2, '.', ''),
            number_format((float) $row['provided_amount'], 2, '.', ''),
        ];
    }
}
