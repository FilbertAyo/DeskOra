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
            position: relative;
            background: var(--bs-primary);
            color: #fff;
            height: 100vh;
            overflow: hidden;
        }

        .auth-features-col::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 12% 0%, rgba(255, 255, 255, 0.16), transparent 52%);
            pointer-events: none;
        }

        .auth-features-panel {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            padding: 2.5rem 3rem 1.75rem;
            overflow-y: auto;
            overflow-x: hidden;
            overscroll-behavior: contain;
            color: #fff;
        }

        .auth-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .auth-brand__mark {
            flex-shrink: 0;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.625rem;
            background: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .auth-brand__mark img {
            width: 1.5rem;
            height: 1.5rem;
            object-fit: contain;
        }

        .auth-brand__name {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            color: #fff;
            line-height: 1;
        }

        .auth-features-panel__hero {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 2.5rem 0 1.5rem;
            max-width: 36rem;
        }

        .auth-hero-kicker {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .auth-hero-kicker__bar {
            width: 3px;
            height: 1.15rem;
            border-radius: 99px;
            background: rgba(255, 255, 255, 0.85);
        }

        .auth-hero-kicker__text {
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.72);
        }

        .auth-features-panel__title {
            font-size: clamp(2rem, 3.2vw, 2.75rem);
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -0.03em;
            margin-bottom: 1rem;
            color: #fff;
        }

        .auth-features-panel__subtitle {
            color: rgba(255, 255, 255, 0.78);
            font-size: 1rem;
            line-height: 1.6;
            max-width: 32rem;
            margin-bottom: 2rem;
        }

        .auth-overview-card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 1rem;
            padding: 1.15rem 1.25rem 1.2rem;
            backdrop-filter: blur(12px);
        }

        .auth-overview-card__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.1rem;
        }

        .auth-overview-card__label,
        .auth-overview-card__meta,
        .auth-overview-stat__label,
        .auth-overview-card__section-label {
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.62);
        }

        .auth-overview-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            padding-bottom: 1.1rem;
            margin-bottom: 1.1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.14);
        }

        .auth-overview-stat__value {
            display: block;
            margin-top: 0.35rem;
            font-size: 0.9375rem;
            font-weight: 600;
            color: #fff;
            line-height: 1.3;
        }

        .auth-overview-card__section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.85rem;
        }

        .auth-overview-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .auth-overview-item__icon {
            flex-shrink: 0;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.16);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .auth-overview-item__title {
            font-size: 0.9375rem;
            font-weight: 600;
            color: #fff;
            line-height: 1.2;
        }

        .auth-overview-item__desc {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.68);
            margin-top: 0.15rem;
        }

        .auth-overview-actions {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-left: auto;
        }

        .auth-overview-action {
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }

        .auth-overview-action--ghost {
            color: rgba(255, 255, 255, 0.7);
            background: transparent;
        }

        .auth-overview-action--ok {
            color: var(--bs-primary);
            background: #fff;
        }

        .auth-features-panel__footer {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.72);
        }

        .auth-features-panel__footer-item {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .auth-features-panel__footer-sep {
            width: 1px;
            height: 0.85rem;
            background: rgba(255, 255, 255, 0.28);
        }

        .auth-features-panel .auth-deskora-credit,
        .auth-features-panel .auth-deskora-credit.text-muted {
            color: rgba(255, 255, 255, 0.72) !important;
        }

        .auth-features-panel .auth-deskora-credit span {
            color: inherit;
        }

        .auth-features-panel .auth-deskora-credit img {
            filter: brightness(0) invert(1);
            opacity: 0.92;
        }

        .auth-form-col {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            padding: 2rem 1.5rem;
            background: #f6f7f9;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .auth-form-wrapper {
            width: 100%;
            max-width: 420px;
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
            box-shadow: none;
            background: transparent;
        }

        .auth-form-wrapper .auth-card .card-body {
            padding: 0;
        }

        .auth-form-heading {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--pc-heading-color, #1f2937);
            margin-bottom: 0.4rem;
        }

        .auth-form-subheading {
            color: #6b7280;
            font-size: 0.95rem;
            margin-bottom: 1.75rem;
        }

        .auth-form-wrapper .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.4rem;
        }

        .auth-form-wrapper .form-control {
            border-radius: 0.7rem;
            min-height: 2.85rem;
            padding: 0.65rem 0.9rem;
            border-color: #e5e7eb;
            background: #fff;
        }

        .auth-form-wrapper .form-control:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.15);
        }

        .auth-password-field {
            position: relative;
        }

        .auth-password-field .form-control {
            padding-right: 2.75rem;
        }

        .auth-password-toggle {
            position: absolute;
            top: 50%;
            right: 0.7rem;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #9ca3af;
            padding: 0.15rem;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .auth-password-toggle:hover,
        .auth-password-toggle:focus {
            color: #4b5563;
        }

        .auth-form-wrapper .btn-primary {
            border-radius: 0.7rem;
            min-height: 2.9rem;
            font-weight: 600;
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

<body data-pc-preset="preset-1" data-pc-direction="ltr" data-pc-theme="light">
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
