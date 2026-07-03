<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Weekly Matrix — Week {{ $period->week_number }} of {{ $period->year }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #1f2937;
        }
        h2, p {
            margin: 0;
        }
        .mb-4 { margin-bottom: 4px; }
        .mb-12 { margin-bottom: 12px; }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #000;
            padding: 4px;
            vertical-align: top;
            text-align: left;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }
        th {
            background: #f3f4f6;
            font-weight: bold;
            white-space: normal;
        }
        .dept-col { width: 10%; }
        .yp-col { width: 12%; }
        .badge {
            display: inline-block;
            border: 1px solid #999;
            border-radius: 3px;
            padding: 0 3px;
            font-size: 8px;
        }
    </style>
</head>
<body>
    <h2 class="mb-4">Weekly Matrix — Week {{ $period->week_number }} of {{ $period->year }}</h2>
    <p class="mb-12">{{ $period->date_range }}</p>

    <table>
        <thead>
            <tr>
                <th class="dept-col">Sub-department</th>
                <th>Key Activities</th>
                <th>Output</th>
                <th>Outcome</th>
                <th>Impact</th>
                <th>Indicators</th>
                <th>Baseline</th>
                <th>Target</th>
                <th>Means of Verification</th>
                <th class="yp-col">Year plan links</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($matrices as $row)
                @php
                    $department = $row['department'];
                    $entry = $row['entry'];
                @endphp
                <tr>
                    <td>{{ $department?->name ?? '-' }}</td>
                    <td>{{ $entry?->key_activity ?: '-' }}</td>
                    <td>{{ $entry?->output ?: '-' }}</td>
                    <td>{{ $entry?->outcome ?: '-' }}</td>
                    <td>{{ $entry?->impact ?: '-' }}</td>
                    <td>{{ $entry?->indicators ?: '-' }}</td>
                    <td>{{ $entry?->baseline ?: '-' }}</td>
                    <td>{{ $entry?->target ?: '-' }}</td>
                    <td>{{ $entry?->means_of_verification ?: '-' }}</td>
                    <td>
                        @forelse ($entry?->yearPlanActivities ?? [] as $activity)
                            <span class="badge">{{ $activity->displayCode() }}</span> {{ \Illuminate\Support\Str::limit($activity->description, 50) }}@if (! $loop->last)<br>@endif
                        @empty
                            -
                        @endforelse
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align: center;">No matrix present for this work period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
