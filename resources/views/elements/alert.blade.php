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
        $globalToasts = array_merge(
            $globalToasts,
            array_map(static function ($validationError) {
                return [
                    'title' => 'Validation Error',
                    'message' => $validationError,
                    'icon' => 'ti ti-alert-circle text-danger',
                    'delay' => 6000,
                ];
            }, $errors->all())
        );
    }
@endphp

@if(!empty($globalToasts))
    {{-- Matches dashboard action-log toast stack (border-0 shadow-sm, py-2, flex column); anchored top-right --}}
    <div class="global-toast-stack position-fixed top-0 end-0 p-2 p-sm-3 d-flex flex-column gap-2 align-items-stretch"
         style="z-index: 1090; width: 100%; max-width: min(22rem, calc(100vw - 1rem)); box-sizing: border-box; pointer-events: none;">
        @foreach($globalToasts as $toast)
            <div class="toast hide border-0 shadow-sm action-toast w-100 js-global-live-toast"
                 role="alert"
                 aria-live="assertive"
                 aria-atomic="true"
                 data-delay="{{ $toast['delay'] }}"
                 style="max-width: 100%; pointer-events: auto;">
                <div class="toast-header py-2 align-items-center">
                    <i class="{{ $toast['icon'] }} me-2 flex-shrink-0"></i>
                    <strong class="me-auto text-truncate" style="min-width: 0;">{{ $toast['title'] }}</strong>
                    <small class="text-muted flex-shrink-0 ms-1">Now</small>
                    <button type="button" class="btn-close ms-2 flex-shrink-0" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body py-2 text-break" style="overflow-wrap: anywhere; word-break: break-word;">
                    <p class="mb-0 small">{{ $toast['message'] }}</p>
                </div>
            </div>
        @endforeach
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
