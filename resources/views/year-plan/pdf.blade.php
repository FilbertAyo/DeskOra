<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Year Plan {{ $yearPlan->year }}{{ isset($departmentTitle) && $departmentTitle ? ' — '.$departmentTitle : '' }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #1f2937;
        }
        h2, h3, p {
            margin: 0;
        }
        .mb-4 { margin-bottom: 4px; }
        .mb-8 { margin-bottom: 8px; }
        .mb-12 { margin-bottom: 12px; }
        .mb-16 { margin-bottom: 16px; }
        .section-title {
            margin-top: 12px;
            margin-bottom: 6px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .muted { color: #6b7280; }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
        }
        th, td {
            border: 1px solid #000;
            padding: 3px 4px;
            vertical-align: top;
            text-align: left;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        th {
            background: #f3f4f6;
            font-weight: bold;
            text-align: center;
            white-space: normal;
        }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .code-col { width: 5%; }
        .desc-col { width: 22%; }
        .num-col { width: 6%; }
        .month-col { width: 3.2%; }
        .subtotal td {
            font-weight: bold;
            background: #f9fafb;
        }
        .grand-total {
            margin-top: 10px;
            padding: 8px;
            border: 1px solid #000;
            font-size: 10px;
            font-weight: bold;
        }
        .grand-total span { float: right; }
    </style>
</head>
<body>
    @php
        $monthLabels = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];
    @endphp

    <h2 class="mb-4">Year Plan {{ $yearPlan->year }}</h2>
    @if (! empty($departmentTitle))
        <p class="mb-12 muted">{{ $departmentTitle }}</p>
    @else
        <p class="mb-12 muted">Full year plan — all sub-departments</p>
    @endif

    @forelse ($sections as $section)
        @php($dept = $section['department'])
        <div class="section-title">
            @if ($dept->parent)
                <span class="muted">{{ $dept->parent->name }} / </span>
            @endif
            {{ $dept->name }}
        </div>

        <table class="mb-8">
            <thead>
                <tr>
                    <th class="code-col">Code</th>
                    <th class="desc-col">Description</th>
                    <th class="num-col">Freq.</th>
                    <th class="num-col">Units</th>
                    <th class="num-col">Unit value</th>
                    <th class="num-col">Total</th>
                    @foreach ($monthLabels as $label)
                        <th class="month-col">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($section['activities'] as $activity)
                    <tr>
                        <td>{{ $activity->displayCode() }}</td>
                        <td>{{ $activity->description }}</td>
                        <td class="text-end">{{ $activity->frequency }}</td>
                        <td class="text-end">{{ $activity->unit_count }}</td>
                        <td class="text-end">{{ number_format((float) $activity->unit_value_tzs, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $activity->total_cost_tzs, 2) }}</td>
                        @foreach ($monthLabels as $m => $label)
                            <td class="text-center">{{ $activity->isPlannedInMonth($m) ? 'Y' : '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
                <tr class="subtotal">
                    <td colspan="5" class="text-end">Subtotal (TZS)</td>
                    <td class="text-end">{{ number_format($section['subtotal'], 2) }}</td>
                    <td colspan="12"></td>
                </tr>
            </tbody>
        </table>
    @empty
        <p>No activities in this year plan.</p>
    @endforelse

    @if ($sections->isNotEmpty())
        <div class="grand-total">
            Grand total (TZS)
            <span>{{ number_format($grandTotal, 2) }}</span>
        </div>
    @endif
</body>
</html>
