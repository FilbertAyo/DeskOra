<?php

namespace App\Exports;

use App\Models\YearPlanActivity;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class YearPlanExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    private const MONTH_LABELS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    ];

    /**
     * @param  Collection<int, array{department: \App\Models\Department, activities: Collection, subtotal: float}>  $sections
     */
    public function __construct(
        private readonly Collection $sections,
        private readonly int $year,
        private readonly float $grandTotal,
        private readonly ?string $sheetTitle = null,
    ) {}

    public function title(): string
    {
        return $this->sheetTitle ?? 'Year Plan '.$this->year;
    }

    public function headings(): array
    {
        return array_merge(
            [
                'Parent department',
                'Sub-department',
                'Code',
                'Description',
                'Freq.',
                'Units',
                'Unit value (TZS)',
                'Total (TZS)',
            ],
            array_values(self::MONTH_LABELS),
        );
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->sections as $section) {
            $dept = $section['department'];

            foreach ($section['activities'] as $activity) {
                /** @var YearPlanActivity $activity */
                $rows[] = $this->activityRow($activity, $dept);
            }

            $rows[] = [
                '',
                $dept->name,
                '',
                'Subtotal (TZS)',
                '',
                '',
                '',
                number_format((float) $section['subtotal'], 2, '.', ''),
                '', '', '', '', '', '', '', '', '', '', '', '',
            ];

            $rows[] = array_fill(0, 20, '');
        }

        if ($this->sections->count() > 1) {
            $rows[] = [
                '',
                '',
                '',
                'Grand total (TZS)',
                '',
                '',
                '',
                number_format($this->grandTotal, 2, '.', ''),
                '', '', '', '', '', '', '', '', '', '', '', '',
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    private function activityRow(YearPlanActivity $activity, $dept): array
    {
        $months = [];
        foreach (array_keys(self::MONTH_LABELS) as $month) {
            $months[] = $activity->isPlannedInMonth($month) ? 'Y' : '';
        }

        return array_merge(
            [
                $dept->parent?->name ?? '',
                $dept->name,
                $activity->displayCode(),
                $activity->description,
                (string) $activity->frequency,
                (string) $activity->unit_count,
                number_format((float) $activity->unit_value_tzs, 2, '.', ''),
                number_format((float) $activity->total_cost_tzs, 2, '.', ''),
            ],
            $months,
        );
    }
}
