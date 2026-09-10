@php
    $globalNotifications = [];
    $profileSettingsUrl = \Illuminate\Support\Facades\Route::has('profile.edit')
        ? route('profile.edit')
        : null;

    if (session('success')) {
        $globalNotifications[] = [
            'title' => 'Success',
            'message' => session('success'),
            'type' => 'success',
        ];
    }

    if (session('error')) {
        $errorMessage = (string) session('error');
        $errorToast = [
            'title' => 'Error',
            'message' => $errorMessage,
            'type' => 'error',
        ];
        if ($profileSettingsUrl && str_contains($errorMessage, 'Profile Settings')) {
            $errorToast['actions'] = [
                [
                    'label' => 'Open profile',
                    'event' => 'gooey-navigate',
                    'icon' => 'external-link',
                    'data' => ['url' => $profileSettingsUrl],
                ],
            ];
        }
        $globalNotifications[] = $errorToast;
    }

    if (session('warning')) {
        $globalNotifications[] = [
            'title' => 'Warning',
            'message' => session('warning'),
            'type' => 'warning',
        ];
    }

    if (session('info')) {
        $globalNotifications[] = [
            'title' => 'Info',
            'message' => session('info'),
            'type' => 'info',
        ];
    }

    $status = session('status');
    if (is_string($status) && $status !== '') {
        $statusMessages = [
            'password-updated' => 'Your password has been updated.',
            'verification-link-sent' => 'A new verification link has been sent to your email.',
            'signature-updated' => 'Your signature has been updated.',
        ];
        if (isset($statusMessages[$status])) {
            $globalNotifications[] = [
                'title' => 'Notice',
                'message' => $statusMessages[$status],
                'type' => 'info',
            ];
        } elseif (str_contains($status, ' ')) {
            $globalNotifications[] = [
                'title' => 'Notice',
                'message' => $status,
                'type' => 'info',
            ];
        }
    }

    if (isset($errors) && $errors->any()) {
        $details = [];
        foreach ($errors->messages() as $field => $fieldMessages) {
            $label = ucfirst(str_replace(['_', '-'], ' ', (string) $field));
            foreach ($fieldMessages as $fieldMessage) {
                $details[] = [
                    'label' => $label,
                    'value' => $fieldMessage,
                ];
            }
        }

        $globalNotifications[] = [
            'title' => 'Please fix the highlighted fields',
            'message' => $errors->first(),
            'type' => 'error',
            'details' => count($details) > 1 ? $details : [],
            'duration' => 6000,
        ];
    }
@endphp

<script id="global-notifications-data" type="application/json">{!! json_encode($globalNotifications) !!}</script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const notificationsElement = document.getElementById('global-notifications-data');
        const notifications = notificationsElement ? JSON.parse(notificationsElement.textContent || '[]') : [];

        if (Array.isArray(notifications) && notifications.length > 0 && typeof window.appToast === 'function') {
            notifications.forEach(function (notification) {
                window.appToast(notification);
            });
        }

        document.body.addEventListener('click', function (event) {
            if (!event.target.closest('.permission-alert')) return;
            if (typeof window.appToast !== 'function') return;

            window.appToast({
                type: 'error',
                title: 'Permission denied',
                message: 'You do not have permission to perform this action.',
            });
        });
    });
</script>
