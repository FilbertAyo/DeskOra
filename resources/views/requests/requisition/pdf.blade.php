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
        .meta td { padding: 5px 6px; vertical-align: top; line-height: 1.6; }
        .meta .col-left { width: 58%; }
        .meta .col-right { width: 42%; }
        .meta .value {
            text-decoration: underline;
            word-wrap: break-word;
            white-space: normal;
        }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th, table.items td { border: 1px solid #0077b6; padding: 6px; }
        table.items th { background: #0077b6; color: #fff; }
        .total { text-align: right; font-weight: 700; margin-top: 8px; }
        .signatories { width: 100%; margin-top: 20px; border-collapse: collapse; }
        .signatories td { padding: 6px 8px; vertical-align: bottom; width: 33.33%; }
        .signatories .field-label { white-space: nowrap; }
        .signatories .field-value {
            display: inline;
            border-bottom: 1px solid #333;
            padding: 0 4px 2px;
            min-width: 120px;
            word-wrap: break-word;
            white-space: normal;
        }
        .signatories .sig-value {
            display: inline-block;
            border-bottom: 1px solid #333;
            min-width: 120px;
            min-height: 38px;
            padding: 0 4px 2px;
            vertical-align: bottom;
        }
        .signatories .sig-value img { max-height: 36px; vertical-align: bottom; }
        .signatory-row td { padding-bottom: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('assets/images/logo.png') }}" alt="Logo">
    </div>
    <div class="title">FUND REQUISITION FORM</div>

    <table class="meta">
        <tr>
            <td class="col-left"><strong>Project/program:</strong> <span class="value">{{ $request->project_program ?: ' ' }}</span></td>
            <td class="col-right"><strong>Activity code:</strong> <span class="value">{{ $request->activity_code ?: ' ' }}</span></td>
        </tr>
        <tr>
            <td class="col-left"><strong>Activity Title:</strong> <span class="value">{{ $request->activity_title ?: ' ' }}</span></td>
            <td class="col-right"><strong>Budget code:</strong> <span class="value">{{ $request->budget_code ?: ' ' }}</span></td>
        </tr>
        <tr>
            <td class="col-left"><strong>Date of request:</strong> <span class="value">{{ optional($request->date_of_request)->format('d/m/Y') ?: ' ' }}</span></td>
            <td class="col-right"><strong>Date of requirement:</strong> <span class="value">{{ optional($request->date_of_requirement)->format('d/m/Y') ?: ' ' }}</span></td>
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

    <table class="signatories">
        <tr class="signatory-row">
            <td>
                <span class="field-label">Requested by:</span>
                <span class="field-value">{{ $request->user->name ?? ' ' }}</span>
            </td>
            <td>
                <span class="field-label">Title:</span>
                <span class="field-value">{{ $request->user->title ?? ' ' }}</span>
            </td>
            <td>
                <span class="field-label">Signature:</span>
                <span class="sig-value">
                    @if ($request->user?->signature)
                        <img src="{{ public_path($request->user->signature) }}">
                    @endif
                </span>
            </td>
        </tr>
        <tr class="signatory-row">
            <td>
                <span class="field-label">Reviewed by:</span>
                <span class="field-value">{{ $request->reviewedBy->name ?? ' ' }}</span>
            </td>
            <td>
                <span class="field-label">Title:</span>
                <span class="field-value">{{ $request->reviewedBy->title ?? ' ' }}</span>
            </td>
            <td>
                <span class="field-label">Signature:</span>
                <span class="sig-value">
                    @if ($request->reviewedBy?->signature)
                        <img src="{{ public_path($request->reviewedBy->signature) }}">
                    @endif
                </span>
            </td>
        </tr>
        <tr class="signatory-row">
            <td>
                <span class="field-label">Approved by:</span>
                <span class="field-value">{{ $request->approvedBy->name ?? ' ' }}</span>
            </td>
            <td>
                <span class="field-label">Title:</span>
                <span class="field-value">{{ $request->approvedBy->title ?? ' ' }}</span>
            </td>
            <td>
                <span class="field-label">Signature:</span>
                <span class="sig-value">
                    @if ($request->approvedBy?->signature)
                        <img src="{{ public_path($request->approvedBy->signature) }}">
                    @endif
                </span>
            </td>
        </tr>
        <tr class="signatory-row">
            <td>
                <span class="field-label">Authorized by:</span>
                <span class="field-value">{{ $request->authorizedBy->name ?? ' ' }}</span>
            </td>
            <td>
                <span class="field-label">Title:</span>
                <span class="field-value">{{ $request->authorizedBy->title ?? ' ' }}</span>
            </td>
            <td>
                <span class="field-label">Signature:</span>
                <span class="sig-value">
                    @if ($request->authorizedBy?->signature)
                        <img src="{{ public_path($request->authorizedBy->signature) }}">
                    @endif
                </span>
            </td>
        </tr>
    </table>
</body>
</html>
