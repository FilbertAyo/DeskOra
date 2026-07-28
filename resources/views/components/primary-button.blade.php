@props([
    'label' => 'Submit',
    'id' => null,
])

@php
    $defaultClasses = 'btn btn-primary lh-1';
@endphp

<button type="submit" {{ $attributes->merge(['class' => $defaultClasses] + ($id ? ['id' => $id] : [])) }}>
    <span class="spinner-border spinner-border-sm me-2 d-none" role="status"></span>
    <span class="btn-label">{{ $label }}</span>
</button>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.__primaryButtonSubmitGuard) {
                return;
            }
            window.__primaryButtonSubmitGuard = true;

            document.addEventListener('submit', function (event) {
                const form = event.target;
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                if (form.dataset.processing === '1') {
                    event.preventDefault();
                    return;
                }

                form.dataset.processing = '1';

                const submitter = event.submitter instanceof HTMLButtonElement
                    ? event.submitter
                    : form.querySelector('button[type="submit"]');

                // Disabled controls are omitted from form submission. Preserve the
                // clicked button's name/value (e.g. action=draft) via a hidden field.
                if (submitter && submitter.name) {
                    let proxy = form.querySelector('input[data-submitter-proxy="1"]');
                    if (! proxy) {
                        proxy = document.createElement('input');
                        proxy.type = 'hidden';
                        proxy.setAttribute('data-submitter-proxy', '1');
                        form.appendChild(proxy);
                    }
                    proxy.name = submitter.name;
                    proxy.value = submitter.value;
                }

                form.querySelectorAll('button[type="submit"]').forEach((button) => {
                    button.disabled = true;
                });

                if (! submitter) {
                    return;
                }

                const spinner = submitter.querySelector('.spinner-border');
                const label = submitter.querySelector('.btn-label');

                if (! spinner || ! label) {
                    return;
                }

                spinner.classList.remove('d-none');

                const originalText = label.textContent.trim().toLowerCase();
                const loadingText = {
                    'save': 'Saving...',
                    'submit': 'Submitting...',
                    'submit requisition': 'Submitting...',
                    'update requisition': 'Updating...',
                    'save as draft': 'Saving...',
                    'save changes': 'Saving Changes...',
                    'login': 'Logging in...',
                    'sign in': 'Signing in...',
                    'logout': 'Logging out...',
                    'log out': 'Logging out...',
                    'register': 'Registering...',
                    'update': 'Updating...',
                    'create': 'Creating...',
                    'delete': 'Deleting...',
                    'send': 'Sending...',
                    'send reset link': 'Sending...',
                    'reset password': 'Resetting...',
                    'verify & sign in': 'Verifying...',
                    'resend code': 'Sending...',
                    'resend verification email': 'Sending...',
                    'confirm': 'Confirming...',
                    'request': 'Requesting...',
                    'verify': 'Verifying...',
                    'activate': 'Activating...',
                    'assign': 'Assigning...',
                    'approve': 'Approving...',
                    'review': 'Reviewing...',
                    'authorize': 'Authorizing...',
                    'pay now': 'Paying Now...',
                    'reject': 'Rejecting...',
                };

                label.textContent = loadingText[originalText] || 'Processing...';
            }, true);
        });
    </script>
@endonce
