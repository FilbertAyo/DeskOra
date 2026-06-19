<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Adilisha Portal') }} — {{ config('branding.product_name') }}</title>

    <link rel="icon" href="{{ asset('assets/images/logo-icon.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('assets/images/logo-icon.png') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" id="main-font-link">
    <link rel="stylesheet" href="{{ asset('assets/fonts/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/feather.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/fontawesome.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/material.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" id="main-style-link">
    <link rel="stylesheet" href="{{ asset('assets/css/style-preset.css') }}">

    <style>
        @media (min-width: 992px) {
            html,
            body {
                height: 100%;
                overflow: hidden;
            }
        }

        .auth-split {
            height: 100vh;
            background: #fff;
            overflow: hidden;
        }

        .auth-split > .container-fluid,
        .auth-split .row.auth-split__row {
            height: 100%;
        }

        .auth-features-col {
            background: #fff;
            color: var(--bs-body-color);
            height: 100vh;
            overflow: hidden;
            border-right: 1px solid var(--bs-border-color);
        }

        .auth-features-panel {
            height: 100vh;
            padding: 2.5rem 2.75rem;
            overflow-y: auto;
            overflow-x: hidden;
            overscroll-behavior: contain;
        }

        .auth-features-panel__logo {
            width: 72px;
            height: auto;
            margin-bottom: 1rem;
        }

        .auth-features-panel__title {
            font-size: 1.65rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--bs-heading-color);
        }

        .auth-features-panel__subtitle {
            color: var(--bs-secondary-color);
            font-size: 0.9375rem;
            line-height: 1.55;
            max-width: 34rem;
            margin-bottom: 0;
        }

        .auth-features-panel__body {
            margin-top: 1.75rem;
        }

        .auth-features-panel__group + .auth-features-panel__group {
            margin-top: 1.25rem;
        }

        .auth-features-panel__group-label {
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--bs-secondary-color);
            margin-bottom: 0.625rem;
        }

        .auth-feature-card {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.75rem 0.875rem;
            border-radius: 0.625rem;
            background: #f8f9fa;
            border: 1px solid var(--bs-border-color);
            height: 100%;
            transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .auth-feature-card:hover {
            background: #fff;
            border-color: rgba(var(--bs-primary-rgb), 0.25);
            box-shadow: 0 4px 12px rgba(28, 35, 47, 0.06);
        }

        .auth-feature-card__icon {
            flex-shrink: 0;
            width: 2rem;
            height: 2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            background: rgba(var(--bs-primary-rgb), 0.1);
            color: var(--bs-primary);
            font-size: 1rem;
        }

        .auth-feature-card__title {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--bs-heading-color);
            line-height: 1.3;
        }

        .auth-feature-card__desc {
            font-size: 0.6875rem;
            color: var(--bs-secondary-color);
            line-height: 1.4;
            margin-top: 0.125rem;
        }

        .auth-features-panel__footer {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--bs-border-color);
        }

        .auth-form-col {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            padding: 1.5rem;
            background: #f8f9fa;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .auth-form-wrapper {
            width: 100%;
            max-width: 440px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .auth-form-wrapper .auth-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .auth-form-wrapper .auth-header img {
            width: 88px;
            height: auto;
        }

        .auth-form-wrapper .auth-card {
            border: none;
            box-shadow: 0 8px 24px rgba(28, 35, 47, 0.08);
            border-radius: 0.75rem;
        }

        .auth-form-wrapper .auth-card .card-body {
            padding: 2rem;
        }

        .auth-form-wrapper .auth-footer {
            margin-top: 1.5rem;
            padding-top: 0;
        }

        .auth-form-wrapper .auth-footer p,
        .auth-form-wrapper .auth-footer a {
            font-size: 0.8125rem;
            color: var(--bs-secondary-color);
        }

        @media (min-width: 992px) {
            .auth-form-col .auth-header.d-lg-none {
                display: none !important;
            }
        }

        @media (max-width: 991.98px) {
            .auth-split {
                height: auto;
                overflow: visible;
            }

            .auth-form-col {
                height: auto;
                min-height: 100vh;
            }
        }
    </style>
</head>

<body>
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>

    <div class="auth-main auth-split">
        <div class="container-fluid g-0">
            <div class="row g-0 auth-split__row">
                <div class="col-lg-6 d-none d-lg-block auth-features-col">
                    <x-auth-features-panel />
                </div>

                <div class="col-lg-6 auth-form-col">
                    <div class="auth-form-wrapper">
                        <div class="auth-header d-lg-none">
                            <a href="#">
                                <img src="{{ asset('assets/images/logo.png') }}" alt="{{ config('app.name', 'Logo') }}">
                            </a>
                            <div class="mt-3 d-flex justify-content-center">
                                <x-deskora-credit />
                            </div>
                        </div>

                        {{ $slot }}

                    
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('assets/js/plugins/popper.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/simplebar.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/js/fonts/custom-font.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/feather.min.js') }}"></script>
    <script src="{{ asset('assets/js/pcoded.js') }}"></script>

    <script>layout_change('light');</script>
    <script>change_box_container('false');</script>
    <script>layout_rtl_change('false');</script>
    <script>preset_change("preset-1");</script>
    <script>font_change("Public-Sans");</script>
</body>

</html>
