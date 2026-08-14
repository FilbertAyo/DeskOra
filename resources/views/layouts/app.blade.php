<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Adilisha Portal') }} — {{ config('branding.product_name') }}</title>

  <!-- [Favicon] icon -->
  <link rel="icon" href="{{ asset('assets/images/logo-icon.png') }}" type="image/png">
  <link rel="apple-touch-icon" href="{{ asset('assets/images/logo-icon.png') }}">
  @include('partials.pwa-head')
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" id="main-font-link">
<!-- [Tabler Icons] https://tablericons.com -->
<link rel="stylesheet" href="{{ asset('assets/fonts/tabler-icons.min.css') }}" >
<!-- [Feather Icons] https://feathericons.com -->
<link rel="stylesheet" href="{{ asset('assets/fonts/feather.css') }}" >
<!-- [Font Awesome Icons] https://fontawesome.com/icons -->
<link rel="stylesheet" href="{{ asset('assets/fonts/fontawesome.css') }}" >
<!-- [Material Icons] https://fonts.google.com/icons -->
<link rel="stylesheet" href="{{ asset('assets/fonts/material.css') }}" >
<!-- [Template CSS Files] -->
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" id="main-style-link" >
<link rel="stylesheet" href="{{ asset('assets/css/style-preset.css') }}" >
<link rel="stylesheet" href="{{ asset('assets/css/deskora-document-thead.css') }}" >

   
</head>

<body data-pc-preset="preset-1" data-pc-direction="ltr" data-pc-theme="light">
    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <!-- [ Pre-loader ] End -->

    @include('layouts.aside')
    @include('layouts.navigation')

    <div class="pc-container">
        <div class="pc-content">
            @include('elements.alert')

            @if (Auth::user()->status == 'active')
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-12">
                            {{ $slot }}
                        </div>
                    </div>
                </div>

                @if ($showApprovalGateModal ?? false)
                    @include('approvals.gate-modal')
                @elseif ($showStaffNominationModal ?? false)
                    @include('appreciation.nomination-modal')
                @endif
            @else
                @include('errors.deactivated')
            @endif
        </div>
    </div>

    @include('layouts.footer')

    @if(Auth::check() && Auth::user()->status === 'active')
        @include('partials.anonymous-feedback-fab')
    @endif

    @if(config('services.groq.api_key') && Auth::check() && Auth::user()->status === 'active')
        @include('partials.deskora-ai-assistant')
    @endif

    @if(Auth::check() && Auth::user()->status === 'active')
        @include('partials.deskora-go')
        @include('partials.ai-assist-actions')
        @include('partials.pwa')
    @endif


  {{-- Heavy chart/editor scripts load via @stack('scripts') / partials on pages that need them. --}}
  <!-- Required Js -->
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
  
    

    @stack('scripts')

    @if(Auth::check() && Auth::user()->status === 'active')
        <script>
            (function () {
                const endpoint = @json(route('analytics.events.store'));
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (!endpoint || !token) return;

                const recent = new Map();

                function trackFeature(featureKey, properties) {
                    if (!featureKey || typeof featureKey !== 'string') return;

                    const now = Date.now();
                    const last = recent.get(featureKey) || 0;
                    if (now - last < 1500) return;
                    recent.set(featureKey, now);

                    fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            feature_key: featureKey,
                            properties: properties || null,
                        }),
                        keepalive: true,
                    }).catch(function () {});
                }

                window.trackFeatureUsage = trackFeature;

                document.addEventListener('click', function (event) {
                    const target = event.target.closest('[data-feature]');
                    if (!target) return;
                    trackFeature(target.getAttribute('data-feature'));
                }, true);
            })();
        </script>
    @endif

</body>

</html>
