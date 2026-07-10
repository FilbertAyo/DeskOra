<x-guest-layout>
    <div class="card auth-card">
        <div class="card-body">
            <div class="mb-4">
                <h3 class="mb-1"><b>Verify Sign In</b></h3>
                <p class="text-muted mb-0">
                    Enter the 4-digit code sent to {{ $maskedEmail }}.
                </p>
            </div>

            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('login.verify-otp.store') }}" id="otp-verify-form">
                @csrf

                <div class="form-group mb-3">
                    <label class="form-label" for="otp-digit-0">Verification Code</label>

                    <input type="hidden" name="code" id="code" value="{{ old('code') }}">

                    <div
                        class="otp-digit-group d-flex justify-content-between gap-2 @error('code') is-invalid @enderror"
                        role="group"
                        aria-label="4-digit verification code">
                        @for ($i = 0; $i < 4; $i++)
                            <input
                                type="text"
                                id="otp-digit-{{ $i }}"
                                class="form-control otp-digit-input text-center @error('code') is-invalid @enderror"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="1"
                                autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                                aria-label="Digit {{ $i + 1 }}"
                                @if ($i === 0) autofocus @endif
                            >
                        @endfor
                    </div>

                    @error('code')
                        <small class="text-danger d-block mt-2">{{ $message }}</small>
                    @enderror
                </div>

                <x-primary-button label="Verify & Sign In" class="w-100" />
            </form>

            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 mt-4">
                <form method="POST" action="{{ route('login.verify-otp.resend') }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-link text-secondary text-decoration-none p-0 small border-0">
                        Resend code
                    </button>
                </form>

                <a href="{{ route('login.verify-otp.cancel') }}" class="text-secondary text-decoration-none small">
                    Back to login
                </a>
            </div>
        </div>
    </div>

    <style>
        .otp-digit-group {
            max-width: 18rem;
            margin-inline: auto;
        }

        .otp-digit-input {
            width: 3.25rem;
            height: 3.25rem;
            padding: 0;
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: 0;
            border-radius: 0.5rem;
        }

        .otp-digit-input:focus {
            border-color: var(--bs-primary, #0d6efd);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }

        @media (max-width: 400px) {
            .otp-digit-input {
                width: 2.75rem;
                height: 2.75rem;
                font-size: 1.25rem;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('otp-verify-form');
            const hiddenCode = document.getElementById('code');
            const inputs = Array.from(document.querySelectorAll('.otp-digit-input'));

            if (!form || !hiddenCode || inputs.length !== 4) {
                return;
            }

            function syncHiddenCode() {
                hiddenCode.value = inputs.map((input) => input.value.replace(/\D/g, '').slice(0, 1)).join('');
            }

            function fillFromValue(value, startIndex) {
                const digits = String(value).replace(/\D/g, '').slice(0, 4 - startIndex).split('');

                digits.forEach((digit, offset) => {
                    const index = startIndex + offset;
                    if (inputs[index]) {
                        inputs[index].value = digit;
                    }
                });

                syncHiddenCode();

                const nextEmpty = inputs.findIndex((input) => input.value === '');
                const focusIndex = nextEmpty === -1 ? inputs.length - 1 : nextEmpty;
                inputs[focusIndex].focus();
                inputs[focusIndex].select();
            }

            // Restore old() value into boxes after validation errors.
            if (hiddenCode.value) {
                fillFromValue(hiddenCode.value, 0);
            }

            inputs.forEach((input, index) => {
                input.addEventListener('input', function (event) {
                    const raw = event.target.value.replace(/\D/g, '');

                    if (raw.length > 1) {
                        fillFromValue(raw, index);
                        return;
                    }

                    event.target.value = raw.slice(0, 1);
                    syncHiddenCode();

                    if (raw && index < inputs.length - 1) {
                        inputs[index + 1].focus();
                        inputs[index + 1].select();
                    }
                });

                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Backspace' && !event.target.value && index > 0) {
                        event.preventDefault();
                        inputs[index - 1].focus();
                        inputs[index - 1].select();
                        return;
                    }

                    if (event.key === 'ArrowLeft' && index > 0) {
                        event.preventDefault();
                        inputs[index - 1].focus();
                        inputs[index - 1].select();
                        return;
                    }

                    if (event.key === 'ArrowRight' && index < inputs.length - 1) {
                        event.preventDefault();
                        inputs[index + 1].focus();
                        inputs[index + 1].select();
                    }
                });

                input.addEventListener('paste', function (event) {
                    event.preventDefault();
                    const pasted = (event.clipboardData || window.clipboardData).getData('text');
                    fillFromValue(pasted, index);
                });

                input.addEventListener('focus', function () {
                    this.select();
                });
            });

            form.addEventListener('submit', function (event) {
                syncHiddenCode();

                if (!/^\d{4}$/.test(hiddenCode.value)) {
                    event.preventDefault();
                    const firstEmpty = inputs.find((input) => !input.value) || inputs[0];
                    firstEmpty.focus();
                }
            });
        });
    </script>
</x-guest-layout>
