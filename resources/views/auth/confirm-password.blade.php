<x-guest-layout>
    <div class="card auth-card">
        <div class="card-body">
            <h1 class="auth-form-heading">Confirm Password</h1>
            <p class="auth-form-subheading">
                This is a secure area. Please confirm your password before continuing.
            </p>

            <form method="POST" action="{{ route('password.confirm') }}">
                @csrf

                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('Password') }}</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="form-control @error('password') is-invalid @enderror"
                        placeholder="Enter your password"
                    >
                    @error('password')
                        <div class="text-danger mt-2 small">{{ $message }}</div>
                    @enderror
                </div>

                <x-primary-button :label="__('Confirm')" class="w-100" />
            </form>
        </div>
    </div>
</x-guest-layout>
