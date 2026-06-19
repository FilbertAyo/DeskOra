@props([
    'label' => 'Submit',
    'id' => null, // allow optional id
])

@php
    $defaultClasses = 'btn btn-primary lh-1';
@endphp

<button type="submit" {{ $attributes->merge(['class' => $defaultClasses] + ($id ? ['id' => $id] : [])) }}>
    <span class="spinner-border spinner-border-sm me-2 d-none" role="status"></span>
    <span class="btn-label">{{ $label }}</span>
</button>


<script>
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function (e) {
                const clickedButton = form.querySelector('button[type="submit"]:focus');

                if (clickedButton) {
                    const spinner = clickedButton.querySelector('.spinner-border');
                    const label = clickedButton.querySelector('.btn-label');

                    if (spinner && label) {
                        spinner.classList.remove('d-none');
                        clickedButton.setAttribute('disabled', 'true');

                        const originalText = label.textContent.trim().toLowerCase();

                        const loadingText = {
                            'save': 'Saving...',
                            'submit': 'Submitting...',
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
                            'pay now': 'Paying Now...',
                            'reject': 'Rejecting...',
                        };

                        label.textContent = loadingText[originalText] || 'Processing...';
                    }
                }
            });
        });
    });
</script>
