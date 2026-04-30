<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($title ?? 'Error').' - '.config('app.name', 'Adilisha Portal') }}</title>

    <link rel="icon" href="{{ asset('assets/images/logo-icon.png') }}" type="image/png">
    <link rel="stylesheet" href="{{ asset('assets/fonts/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style-preset.css') }}">
</head>
<body class="bg-light">
    <div class="auth-main">
        <div class="auth-wrapper v3">
            <div class="auth-form">
              
                    <div class="text-center p-5">
                    
                        <h1 class="display-6 mb-2">{{ $code ?? 'Error' }}</h1>
                        <h4 class="mb-3">{{ $title ?? 'Something went wrong' }}</h4>
                        <p class="text-muted mb-4">{{ $message ?? 'Please try again later.' }}</p>

                        @hasSection('actions')
                            <div class="d-flex justify-content-center gap-2">
                                @yield('actions')
                            </div>
                        @else
                            <a href="{{ url('/') }}" class="btn btn-primary">
                                <i class="ti ti-home me-1"></i>
                                Back Home
                            </a>
                        @endif
                    </div>
              
            </div>
        </div>
    </div>
</body>
</html>
