<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            color: #222;
            margin: 0;
        }

        h2 {
            margin: 0 0 6px 0;
            text-align: center;
        }

        .subtitle {
            margin: 0 0 12px 0;
            text-align: center;
            color: #555;
            font-size: 9pt;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9pt;
        }

        th, td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        th {
            background: #f5f5f5;
        }
    </style>
</head>
<body>
    <h2>Weekly Objective Matrix</h2>
    <p class="subtitle">
        Week {{ $currentPeriod->week_number }} of {{ $currentPeriod->year }} ({{ ucfirst($orientation) }})
    </p>

    <table>
        <thead>
            <tr>
                <th style="width: 22%;">Staff</th>
                <th style="width: 20%;">Department</th>
                <th style="width: 29%;">Main Objective</th>
                <th style="width: 29%;">Progress / Reflection</th>
            </tr>
        </thead>
        <tbody>
            @forelse($matrixRows as $plan)
                @php
                    $planContent = is_array($plan->content ?? null) ? $plan->content : [];
                    $objective = is_array($planContent['objective'] ?? null) ? $planContent['objective'] : [];
                    $reflectionProgress = trim((string) ($objective['reflection_progress'] ?? ''));
                @endphp
                <tr>
                    <td>{{ $plan->user?->name ?? 'Unknown Staff' }}</td>
                    <td>{{ $plan->user?->department?->name ?? 'Unknown Sub Department' }}</td>
                    <td>{{ $plan->main_target ?: '-' }}</td>
                    <td>{{ $reflectionProgress !== '' ? $reflectionProgress : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">No submitted weekly plans with a filled main objective for this week.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
