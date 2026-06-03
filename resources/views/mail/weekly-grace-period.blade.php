<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grace Period Alert</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1f2937; background: #f8fafc; margin: 0; padding: 24px;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 640px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
        <tr>
            <td style="background: #d97706; color: #ffffff; padding: 16px 20px;">
                <h2 style="margin: 0; font-size: 20px;">Grace period is active</h2>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px;">
                <p style="margin-top: 0;">Hello {{ $staffName }},</p>
                <p>
                    Your administrator extended the deadline for your <strong>{{ $activityLabel }}</strong>
                    for <strong>{{ $periodLabel }}</strong>.
                </p>
                <p>
                    You can still create or submit until
                    <strong>{{ $graceEndsAt->format('M d, Y H:i') }}</strong>.
                </p>
                <p style="margin: 24px 0;">
                    <a href="{{ $actionUrl }}" style="display: inline-block; background: #d97706; color: #ffffff; text-decoration: none; padding: 10px 14px; border-radius: 6px;">
                        Open and submit now
                    </a>
                </p>
                <p style="margin-bottom: 0; font-size: 14px; color: #6b7280;">
                    If you have already submitted, you can ignore this message.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
