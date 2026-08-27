<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Inventory assets' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 6px; }
        .meta { color: #666; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; }
        th { background: #f3f3f3; }
    </style>
</head>
<body>
    <h1>{{ $title ?? 'Inventory assets' }}</h1>
    <div class="meta">Generated {{ $generatedAt->format('M d, Y h:i A') }} · {{ $assets->count() }} record(s)</div>
    <table>
        <thead>
            <tr>
                <th>Asset ID</th>
                <th>Name</th>
                <th>Location</th>
                <th>Department</th>
                <th>Responsible</th>
                <th>Condition</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($assets as $asset)
                <tr>
                    <td>{{ $asset->asset_code }}</td>
                    <td>{{ $asset->name }}</td>
                    <td>{{ $asset->location?->pathLabel() ?? '—' }}</td>
                    <td>{{ $asset->department?->pathLabel() ?? $asset->department?->name ?? '—' }}</td>
                    <td>{{ $asset->responsibleUser?->name ?? '—' }}</td>
                    <td>{{ $conditionOptions[$asset->condition] ?? $asset->condition }}</td>
                    <td>{{ $statusOptions[$asset->status] ?? $asset->status }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No assets found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
