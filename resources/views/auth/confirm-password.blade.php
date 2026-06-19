<x-guest-layout>
    <div class="card auth-card">
        <div class="card-body">
            <div class="mb-4">
                <h3 class="mb-1"><b>Confirm Password</b></h3>
                <p class="text-muted mb-0">
                    This is a secure area. Please confirm your password before continuing.
                </p>
            </div>

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
