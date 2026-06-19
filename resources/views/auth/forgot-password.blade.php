<x-guest-layout>
    <div class="card auth-card">
        <div class="card-body">
            <div class="mb-4">
                <h3 class="mb-1"><b>Forgot Password</b></h3>
                <p class="text-muted mb-0">
                    Enter your email address and we'll send you a link to reset your password.
                </p>
            </div>

            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div class="mb-3">
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        class="form-control @error('email') is-invalid @enderror"
                        placeholder="Enter your email"
                    >
                    @error('email')
                        <div class="text-danger mt-2 small">{{ $message }}</div>
                    @enderror
                </div>

                <x-primary-button label="Send" class="w-100" />

                <div class="text-center mt-3">
                    <a href="{{ route('login') }}" class="text-secondary text-decoration-none small">
                        Back to Login
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
