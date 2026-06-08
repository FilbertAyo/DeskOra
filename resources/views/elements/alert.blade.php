@once
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/notifier.css') }}">
    <style>
        .notifier-container {
            z-index: 2000 !important;
        }
    </style>
    <script src="{{ asset('assets/js/plugins/notifier.js') }}"></script>
@endonce

@php
    $globalNotifications = [];

    if (session('success')) {
        $globalNotifications[] = [
            'title' => 'Success',
            'message' => session('success'),
            'type' => 'success',
            'delay' => 4500,
        ];
    }

    if (session('error')) {
        $globalNotifications[] = [
            'title' => 'Error',
            'message' => session('error'),
            'type' => 'danger',
            'delay' => 5500,
        ];
    }

    if (session('warning')) {
        $globalNotifications[] = [
            'title' => 'Warning',
            'message' => session('warning'),
            'type' => 'warning',
            'delay' => 5000,
        ];
    }

    if (session('info')) {
        $globalNotifications[] = [
            'title' => 'Info',
            'message' => session('info'),
            'type' => 'info',
            'delay' => 5000,
        ];
    }

    // Backwards-compatible flash support.
    // Some controllers still use `with('status', ...)` instead of `success/error/warning`.
    if (session('status')) {
        $globalNotifications[] = [
            'title' => 'Notice',
            'message' => session('status'),
            'type' => 'info',
            'delay' => 5000,
        ];
    }

    if (isset($errors) && $errors->any()) {
        $globalNotifications = array_merge(
            $globalNotifications,
            array_map(static function ($validationError) {
                return [
                    'title' => 'Validation Error',
                    'message' => $validationError,
                    'type' => 'danger',
                    'delay' => 6000,
                ];
            }, $errors->all())
        );
    }
@endphp

<script id="global-notifications-data" type="application/json">{!! json_encode($globalNotifications) !!}</script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const notificationsElement = document.getElementById('global-notifications-data');
        const notifications = notificationsElement ? JSON.parse(notificationsElement.textContent || '[]') : [];

        if (window.notifier && Array.isArray(notifications) && notifications.length > 0) {
            notifications.forEach(function (notification) {
                notifier.show(
                    notification.title || 'Notice',
                    notification.message || '',
                    notification.type || '',
                    '',
                    Number(notification.delay) || 4500
                );
            });
        }

        document.body.addEventListener('click', function (event) {
            if (!event.target.closest('.permission-alert')) return;
            if (!window.notifier) return;

            notifier.show(
                'Permission denied',
                'You do not have permission to perform this action.',
                'danger',
                '',
                4500
            );
        });
    });
</script>
