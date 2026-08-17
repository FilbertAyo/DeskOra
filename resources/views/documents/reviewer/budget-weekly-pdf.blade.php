<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Weekly Budget Review Export</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
        }
        h2, h4, p {
            margin: 0;
        }
        .mb-8 { margin-bottom: 8px; }
        .mb-12 { margin-bottom: 12px; }
        .mb-16 { margin-bottom: 16px; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: top;
        }
        th {
            background: #f3f4f6;
            text-align: left;
        }
        .text-end { text-align: right; }
        .summary td {
            font-weight: bold;
            background: #f9fafb;
        }
        .section-title {
            margin-top: 14px;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h2 class="mb-8">Weekly Budget Review</h2>
    <p class="mb-16">Work Period: {{ $workPeriodRange }}</p>

    @forelse($grouped as $periodGroup)
        @php
            $periodHasApprovedItems = collect($periodGroup['departments'])->contains(function ($dept) {
                return collect($dept['staff'])->contains(function ($staffData) {
                    return collect($staffData['items'])->contains(function ($item) {
                        return ($item['decision'] ?? 'pending') === \App\Models\Document::BUDGET_REVIEW_APPROVED;
                    });
                });
            });
        @endphp
        @if(! $periodHasApprovedItems)
            @continue
        @endif

        <div class="section-title">{{ $periodGroup['period_label'] }}@if(! empty($periodGroup['period_range'])) — {{ $periodGroup['period_range'] }}@endif</div>

        @foreach($periodGroup['departments'] as $dept)
            @php
                $departmentHasApprovedItems = collect($dept['staff'])->contains(function ($staffData) {
                    return collect($staffData['items'])->contains(function ($item) {
                        return ($item['decision'] ?? 'pending') === \App\Models\Document::BUDGET_REVIEW_APPROVED;
                    });
                });
            @endphp
            @if(!$departmentHasApprovedItems)
                @continue
            @endif

            <div class="section-title">{{ $dept['department_name'] }}</div>

            @foreach($dept['staff'] as $staffData)
                @php
                    $approvedItems = collect($staffData['items'])->filter(function ($item) {
                        return ($item['decision'] ?? 'pending') === \App\Models\Document::BUDGET_REVIEW_APPROVED;
                    })->values();
                @endphp
                @if($approvedItems->isEmpty())
                    @continue
                @endif
                <p class="mb-8"><strong>{{ $staffData['user']?->name ?? 'Unknown' }}</strong></p>

            <table class="mb-12">
                <thead>
                    <tr>
                        <th style="width: 32px;">#</th>
                        <th>Item</th>
                        <th>Description / Purpose</th>
                        <th style="width: 95px;">Requested</th>
                        <th style="width: 95px;">Provided</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($approvedItems as $idx => $item)
                        <tr>
                            <td>{{ $idx + 1 }}</td>
                            <td>{{ $item['item'] }}</td>
                            <td>{{ $item['description_purpose'] }}</td>
                            <td class="text-end">{{ number_format((float) $item['amount_numeric'], 2) }}</td>
                            <td class="text-end">{{ number_format((float) $item['provided_amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="summary">
                    <tr>
                        <td colspan="3" class="text-end">Staff Total</td>
                        <td class="text-end">{{ number_format((float) $approvedItems->sum('amount_numeric'), 2) }}</td>
                        <td class="text-end">{{ number_format((float) $approvedItems->sum('provided_amount'), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
            @endforeach
        @endforeach
    @empty
        <p>No weekly budget records found for selected filters.</p>
    @endforelse
</body>
</html>
