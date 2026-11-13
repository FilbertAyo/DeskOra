<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Otp\LoginInactivityOtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use SadiqSalau\LaravelOtp\Facades\Otp;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->resolvedUser();

        if ($user->requiresLoginOtp()) {
            return $this->sendLoginOtp($request, $user);
        }

        return $this->completeLogin($request, $user, $request->boolean('remember'));
    }

    public function showVerifyOtp(Request $request): RedirectResponse|Response
    {
        if (! $this->hasPendingLogin($request)) {
            return redirect()->route('login');
        }

        $user = User::query()->find($request->session()->get('pending_login_user_id'));

        if (! $user) {
            $this->clearPendingLogin($request);

            return redirect()->route('login');
        }

        return Inertia::render('Auth/VerifyOtp', [
            'maskedEmail' => $user->maskedEmail(),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        if (! $this->hasPendingLogin($request)) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['required', 'string', 'digits:4'],
        ]);

        $this->ensureOtpNotRateLimited($request);

        $result = Otp::identifier($this->loginOtpIdentifier($request))
            ->attempt($request->string('code')->toString());

        if ($result['status'] !== Otp::OTP_PROCESSED) {
            RateLimiter::hit($this->otpThrottleKey($request));

            return back()->withErrors([
                'code' => __($result['status']),
            ]);
        }

        RateLimiter::clear($this->otpThrottleKey($request));

        $user = User::query()->find($request->session()->get('pending_login_user_id'));

        if (! $user) {
            $this->clearPendingLogin($request);

            return redirect()->route('login')->withErrors([
                'email' => 'Your sign-in session expired. Please try again.',
            ]);
        }

        $remember = (bool) $request->session()->get('pending_login_remember', false);

        return $this->completeLogin($request, $user, $remember);
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        if (! $this->hasPendingLogin($request)) {
            return redirect()->route('login');
        }

        $user = User::query()->find($request->session()->get('pending_login_user_id'));

        if (! $user) {
            $this->clearPendingLogin($request);

            return redirect()->route('login');
        }

        $result = Otp::identifier($this->loginOtpIdentifier($request))->update();

        if ($result['status'] !== Otp::OTP_SENT) {
            return back()->withErrors([
                'code' => __($result['status']),
            ]);
        }

        return back()->with('status', 'A new verification code has been sent.');
    }

    public function cancelVerifyOtp(Request $request): RedirectResponse
    {
        if ($this->hasPendingLogin($request)) {
            Otp::identifier($this->loginOtpIdentifier($request))->clear();
        }

        $this->clearPendingLogin($request);

        return redirect()->route('login');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function sendLoginOtp(Request $request, User $user): RedirectResponse
    {
        $identifier = 'login:'.$user->email;

        Otp::identifier($identifier)->send(
            new LoginInactivityOtp($user),
            Notification::route('mail', $user->email)
        );

        $request->session()->put([
            'pending_login_user_id' => $user->id,
            'pending_login_identifier' => $identifier,
            'pending_login_at' => now()->toISOString(),
            'pending_login_remember' => $request->boolean('remember'),
        ]);

        return redirect()
            ->route('login.verify-otp')
            ->with('status', 'We sent a 4-digit verification code to your email.');
    }

    private function completeLogin(Request $request, User $user, bool $remember = false): RedirectResponse
    {
        $this->clearPendingLogin($request);

        Auth::login($user, $remember);

        $user->update(['last_login_at' => now()]);

        activity('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->event('login')
            ->withProperties([
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
            ])
            ->log('User logged in');

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function hasPendingLogin(Request $request): bool
    {
        return $request->session()->has('pending_login_user_id')
            && $request->session()->has('pending_login_identifier');
    }

    private function clearPendingLogin(Request $request): void
    {
        $request->session()->forget([
            'pending_login_user_id',
            'pending_login_identifier',
            'pending_login_at',
            'pending_login_remember',
        ]);
    }

    private function loginOtpIdentifier(Request $request): string
    {
        return (string) $request->session()->get('pending_login_identifier');
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    private function ensureOtpNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->otpThrottleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->otpThrottleKey($request));

        throw ValidationException::withMessages([
            'code' => 'Too many verification attempts. Try again in '.ceil($seconds / 60).' minute(s).',
        ]);
    }

    private function otpThrottleKey(Request $request): string
    {
        return 'login-otp|'.$request->session()->get('pending_login_user_id').'|'.$request->ip();
    }
}
