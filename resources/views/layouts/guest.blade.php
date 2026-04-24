<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Adilisha Portal') }} — {{ config('branding.product_name') }}</title>

     <!-- [Favicon] icon -->
<link rel="icon" href="{{ asset('assets/images/logo-icon.png') }}" type="image/png">
<link rel="apple-touch-icon" href="{{ asset('assets/images/logo-icon.png') }}">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" id="main-font-link">
<link rel="stylesheet" href="{{ asset('assets/fonts/tabler-icons.min.css') }}" >
<link rel="stylesheet" href="{{ asset('assets/fonts/feather.css') }}" >
<link rel="stylesheet" href="{{ asset('assets/fonts/fontawesome.css') }}" >
<link rel="stylesheet" href="{{ asset('assets/fonts/material.css') }}" >
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}" id="main-style-link" >
<link rel="stylesheet" href="{{ asset('assets/css/style-preset.css') }}" >
</head>

<body class="bg-light">
    <div class="wrapper vh-100">
        <div class="row align-items-center h-100">

            <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <!-- [ Pre-loader ] End -->

    <div class="auth-main">
        <div class="auth-wrapper v3">
            <div class="auth-form">
                <div class="auth-header text-center">
                    <a href="#">
                        <img src="{{ asset('assets/images/logo.png') }}" alt="{{ config('app.name', 'Logo') }}" style="width: 100px; height: auto;">
                    </a>
                    <div class="mt-3 d-flex justify-content-center">
                        <x-deskora-credit />
                    </div>
                </div>
            {{ $slot }}

            <div class="auth-footer row">
                    <div class="col my-1">
                        <p class="m-0">
                            Developed by <a href="#">DeskOra</a>
                        </p>
                    </div>
                    <div class="col-auto">
                        <ul class="list-inline footer-link mb-0">
                            <li class="list-inline-item"><a href="https://adilisha.or.tz" target="_blank">Our website</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>


        </div>
    </div>


      <!-- [ Main Content ] end -->
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
</body>

</html>
