<x-guest-layout>
    <div class="card auth-card">
        <div class="card-body">
            <div class="mb-4">
                <h3 class="mb-1"><b>Verify Email</b></h3>
                <p class="text-muted mb-0">
                    Thanks for signing up! Before getting started, verify your email address by clicking the link we sent you. If you didn't receive it, we can send another.
                </p>
            </div>

            @if (session('status') == 'verification-link-sent')
                <div class="alert alert-success small mb-4">
                    A new verification link has been sent to the email address you provided during registration.
                </div>
            @endif

            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between align-items-stretch align-items-sm-center">
                <form method="POST" action="{{ route('verification.send') }}" class="flex-grow-1">
                    @csrf
                    <button type="submit" class="btn btn-primary w-100">
                        {{ __('Resend Verification Email') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-link text-secondary text-decoration-none w-100">
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
