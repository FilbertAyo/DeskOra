<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Issued stock</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 6px; }
        .meta { color: #666; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; }
        th { background: #f3f3f3; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <h1>Stock issued to {{ $user->name }}</h1>
    <div class="meta">Generated {{ $generatedAt->format('M d, Y h:i A') }} · {{ $issues->count() }} record(s)</div>
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="num">Quantity</th>
                <th>Issued to</th>
                <th>Location</th>
                <th>Purpose</th>
                <th>Issued on</th>
            </tr>
        </thead>
        <tbody>
            @forelse($issues as $issue)
                <tr>
                    <td>{{ $issue->stockItem?->name ?? '—' }}</td>
                    <td class="num">{{ $issue->quantity }}</td>
                    <td>
                        @if($issue->issuedToUser)
                            {{ $issue->issuedToUser->name }}
                        @elseif($issue->issuedToDepartment)
                            {{ $issue->issuedToDepartment->pathLabel() }}
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $issue->stockItem?->location?->pathLabel() ?? '—' }}</td>
                    <td>{{ $issue->purpose ?: '—' }}</td>
                    <td>{{ $issue->issued_at?->format('M d, Y') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No issued stock found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
