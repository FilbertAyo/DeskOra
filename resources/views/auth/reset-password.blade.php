<x-guest-layout>
    <div class="card auth-card">
        <div class="card-body">
            <div class="mb-4">
                <h3 class="mb-1"><b>Reset Your Password</b></h3>
                <p class="text-muted mb-0">
                    Enter your email, a new password, and confirm it to complete the reset.
                </p>
            </div>

            <form method="POST" action="{{ route('password.store') }}">
                @csrf

                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <div class="mb-3">
                    <label for="email" class="form-label">{{ __('Email') }}</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email', $request->email) }}"
                        required
                        autofocus
                        autocomplete="username"
                        class="form-control @error('email') is-invalid @enderror"
                        placeholder="Enter your email"
                    >
                    @error('email')
                        <div class="text-danger mt-2 small">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('Password') }}</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        class="form-control @error('password') is-invalid @enderror"
                        placeholder="Enter a new password"
                    >
                    @error('password')
                        <div class="text-danger mt-2 small">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="password_confirmation" class="form-label">{{ __('Confirm Password') }}</label>
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        class="form-control @error('password_confirmation') is-invalid @enderror"
                        placeholder="Confirm your new password"
                    >
                    @error('password_confirmation')
                        <div class="text-danger mt-2 small">{{ $message }}</div>
                    @enderror
                </div>

                <x-primary-button label="Reset Password" class="w-100" />
            </form>
        </div>
    </div>
</x-guest-layout>
