<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inventory stock items</title>
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
    <h1>Inventory stock items</h1>
    <div class="meta">Generated {{ $generatedAt->format('M d, Y h:i A') }} · {{ $stockItems->count() }} record(s)</div>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Category</th>
                <th>Location</th>
                <th class="num">Received</th>
                <th class="num">Issued</th>
                <th class="num">Remaining</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($stockItems as $item)
                <tr>
                    <td>{{ $item->name }}@if($item->unit) ({{ $item->unit }})@endif</td>
                    <td>{{ $item->category?->name ?? '—' }}</td>
                    <td>{{ $item->location?->pathLabel() ?? '—' }}</td>
                    <td class="num">{{ $item->quantity_received }}</td>
                    <td class="num">{{ $item->quantity_issued }}</td>
                    <td class="num">{{ $item->quantity_remaining }}</td>
                    <td>{{ ucfirst($item->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No stock items found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
