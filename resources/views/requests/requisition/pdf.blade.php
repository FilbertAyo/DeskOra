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
        .meta td { padding: 5px 10px 7px 0; vertical-align: top; }
        .meta .col-left { width: 58%; padding-right: 16px; }
        .meta .col-right { width: 42%; }
        .meta .field-label { font-weight: 700; white-space: nowrap; }
        .meta .field-value {
            display: inline-block;
            min-width: 120px;
            text-decoration: underline;
            text-underline-offset: 2px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            vertical-align: bottom;
            line-height: 1.5;
        }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th, table.items td { border: 1px solid #0077b6; padding: 6px; }
        table.items th { background: #0077b6; color: #fff; }
        .total { text-align: right; font-weight: 700; margin-top: 8px; }
        .signatures { width: 100%; border-collapse: collapse; margin-top: 24px; }
        .signatures td { padding: 10px 6px; vertical-align: bottom; }
        .sig-label { width: 20%; font-weight: 700; white-space: nowrap; }
        .sig-line {
            width: 48%;
            border-bottom: 1px solid #333;
            height: 34px;
            vertical-align: bottom;
            text-align: center;
        }
        .sig-line img { max-height: 32px; vertical-align: bottom; }
        .sig-name { width: 32%; font-size: 11px; padding-left: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('assets/images/logo.png') }}" alt="Logo">
    </div>
    <div class="title">FUND REQUISITION FORM</div>

    <table class="meta">
        <tr>
            <td class="col-left">
                <span class="field-label">Project/program:</span>
                <span class="field-value">{{ $request->project_program ?: ' ' }}</span>
            </td>
            <td class="col-right">
                <span class="field-label">Activity code:</span>
                <span class="field-value">{{ $request->activity_code ?: ' ' }}</span>
            </td>
        </tr>
        <tr>
            <td class="col-left">
                <span class="field-label">Activity Title:</span>
                <span class="field-value">{{ $request->activity_title ?: ' ' }}</span>
            </td>
            <td class="col-right">
                <span class="field-label">Budget code:</span>
                <span class="field-value">{{ $request->budget_code ?: ' ' }}</span>
            </td>
        </tr>
        <tr>
            <td class="col-left">
                <span class="field-label">Date of request:</span>
                <span class="field-value">{{ optional($request->date_of_request)->format('d/m/Y') ?: ' ' }}</span>
            </td>
            <td class="col-right">
                <span class="field-label">Date of requirement:</span>
                <span class="field-value">{{ optional($request->date_of_requirement)->format('d/m/Y') ?: ' ' }}</span>
            </td>
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

    <table class="signatures">
        <tr>
            <td class="sig-label">Requested by</td>
            <td class="sig-line">@if($request->user && $request->user->signature)<img src="{{ public_path($request->user->signature) }}">@endif</td>
            <td class="sig-name">{{ $request->user->name ?? '' }}</td>
        </tr>
        <tr>
            <td class="sig-label">Reviewed by</td>
            <td class="sig-line">@if($request->reviewedBy && $request->reviewedBy->signature)<img src="{{ public_path($request->reviewedBy->signature) }}">@endif</td>
            <td class="sig-name">{{ $request->reviewedBy->name ?? '' }}</td>
        </tr>
        <tr>
            <td class="sig-label">Approved by</td>
            <td class="sig-line">@if($request->approvedBy && $request->approvedBy->signature)<img src="{{ public_path($request->approvedBy->signature) }}">@endif</td>
            <td class="sig-name">{{ $request->approvedBy->name ?? '' }}</td>
        </tr>
        <tr>
            <td class="sig-label">Authorized by</td>
            <td class="sig-line">@if($request->authorizedBy && $request->authorizedBy->signature)<img src="{{ public_path($request->authorizedBy->signature) }}">@endif</td>
            <td class="sig-name">{{ $request->authorizedBy->name ?? '' }}</td>
        </tr>
    </table>
</body>
</html>
