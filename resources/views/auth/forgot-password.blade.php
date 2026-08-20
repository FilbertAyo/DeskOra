<x-guest-layout>
    <div class="card auth-card">
        <div class="card-body">
            <h1 class="auth-form-heading">Forgot Password</h1>
            <p class="auth-form-subheading">
                Enter your email address and we'll send you a link to reset your password.
            </p>

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
