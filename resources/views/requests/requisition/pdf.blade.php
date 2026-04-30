<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Requisition</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 10px; }
        .header img { height: 55px; }
        .title { text-align: center; font-weight: 700; margin: 10px 0 14px; }
        .meta { width: 100%; margin-bottom: 12px; }
        .meta td { padding: 3px 4px; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th, table.items td { border: 1px solid #0f5f9e; padding: 6px; }
        table.items th { background: #0f5f9e; color: #fff; }
        .total { text-align: right; font-weight: 700; margin-top: 8px; }
        .signatures { width: 100%; margin-top: 20px; }
        .signatures td { width: 25%; text-align: center; vertical-align: top; }
        .sig-box { height: 40px; margin-bottom: 6px; }
        .sig-box img { max-height: 38px; }
        .line { border-top: 1px solid #333; margin: 8px 10px 4px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('assets/images/logo.png') }}" alt="Logo">
    </div>
    <div class="title">FUND REQUISITION FORM</div>

    <table class="meta">
        <tr>
            <td><strong>Project/program:</strong> {{ $request->project_program }}</td>
            <td><strong>Activity code:</strong> {{ $request->activity_code }}</td>
        </tr>
        <tr>
            <td><strong>Activity Title:</strong> {{ $request->activity_title }}</td>
            <td><strong>Budget code:</strong> {{ $request->budget_code }}</td>
        </tr>
        <tr>
            <td><strong>Date of request:</strong> {{ optional($request->date_of_request)->format('d/m/Y') }}</td>
            <td><strong>Date of requirement:</strong> {{ optional($request->date_of_requirement)->format('d/m/Y') }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>S/NO</th>
                <th>DESCRIPTION</th>
                <th>UNIT</th>
                <th>COST</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($request->requisitionItems as $item)
                <tr>
                    <td>{{ $item->serial_no }}</td>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->unit }}</td>
                    <td>{{ number_format($item->cost, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="total">TOTAL AMOUNT: TZS {{ number_format($request->amount, 2) }}</div>

    <table class="signatures">
        <tr>
            <td>
                <div>Requested by</div>
                <div class="sig-box">@if($request->user && $request->user->signature)<img src="{{ public_path($request->user->signature) }}">@endif</div>
                <div class="line"></div>
                <div>{{ $request->user->name ?? '' }}</div>
            </td>
            <td>
                <div>Reviewed by</div>
                <div class="sig-box">@if($request->reviewedBy && $request->reviewedBy->signature)<img src="{{ public_path($request->reviewedBy->signature) }}">@endif</div>
                <div class="line"></div>
                <div>{{ $request->reviewedBy->name ?? '' }}</div>
            </td>
            <td>
                <div>Approved by</div>
                <div class="sig-box">@if($request->approvedBy && $request->approvedBy->signature)<img src="{{ public_path($request->approvedBy->signature) }}">@endif</div>
                <div class="line"></div>
                <div>{{ $request->approvedBy->name ?? '' }}</div>
            </td>
            <td>
                <div>Authorized by</div>
                <div class="sig-box">@if($request->authorizedBy && $request->authorizedBy->signature)<img src="{{ public_path($request->authorizedBy->signature) }}">@endif</div>
                <div class="line"></div>
                <div>{{ $request->authorizedBy->name ?? '' }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
