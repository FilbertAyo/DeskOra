<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', config('app.name', 'Notification'))</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td, a { font-family: Arial, Helvetica, sans-serif !important; }
    </style>
    <![endif]-->
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f4f5;
            color: #18181b;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table { border-collapse: collapse; }
        img { border: 0; outline: none; text-decoration: none; }
        a { color: #18181b; }
        .wrapper { width: 100%; background-color: #f4f4f5; }
        .container {
            width: 100%;
            max-width: 560px;
            background-color: #ffffff;
            border: 1px solid #e4e4e7;
        }
        .brand {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #18181b;
        }
        .eyebrow {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #71717a;
            margin: 0 0 10px;
        }
        .title {
            font-family: Georgia, 'Times New Roman', Times, serif;
            font-size: 24px;
            line-height: 1.3;
            font-weight: 400;
            color: #18181b;
            margin: 0 0 20px;
        }
        .body-text,
        .body-text p,
        .body-text li {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            font-size: 15px;
            line-height: 1.65;
            color: #3f3f46;
        }
        .body-text p { margin: 0 0 14px; }
        .body-text strong { color: #18181b; font-weight: 600; }
        .meta-table td {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            padding: 10px 0;
            border-bottom: 1px solid #f4f4f5;
            vertical-align: top;
        }
        .meta-label {
            width: 34%;
            color: #71717a;
            padding-right: 12px !important;
        }
        .meta-value {
            color: #18181b;
            font-weight: 600;
        }
        .btn {
            display: inline-block;
            background-color: #18181b;
            color: #ffffff !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            font-size: 14px;
            font-weight: 600;
            line-height: 1;
            text-decoration: none;
            padding: 14px 22px;
            border-radius: 4px;
        }
        .panel {
            background-color: #fafafa;
            border: 1px solid #e4e4e7;
            border-radius: 8px;
            padding: 16px 18px;
            margin: 18px 0;
        }
        .panel-title {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #71717a;
            margin: 0 0 8px;
        }
        .panel-body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #18181b;
            margin: 0;
            white-space: pre-wrap;
        }
        .footer-text {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #a1a1aa;
        }
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .pad { padding: 28px 20px !important; }
            .title { font-size: 22px !important; }
        }
    </style>
    @stack('styles')
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;">
    <table role="presentation" class="wrapper" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f5;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" class="container" width="560" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:560px;background-color:#ffffff;border:1px solid #e4e4e7;">
                    <tr>
                        <td class="pad" style="padding: 28px 32px 20px; border-bottom: 1px solid #f4f4f5;">
                            <p class="brand" style="margin:0;">{{ config('app.name', 'DeskOra') }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td class="pad body-text" style="padding: 28px 32px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td class="pad footer-text" style="padding: 20px 32px 28px; border-top: 1px solid #f4f4f5;">
                            <p style="margin:0 0 6px;">This is an automated message. Please do not reply.</p>
                            <p style="margin:0;">Sent from <span style="color:#71717a;">portal@adilisha.or.tz</span></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
