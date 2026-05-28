<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Work Period Opened</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1f2937; background: #f8fafc; margin: 0; padding: 24px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 640px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
        <tr>
            <td style="background: #16a34a; color: #ffffff; padding: 16px 20px;">
                <h2 style="margin: 0; font-size: 20px;">New Work Period Opened</h2>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px;">
                <p style="margin-top: 0;">Hello {{ $staffName }},</p>
                <p>A new work period is now open for submissions.</p>
                <p>
                    <strong>Period:</strong>
                    Week {{ $period->week_number }}, {{ $period->year }} ({{ $period->date_range }})
                </p>
                <p>Please submit your weekly documents within the open period window.</p>
                <p style="margin: 24px 0;">
                    <a href="{{ $documentsUrl }}" style="display: inline-block; background: #16a34a; color: #ffffff; text-decoration: none; padding: 10px 14px; border-radius: 6px;">
                        Open Documents
                    </a>
                </p>
                <p style="margin-bottom: 0;">Thank you.</p>
            </td>
        </tr>
    </table>
</body>
</html>
