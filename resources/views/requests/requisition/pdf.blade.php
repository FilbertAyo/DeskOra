<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fund Requisition Form</title>
    <style>
        @font-face {
            font-family: 'Calibri';
            font-style: normal;
            font-weight: normal;
            src: url('{{ $calibriRegularPath }}') format('truetype');
        }
        @font-face {
            font-family: 'Calibri';
            font-style: normal;
            font-weight: bold;
            src: url('{{ $calibriBoldPath }}') format('truetype');
        }

        @page {
            margin-top: 40px;
            margin-right: 50px;
            margin-bottom: 88px;
            margin-left: 50px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Calibri', DejaVu Sans, sans-serif;
            font-size: 12pt;
            color: #111;
            margin: 0;
            padding: 0 0 72px;
        }

        .header {
            text-align: center;
            margin-bottom: 8px;
        }

        .header img {
            max-height: 78px;
            max-width: 220px;
        }

        .title {
            text-align: center;
            font-weight: bold;
            font-size: 14pt;
            margin: 10px 0 16px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 12pt;
        }

        .meta td {
            padding: 4px 0 8px;
            vertical-align: bottom;
        }

        .meta .label {
            white-space: nowrap;
            padding-right: 4px;
        }

        .meta .line {
            border-bottom: 1px solid #222;
            padding: 0 4px 1px;
            min-height: 16px;
            display: inline-block;
            width: 100%;
            word-wrap: break-word;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            font-size: 12pt;
            margin-top: 4px;
        }

        table.items th,
        table.items td {
            border: 1px solid #1a3a6b;
            padding: 5px 6px;
            vertical-align: top;
        }

        table.items th {
            background: #1a3a6b;
            color: #fff;
            font-weight: bold;
            text-align: center;
        }

        table.items td.sn,
        table.items td.unit,
        table.items td.cost {
            text-align: center;
        }

        table.items td.cost {
            text-align: right;
        }

        table.items .total-label {
            text-align: right;
            font-weight: bold;
        }

        table.items .total-amount {
            background: #cfe8f5;
            font-weight: bold;
            text-align: left;
        }

        .receipt-note {
            text-align: right;
            font-size: 9pt;
            font-style: italic;
            margin: 4px 0 18px;
            color: #333;
        }

        .signatories {
            width: 100%;
            border-collapse: collapse;
            font-size: 12pt;
            margin-top: 8px;
            margin-bottom: 12px;
        }

        .signatories td {
            padding: 8px 4px 10px;
            vertical-align: bottom;
        }

        .signatories .label {
            white-space: nowrap;
        }

        .signatories .value {
            display: inline-block;
            border-bottom: 1px solid #222;
            min-width: 110px;
            padding: 0 4px 1px;
            word-wrap: break-word;
        }

        .signatories .sig {
            display: inline-block;
            border-bottom: 1px solid #222;
            min-width: 110px;
            min-height: 34px;
            padding: 0 4px 1px;
            vertical-align: bottom;
        }

        .signatories .sig img {
            max-height: 32px;
            vertical-align: bottom;
        }

        .footer-qr {
            position: fixed;
            right: 0;
            bottom: 12px;
            text-align: right;
            z-index: 10;
        }

        .footer-qr img {
            width: 52px;
            height: 52px;
        }

        .meta .line-continue {
            border-bottom: 1px solid #222;
            padding: 0 4px 1px;
            min-height: 18px;
            display: block;
            width: 100%;
            word-wrap: break-word;
            line-height: 1.35;
        }

        .meta td.title-continue {
            padding-top: 2px;
            padding-bottom: 2px;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ $logoAbsolutePath }}" alt="Logo">
    </div>

    <div class="title">FUND REQUISITION FORM</div>

    @php
        $activityTitle = trim((string) ($request->activity_title ?? ''));
        // Match paper form: line 1 beside label, then up to 2 full-width underline rows.
        $titleLineMax = [40, 100, 100];
        $titleLines = ['', '', ''];
        $words = $activityTitle !== ''
            ? (preg_split('/\s+/u', $activityTitle, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            : [];
        $lineIdx = 0;

        foreach ($words as $word) {
            if ($lineIdx > 2) {
                $titleLines[2] = trim($titleLines[2].' '.$word);
                continue;
            }

            $candidate = $titleLines[$lineIdx] === '' ? $word : $titleLines[$lineIdx].' '.$word;
            if (mb_strlen($candidate) <= $titleLineMax[$lineIdx] || $titleLines[$lineIdx] === '') {
                $titleLines[$lineIdx] = $candidate;
                continue;
            }

            $lineIdx++;
            if ($lineIdx > 2) {
                $titleLines[2] = trim($titleLines[2].' '.$word);
                continue;
            }
            $titleLines[$lineIdx] = $word;
        }

        $titleLine1 = $titleLines[0] !== '' ? $titleLines[0] : "\u{00A0}";
        $titleNeedsContinue = $titleLines[1] !== '' || $titleLines[2] !== '';
        // When title overflows, always show both continuation underlines (form-style).
        $titleContinueLines = $titleNeedsContinue
            ? [
                $titleLines[1] !== '' ? $titleLines[1] : "\u{00A0}",
                $titleLines[2] !== '' ? $titleLines[2] : "\u{00A0}",
            ]
            : [];

        $items = $request->requisitionItems ?? collect();
        $charsPerDescLine = 55;
        $usedItemLines = 0;
        foreach ($items as $item) {
            $descLen = mb_strlen(trim((string) ($item->description ?? '')));
            $usedItemLines += max(1, (int) ceil(max(1, $descLen) / $charsPerDescLine));
        }
        if ($items->isEmpty()) {
            $usedItemLines = 0;
        }

        // Keep form on one page: fewer blank rows when descriptions/title consume vertical space.
        $baseMinRows = 9;
        $extraLines = max(0, $usedItemLines - $items->count());
        $extraLines += count($titleContinueLines);
        $minRows = max($items->count(), $baseMinRows - $extraLines);
        $blankRows = max(0, $minRows - $items->count());
    @endphp

    <table class="meta">
        <tr>
            <td colspan="6">
                <span class="label">Project/program:</span>
                <span class="line" style="width: 78%;">{{ $request->project_program ?: "\u{00A0}" }}</span>
            </td>
        </tr>
        <tr>
            <td style="width: 12%;" class="label">Activity code:</td>
            <td style="width: 18%;">
                <span class="line">{{ $request->activity_code ?: "\u{00A0}" }}</span>
                @if ($request->is_off_plan)
                    <div style="font-size: 9px; color: #555; margin-top: 2px;">Not on the year plan</div>
                @endif
            </td>
            <td style="width: 12%;" class="label">Activity Title:</td>
            <td style="width: 58%;" colspan="3"><span class="line">{{ $titleLine1 }}</span></td>
        </tr>
        @foreach ($titleContinueLines as $continueLine)
            <tr>
                <td colspan="6" class="title-continue">
                    <span class="line-continue">{{ $continueLine }}</span>
                </td>
            </tr>
        @endforeach
        <tr>
            <td class="label">Budget code:</td>
            <td><span class="line">{{ $request->budget_code ?: "\u{00A0}" }}</span></td>
            <td class="label">Date of request:</td>
            <td style="width: 18%;"><span class="line">{{ optional($request->date_of_request)->format('d/m/Y') ?: "\u{00A0}" }}</span></td>
            <td style="width: 16%;" class="label">Date of requirement:</td>
            <td style="width: 24%;"><span class="line">{{ optional($request->date_of_requirement)->format('d/m/Y') ?: "\u{00A0}" }}</span></td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 8%;">S/NO</th>
                <th style="width: 54%;">DESCRIPTION</th>
                <th style="width: 14%;">UNIT</th>
                <th style="width: 24%;">COST</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td class="sn">{{ $item->serial_no }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="unit">{{ $item->unit }}</td>
                    <td class="cost">{{ number_format((float) $item->cost, 2) }}</td>
                </tr>
            @endforeach
            @for ($i = 0; $i < $blankRows; $i++)
                <tr>
                    <td class="sn">&nbsp;</td>
                    <td>&nbsp;</td>
                    <td class="unit">&nbsp;</td>
                    <td class="cost">&nbsp;</td>
                </tr>
            @endfor
            <tr>
                <td class="total-label" colspan="3">TOTAL AMOUNT</td>
                <td class="total-amount">TSh {{ number_format((float) $request->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="receipt-note">Don't forget to attach receipts!</div>

    <table class="signatories">
        <tr>
            <td style="width: 38%;">
                <span class="label">Requested by:</span>
                <span class="value">{{ $request->user->name ?? "\u{00A0}" }}</span>
            </td>
            <td style="width: 31%;">
                <span class="label">Title:</span>
                <span class="value">{{ $request->user->title ?? "\u{00A0}" }}</span>
            </td>
            <td style="width: 31%;">
                <span class="label">Signature:</span>
                <span class="sig">
                    @if ($request->user?->signature && is_file(public_path($request->user->signature)))
                        <img src="{{ public_path($request->user->signature) }}">
                    @endif
                </span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Reviewed by:</span>
                <span class="value">{{ $request->reviewedBy->name ?? "\u{00A0}" }}</span>
            </td>
            <td>
                <span class="label">Title:</span>
                <span class="value">{{ $request->reviewedBy->title ?? "\u{00A0}" }}</span>
            </td>
            <td>
                <span class="label">Signature:</span>
                <span class="sig">
                    @if ($request->reviewedBy?->signature && is_file(public_path($request->reviewedBy->signature)))
                        <img src="{{ public_path($request->reviewedBy->signature) }}">
                    @endif
                </span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Approved by:</span>
                <span class="value">{{ $request->approvedBy->name ?? "\u{00A0}" }}</span>
            </td>
            <td>
                <span class="label">Title:</span>
                <span class="value">{{ $request->approvedBy->title ?? "\u{00A0}" }}</span>
            </td>
            <td>
                <span class="label">Signature:</span>
                <span class="sig">
                    @if ($request->approvedBy?->signature && is_file(public_path($request->approvedBy->signature)))
                        <img src="{{ public_path($request->approvedBy->signature) }}">
                    @endif
                </span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Authorized by:</span>
                <span class="value">{{ $request->authorizedBy->name ?? "\u{00A0}" }}</span>
            </td>
            <td>
                <span class="label">Title:</span>
                <span class="value">{{ $request->authorizedBy->title ?? "\u{00A0}" }}</span>
            </td>
            <td>
                <span class="label">Signature:</span>
                <span class="sig">
                    @if ($request->authorizedBy?->signature && is_file(public_path($request->authorizedBy->signature)))
                        <img src="{{ public_path($request->authorizedBy->signature) }}">
                    @endif
                </span>
            </td>
        </tr>
    </table>

    @if (! empty($qrCodeDataUri))
        <div class="footer-qr">
            <img src="{{ $qrCodeDataUri }}" alt="QR">
        </div>
    @endif
</body>
</html>
