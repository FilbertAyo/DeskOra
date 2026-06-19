<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Requisition</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 10px; }
        .header img { height: 55px; }
        .title { text-align: center; font-weight: 700; font-size: 20px; margin: 10px 0 14px; }
        .meta { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .meta td { padding: 5px 8px; vertical-align: top; }
        .meta .col-left { width: 42%; }
        .meta .col-right { width: 58%; }
        .meta .value { display: inline-block; min-width: 85%; border-bottom: 1px solid #333; padding-bottom: 1px; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th, table.items td { border: 1px solid #0077b6; padding: 6px; }
        table.items th { background: #0077b6; color: #fff; }
        .total { text-align: right; font-weight: 700; margin-top: 8px; }
        .signatures { width: 100%; margin-top: 24px; }
        .signature-block { margin-bottom: 18px; }
        .signature-block .role { font-weight: 700; margin-bottom: 4px; }
        .sig-box { height: 40px; margin-bottom: 4px; }
        .sig-box img { max-height: 38px; }
        .line { border-top: 1px solid #333; width: 220px; margin: 6px 0 4px; }
        .signature-name { font-size: 11px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('assets/images/logo.png') }}" alt="Logo">
    </div>
    <div class="title">FUND REQUISITION FORM</div>

    <table class="meta">
        <tr>
            <td class="col-left"><strong>Project/program:</strong> <span class="value">{{ $request->project_program }}</span></td>
            <td class="col-right"><strong>Activity code:</strong> <span class="value">{{ $request->activity_code }}</span></td>
        </tr>
        <tr>
            <td class="col-left"><strong>Activity Title:</strong> <span class="value">{{ $request->activity_title }}</span></td>
            <td class="col-right"><strong>Budget code:</strong> <span class="value">{{ $request->budget_code }}</span></td>
        </tr>
        <tr>
            <td class="col-left"><strong>Date of request:</strong> <span class="value">{{ optional($request->date_of_request)->format('d/m/Y') }}</span></td>
            <td class="col-right"><strong>Date of requirement:</strong> <span class="value">{{ optional($request->date_of_requirement)->format('d/m/Y') }}</span></td>
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
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
        </tbody>
    </table>
    <div class="total">TOTAL AMOUNT: TZS {{ number_format($request->amount, 2) }}</div>

    <div class="signatures">
        <div class="signature-block">
            <div class="role">Requested by</div>
            <div class="sig-box">@if($request->user && $request->user->signature)<img src="{{ public_path($request->user->signature) }}">@endif</div>
            <div class="line"></div>
            <div class="signature-name">{{ $request->user->name ?? '' }}</div>
        </div>

        <div class="signature-block">
            <div class="role">Reviewed by</div>
            <div class="sig-box">@if($request->reviewedBy && $request->reviewedBy->signature)<img src="{{ public_path($request->reviewedBy->signature) }}">@endif</div>
            <div class="line"></div>
            <div class="signature-name">{{ $request->reviewedBy->name ?? '' }}</div>
        </div>

        <div class="signature-block">
            <div class="role">Approved by</div>
            <div class="sig-box">@if($request->approvedBy && $request->approvedBy->signature)<img src="{{ public_path($request->approvedBy->signature) }}">@endif</div>
            <div class="line"></div>
            <div class="signature-name">{{ $request->approvedBy->name ?? '' }}</div>
        </div>

        <div class="signature-block">
            <div class="role">Authorized by</div>
            <div class="sig-box">@if($request->authorizedBy && $request->authorizedBy->signature)<img src="{{ public_path($request->authorizedBy->signature) }}">@endif</div>
            <div class="line"></div>
            <div class="signature-name">{{ $request->authorizedBy->name ?? '' }}</div>
        </div>
    </div>
</body>
</html>
