<x-guest-layout>
    <div class="card auth-card">
        <div class="card-body">
            <h1 class="auth-form-heading">Verify Email</h1>
            <p class="auth-form-subheading">
                Thanks for signing up! Before getting started, verify your email address by clicking the link we sent you. If you didn't receive it, we can send another.
            </p>

            @if (session('status') == 'verification-link-sent')
                <div class="alert alert-success small mb-4">
                    A new verification link has been sent to the email address you provided during registration.
                </div>
            @endif

            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between align-items-stretch align-items-sm-center">
                <form method="POST" action="{{ route('verification.send') }}" class="flex-grow-1">
                    @csrf
                    <x-primary-button :label="__('Resend Verification Email')" class="w-100" />
                </form>

                <form method="POST" action="{{ route('logout') }}" class="flex-grow-1">
                    @csrf
                    <x-primary-button :label="__('Log Out')" class="w-100" />
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
