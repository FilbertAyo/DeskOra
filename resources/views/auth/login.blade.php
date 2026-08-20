<x-guest-layout>
    <div class="card auth-card">
        <div class="card-body">
            <h1 class="auth-form-heading">Welcome back</h1>
            <p class="auth-form-subheading">Sign in to your {{ config('app.name', 'Adilisha Portal') }} workspace</p>

            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="form-group mb-3">
                    <label class="form-label" for="email">Work email</label>
                    <input
                        id="email"
                        class="form-control @error('email') is-invalid @enderror"
                        type="email"
                        name="email"
                        placeholder="you@company.com"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                    >
                    @error('email')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group mb-4">
                    <label class="form-label" for="password">Password</label>
                    <div class="auth-password-field">
                        <input
                            id="password"
                            class="form-control @error('password') is-invalid @enderror"
                            type="password"
                            name="password"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        >
                        <button
                            type="button"
                            class="auth-password-toggle"
                            data-password-toggle
                            aria-label="Show password"
                        >
                            <i class="ti ti-eye" data-password-icon></i>
                        </button>
                    </div>
                    @error('password')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <x-primary-button label="Sign in" class="w-100" />
            </form>

            <div class="text-center mt-3 small text-muted">
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-secondary text-decoration-none">
                        Forgot Password?
                    </a>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.querySelector('[data-password-toggle]');
            const input = document.getElementById('password');
            const icon = document.querySelector('[data-password-icon]');

            if (!toggle || !input || !icon) {
                return;
            }

            toggle.addEventListener('click', function () {
                const hidden = input.type === 'password';
                input.type = hidden ? 'text' : 'password';
                icon.classList.toggle('ti-eye', !hidden);
                icon.classList.toggle('ti-eye-off', hidden);
                toggle.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
            });
        });
    </script>
</x-guest-layout>
