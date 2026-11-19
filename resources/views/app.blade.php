@php
    $systemBranding = app(\App\Services\SystemSettingService::class)->branding();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ $systemBranding['appName'] }} — {{ $systemBranding['productName'] }}</title>

        <link rel="icon" href="{{ $systemBranding['logoIcon'] }}" type="image/png">
        <link rel="apple-touch-icon" href="{{ $systemBranding['logoIcon'] }}">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        @if (! empty($systemBranding['primaryColor']))
            <style>
                :root {
                    --primary: {{ $systemBranding['primaryColor'] }};
                    --ring: {{ $systemBranding['primaryColor'] }};
                    --sidebar-primary: {{ $systemBranding['primaryColor'] }};
                    --sidebar-ring: {{ $systemBranding['primaryColor'] }};
                }
            </style>
        @endif

        @routes
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-background font-sans antialiased">
        @inertia
    </body>
</html>
