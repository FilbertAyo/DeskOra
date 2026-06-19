<x-guest-layout>
    <div class="card auth-card">
        <div class="card-body">
            <div class="mb-4">
                <h3 class="mb-1"><b>Login</b></h3>
            </div>

            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="form-group mb-3">
                    <label class="form-label" for="email">Email Address</label>
                    <input
                        id="email"
                        class="form-control @error('email') is-invalid @enderror"
                        type="email"
                        name="email"
                        placeholder="Email Address"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                    >
                    @error('email')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input
                        id="password"
                        class="form-control @error('password') is-invalid @enderror"
                        type="password"
                        name="password"
                        placeholder="Password"
                        required
                        autocomplete="current-password"
                    >
                    @error('password')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                @if (Route::has('password.request'))
                    <div class="text-end">
                        <a href="{{ route('password.request') }}" class="text-secondary text-decoration-none small">
                            Forgot Password?
                        </a>
                    </div>
                @endif

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary">
                        Login
                    </button>
                </div>
            </form>

            <div class="text-center mt-4 small text-muted">
                Facing issues?
                <a href="https://wa.me/255741400900" target="_blank" class="text-primary">
                    Contact Admin
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>
