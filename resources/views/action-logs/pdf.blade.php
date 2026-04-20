<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            color: #222;
            margin: 0;
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
            font-size: 8.5pt;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #000;
            padding: 4px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        th {
            background: #f5f5f5;
        }
        .col-no { width: 5%; }
        .col-task { width: 21%; }
        .col-responsible { width: 18%; }
        .col-progress { width: 10%; }
        .col-due { width: 11%; }
        .col-compilation { width: 11%; }
        .col-notes { width: 24%; }
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
                <th class="col-no">No</th>
                <th class="col-task">Task</th>
                <th class="col-responsible">Responsible Person</th>
                <th class="col-progress">Progress</th>
                <th class="col-due">Due Date</th>
                <th class="col-compilation">Compilation Date</th>
                <th class="col-notes">Notes/Progress</th>
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
