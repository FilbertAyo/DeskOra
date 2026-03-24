<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        document.body.addEventListener("click", function (event) {
            if (event.target.closest(".permission-alert")) {
                Swal.fire({
                    icon: "error",
                    title: "Oops...",
                    text: "You don't have permission to perform this action!",
                });
            }
        });
    });
</script>

@php
    $globalToasts = [];

    if (session('success')) {
        $globalToasts[] = [
            'title' => 'Success',
            'message' => session('success'),
            'icon' => 'ti ti-circle-check text-success',
            'delay' => 4500,
        ];
    }

    if (session('error')) {
        $globalToasts[] = [
            'title' => 'Error',
            'message' => session('error'),
            'icon' => 'ti ti-alert-circle text-danger',
            'delay' => 5500,
        ];
    }

    if (session('warning')) {
        $globalToasts[] = [
            'title' => 'Warning',
            'message' => session('warning'),
            'icon' => 'ti ti-alert-triangle text-warning',
            'delay' => 5000,
        ];
    }

    if (session('info')) {
        $globalToasts[] = [
            'title' => 'Info',
            'message' => session('info'),
            'icon' => 'ti ti-info-circle text-info',
            'delay' => 5000,
        ];
    }

    if (isset($errors) && $errors->any()) {
        foreach ($errors->all() as $validationError) {
            $globalToasts[] = [
                'title' => 'Validation Error',
                'message' => $validationError,
                'icon' => 'ti ti-alert-circle text-danger',
                'delay' => 6000,
            ];
        }
    }
@endphp

@if(!empty($globalToasts))
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1090; width: min(92vw, 360px);">
        <div class="toast-container w-100">
            @foreach($globalToasts as $toast)
                <div class="toast hide w-100 mb-2 shadow-sm border-0 js-global-live-toast"
                     role="alert"
                     aria-live="assertive"
                     aria-atomic="true"
                     data-delay="{{ $toast['delay'] }}">
                    <div class="toast-header">
                        <i class="{{ $toast['icon'] }} me-2"></i>
                        <strong class="me-auto">{{ $toast['title'] }}</strong>
                        <small>Now</small>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        {{ $toast['message'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toasts = document.querySelectorAll('.js-global-live-toast');
            if (!toasts || toasts.length === 0) return;

            toasts.forEach(function (el) {
                if (window.bootstrap && bootstrap.Toast) {
                    const delay = parseInt(el.getAttribute('data-delay') || '4500', 10);
                    const instance = new bootstrap.Toast(el, {
                        autohide: true,
                        delay: Number.isNaN(delay) ? 4500 : delay
                    });
                    instance.show();
                }
            });
        });
    </script>
@endif
