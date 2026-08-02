<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Executive Pulse</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        .meta { color: #555; margin-bottom: 18px; }
        .grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .grid td { border: 1px solid #ddd; padding: 8px; vertical-align: top; width: 25%; }
        .label { color: #666; font-size: 10px; text-transform: uppercase; }
        .value { font-size: 16px; font-weight: bold; margin-top: 4px; }
        ul { padding-left: 18px; }
        li { margin-bottom: 6px; }
    </style>
</head>
<body>
    <h1>Executive Pulse</h1>
    <div class="meta">
        Generated {{ $snapshot['generated_at']->format('d M Y, H:i') }}
        @if($snapshot['current_period'])
            · Current period: Week {{ $snapshot['current_period']->week_number }} of {{ $snapshot['current_period']->year }}
        @endif
    </div>

    <table class="grid">
        <tr>
            <td>
                <div class="label">Weekly reports</div>
                <div class="value">{{ $snapshot['reports']['percent'] }}%</div>
                <div>{{ $snapshot['reports']['submitted'] }} submitted / {{ $snapshot['reports']['owing'] }} owing</div>
            </td>
            <td>
                <div class="label">Weekly plans</div>
                <div class="value">{{ $snapshot['plans']['percent'] }}%</div>
                <div>{{ $snapshot['plans']['submitted'] }} submitted / {{ $snapshot['plans']['owing'] }} owing</div>
            </td>
            <td>
                <div class="label">Open finance approvals</div>
                <div class="value">{{ $snapshot['approvals']['open_finance_requests'] }}</div>
                <div>Budget pending: {{ $snapshot['approvals']['budget_pending_org'] }}</div>
            </td>
            <td>
                <div class="label">Action tasks</div>
                <div class="value">{{ $snapshot['action_tasks']['active'] }}</div>
                <div>{{ $snapshot['action_tasks']['overdue'] }} overdue</div>
            </td>
        </tr>
    </table>

    <h3>Highlights & blockers</h3>
    <ul>
        @foreach($snapshot['highlights'] as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>

    <h3>Deadlines</h3>
    <p><strong>Plan:</strong> {{ $snapshot['deadlines']['plan_text'] ?? 'N/A' }}</p>
    <p><strong>Report:</strong> {{ $snapshot['deadlines']['report_text'] ?? 'N/A' }}</p>
</body>
</html>
