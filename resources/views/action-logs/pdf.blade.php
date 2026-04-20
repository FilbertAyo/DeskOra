<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            color: #222;
        }
        h2, h4 {
            margin: 0 0 6px 0;
            text-align: center;
        }
        .meta {
            margin-top: 12px;
            margin-bottom: 12px;
            font-size: 9pt;
        }
        .meta-row {
            margin-bottom: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f5f5f5;
        }
    </style>
</head>
<body>
    <h2>Action Log</h2>
    <h4>{{ $document->display_title }}</h4>

    <div class="meta">
        <div class="meta-row"><strong>Created by:</strong> {{ $document->user?->name ?? '-' }}</div>
        <div class="meta-row"><strong>State:</strong> {{ ucfirst((string) $document->state) }}</div>
        <div class="meta-row"><strong>Work period:</strong>
            @if($document->period)
                Week {{ $document->period->week_number }} of {{ $document->period->year }}
            @else
                -
            @endif
        </div>
        <div class="meta-row"><strong>Updated at:</strong> {{ $document->updated_at?->format('M d, Y h:i A') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 40px;">No</th>
                <th>Task</th>
                <th style="width: 180px;">Responsible Person</th>
                <th style="width: 90px;">Progress</th>
                <th style="width: 100px;">Due Date</th>
                <th style="width: 110px;">Compilation Date</th>
                <th style="width: 220px;">Notes/Progress</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tasks as $index => $row)
                @php
                    $row = is_array($row) ? $row : [];
                    $ids = is_array($row['responsible_user_ids'] ?? null) ? array_map('strval', $row['responsible_user_ids']) : [];
                    $names = collect($ids)->map(fn ($id) => $responsibleNameMap[$id] ?? null)->filter()->values()->all();
                @endphp
                <tr>
                    <td>{{ $row['task_no'] ?? ($index + 1) }}</td>
                    <td>{{ $row['task'] ?? '' }}</td>
                    <td>{{ !empty($names) ? implode(', ', $names) : '-' }}</td>
                    <td>{{ $row['progress'] ?? 'Not Started' }}</td>
                    <td>{{ $row['due_date'] ?? '-' }}</td>
                    <td>{{ $row['compilation_date'] ?? '-' }}</td>
                    <td>{{ $row['notes_progress'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No tasks found for this action log.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
