<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $letter->reference_number }}</title>
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
            margin-top: 28px;
            margin-right: 0;
            margin-bottom: 0;
            margin-left: 0;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Calibri', DejaVu Sans, sans-serif;
            font-size: 11pt;
            color: #111;
            margin: 0;
            padding: 0 48px 72px;
            line-height: 1.35;
        }

        .letterhead {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .letterhead td {
            vertical-align: top;
            padding: 0;
        }

        .letterhead-logo img {
            max-height: 92px;
            max-width: 230px;
        }

        .letterhead-meta {
            text-align: right;
            font-size: 7pt;
            font-style: italic;
            font-weight: bold;
            color: #222;
            line-height: 1.35;
            padding-top: 4px;
        }

        .letterhead-rule {
            border: 0;
            border-top: 2px solid #111;
            margin: 0 0 16px;
        }

        .ref-row {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 16px;
        }

        .ref-row td {
            vertical-align: top;
            padding: 0;
            font-weight: bold;
            font-size: 11pt;
        }

        .ref-row .ref {
            font-style: italic;
            text-align: left;
        }

        .ref-row .date {
            text-align: right;
            white-space: nowrap;
        }

        .recipient {
            white-space: pre-line;
            text-transform: uppercase;
            font-weight: bold;
            line-height: 1.35;
            margin-bottom: 14px;
            font-size: 11pt;
        }

        .salutation {
            margin-bottom: 12px;
        }

        .subject {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin: 0 0 16px;
            line-height: 1.4;
            font-size: 11pt;
        }

        .body {
            margin-bottom: 22px;
            text-align: justify;
            line-height: 1.4;
            font-family: 'Calibri', DejaVu Sans, sans-serif;
            font-size: 11pt;
        }

        .body p {
            margin: 0 0 8px;
            text-align: justify;
        }

        .body ul, .body ol {
            margin: 0 0 8px 18px;
            padding: 0;
            text-align: justify;
        }

        .closing-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            page-break-inside: avoid;
        }

        .closing-table td {
            vertical-align: bottom;
            padding: 0;
        }

        .closing-block {
            text-align: center;
        }

        .closing-phrase {
            margin-bottom: 12px;
        }

        .closing-phrase.manual {
            margin-bottom: 8px;
        }

        .signature-space {
            height: 48px;
            width: 100%;
        }

        .signature-image {
            margin: 0 auto 0;
            text-align: center;
            height: 52px;
        }

        .signature-image img {
            max-height: 52px;
            max-width: 180px;
        }

        .signature-line {
            border-bottom: 1px solid #333;
            width: 220px;
            margin: 0 auto 10px;
            height: 1px;
        }

        .signatory-name {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11pt;
            margin-bottom: 2px;
        }

        .signatory-title {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10.5pt;
        }

        .qr-cell {
            width: 90px;
            text-align: right;
        }

        .qr-cell img {
            width: 78px;
            height: 78px;
        }

        .footer-bar {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 60px;
            background-color: #072649;
            color: #ffffff;
        }

        .footer-inner {
            margin-left: 18px;
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            height: 58px;
        }

        .footer-inner td {
            width: 33.33%;
            color: #ffffff;
            vertical-align: middle;
            text-align: center;
            padding: 0 12px;
            font-size: 10pt;
            white-space: nowrap;
        }

        .footer-item {
            display: inline-block;
            vertical-align: middle;
            line-height: 58px;
        }

        .footer-icon {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            vertical-align: middle;
            border: 0;
        }

        .footer-text {
            vertical-align: middle;
            color: #ffffff;
            font-weight: normal;
        }
    </style>
</head>
<body>
    @php
        $displayRef = preg_replace('/^REF\b/u', 'Ref', (string) $letter->reference_number);
        $physicalLines = preg_split("/\r\n|\n|\r/", (string) ($letterhead['physical_address'] ?? '')) ?: [];
        $bodyHtml = (string) $letter->body;
        if ($bodyHtml !== '' && $bodyHtml === strip_tags($bodyHtml)) {
            $bodyHtml = nl2br(e($bodyHtml));
        }
    @endphp

    <table class="letterhead">
        <tr>
            <td class="letterhead-logo" style="width: 48%;">
                @if(!empty($logoAbsolutePath) && is_file($logoAbsolutePath))
                    <img src="{{ $logoAbsolutePath }}" alt="Logo">
                @endif
            </td>
            <td class="letterhead-meta" style="width: 52%;">
                {{ $letterhead['organization_name'] ?? '' }}<br>
                {{ $letterhead['postal_address'] ?? '' }}<br>
                {{ $letterhead['city_country'] ?? '' }}<br>
                @foreach($physicalLines as $line)
                    @if(trim($line) !== '')
                        {{ $line }}<br>
                    @endif
                @endforeach
                {{ $letterhead['org_reg_no'] ?? '' }}
            </td>
        </tr>
    </table>

    <hr class="letterhead-rule">

    <table class="ref-row">
        <tr>
            <td class="ref">{{ $displayRef }}</td>
            <td class="date">{{ $letter->letter_date->format('d/m/Y') }}</td>
        </tr>
    </table>

    <div class="recipient">{{ mb_strtoupper((string) $letter->recipient, 'UTF-8') }}</div>

    <div class="salutation">{{ $letter->salutation ?: 'Dear Sir/Madam,' }}</div>

    <div class="subject">RE: {{ mb_strtoupper((string) $letter->subject, 'UTF-8') }}</div>

    <div class="body">{!! $bodyHtml !!}</div>

    <table class="closing-table">
        <tr>
            {{-- Equal side columns keep the signature block exactly centered --}}
            <td style="width: 90px;"></td>
            <td class="closing-block">
                @php
                    $isManualSignature = ! $letter->usesDigitalSignature();
                    $showDigitalSignature = ! empty($signatorySignaturePath)
                        && is_file($signatorySignaturePath)
                        && $letter->usesDigitalSignature();
                @endphp
                <div class="closing-phrase {{ $isManualSignature ? 'manual' : '' }}">{{ $letter->closing ?: 'In service,' }}</div>
                @if($showDigitalSignature)
                    <div class="signature-image">
                        <img src="{{ $signatorySignaturePath }}" alt="Signature">
                    </div>
                @elseif($isManualSignature)
                    <div class="signature-space"></div>
                @endif
                <div class="signature-line"></div>
                <div class="signatory-name">{{ mb_strtoupper((string) $letter->signatory_name, 'UTF-8') }}</div>
                <div class="signatory-title">{{ mb_strtoupper((string) $letter->signatory_title, 'UTF-8') }}</div>
            </td>
            <td class="qr-cell">
                @if(! empty($qrCodeAbsolutePath) && is_file($qrCodeAbsolutePath))
                    <img src="{{ $qrCodeAbsolutePath }}" alt="QR Code" width="78" height="78">
                @elseif(! empty($qrCodeDataUri))
                    <img src="{{ $qrCodeDataUri }}" alt="QR Code" width="78" height="78">
                @endif
            </td>
        </tr>
    </table>

    <div class="footer-bar">
        <table class="footer-inner">
            <tr>
                <td>
                    <span class="footer-item">
                        @if(!empty($footerPhoneIconPath) && is_file($footerPhoneIconPath))
                            <img class="footer-icon" src="{{ $footerPhoneIconPath }}" alt="" width="18" height="18">
                        @endif
                        <span class="footer-text">{{ $letterhead['phones'] ?? '' }}</span>
                    </span>
                </td>
                <td>
                    <span class="footer-item">
                        @if(!empty($footerEmailIconPath) && is_file($footerEmailIconPath))
                            <img class="footer-icon" src="{{ $footerEmailIconPath }}" alt="" width="18" height="18">
                        @endif
                        <span class="footer-text">{{ $letterhead['email'] ?? '' }}</span>
                    </span>
                </td>
                <td>
                    <span class="footer-item">
                        @if(!empty($footerGlobeIconPath) && is_file($footerGlobeIconPath))
                            <img class="footer-icon" src="{{ $footerGlobeIconPath }}" alt="" width="18" height="18">
                        @endif
                        <span class="footer-text">{{ $letterhead['website'] ?? '' }}</span>
                    </span>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
