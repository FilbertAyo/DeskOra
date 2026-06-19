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

           
                <x-primary-button label="Login" class="w-100 mt-3" />
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
</x-guest-layout>
