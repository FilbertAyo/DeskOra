<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Asset labels</title>
    <style>
        body { font-family: sans-serif; margin: 24px; }
        .toolbar { margin-bottom: 16px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
        .label { border: 1px solid #ccc; padding: 12px; text-align: center; break-inside: avoid; }
        .code { font-weight: 700; font-size: 14px; }
        .name { font-size: 12px; margin-top: 4px; }
        img { width: 120px; height: 120px; }
        @media print {
            .toolbar { display: none; }
            body { margin: 8px; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print</button>
        <a href="{{ route('inventory.assets.index') }}">Back to assets</a>
        <span style="color:#666; font-size:12px; margin-left:8px;">Up to 200 labels from the current asset filter.</span>
    </div>
    <div class="grid">
        @forelse($labels as $label)
            <div class="label">
                <img src="{{ $label['qr'] }}" alt="QR {{ $label['asset']->asset_code }}">
                <div class="code">{{ $label['asset']->asset_code }}</div>
                <div class="name">{{ $label['asset']->name }}</div>
            </div>
        @empty
            <p>No assets match the current filter.</p>
        @endforelse
    </div>
</body>
</html>
