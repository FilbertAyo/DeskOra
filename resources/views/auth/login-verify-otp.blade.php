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

            <form method="POST" action="{{ route('login.verify-otp.store') }}">
                @csrf

                <div class="form-group mb-3">
                    <label class="form-label" for="code">Verification Code</label>
                    <input
                        id="code"
                        class="form-control text-center @error('code') is-invalid @enderror"
                        type="text"
                        name="code"
                        inputmode="numeric"
                        pattern="[0-9]{4}"
                        maxlength="4"
                        placeholder="0000"
                        required
                        autofocus
                        autocomplete="one-time-code"
                    >
                    @error('code')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <x-primary-button label="Verify & Sign In" class="w-100" />
            </form>

            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 mt-4">
                <form method="POST" action="{{ route('login.verify-otp.resend') }}">
                    @csrf
                    <button type="submit" class="text-secondary text-decoration-none small border-0 bg-transparent p-0">
                        Resend code
                    </button>
                </form>

                <a href="{{ route('login.verify-otp.cancel') }}" class="text-secondary text-decoration-none small">
                    Back to login
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>
