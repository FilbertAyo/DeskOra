<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Notification')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* General styles for all emails */
        body {
            background-color: #f6f6f6;
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .email-container {
            background: #fff;
            max-width: 600px;
            margin: 30px auto;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            padding: 32px 24px;
        }

        .email-header {
            border-bottom: 1px solid #eaeaea;
            padding-bottom: 16px;
            margin-bottom: 24px;
            text-align: center;
        }

        .email-footer {
            border-top: 1px solid #eaeaea;
            padding-top: 16px;
            margin-top: 24px;
            font-size: 12px;
            color: #888;
            text-align: center;
        }

        h1,
        h2,
        h3 {
            color: #2d3748;
            margin-top: 0;
        }


        @media only screen and (max-width: 600px) {
            .email-container {
                padding: 16px 8px;
            }
        }
    </style>
    @stack('styles')
</head>

<body>
    <div class="email-container">

        <div class="email-body">
            @yield('content')
        </div>
        <div class="email-footer">
            <p>Thank you! 🚀</p>
            <p>
                This is an automated message sent from <strong>portal@adilisha.or.tz</strong>. Please do not reply to
                this email.
            </p>

        </div>
    </div>

</body>

</html>
