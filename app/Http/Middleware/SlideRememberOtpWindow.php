<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Slide the OTP-skip window forward while a remembered account is in use.
 *
 * Writes at most twice a day. An expired window is left alone so four days
 * without activity requires an email code on the next sign-in.
 */
class SlideRememberOtpWindow
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->hasActiveRememberWindow()) {
            $days = max(1, (int) config('auth.login_remember_days', 4));
            $refreshAfter = now()->addDays($days)->subHours(12);

            if ($user->remember_expires_at->lt($refreshAfter)) {
                $user->forceFill([
                    'remember_expires_at' => now()->addDays($days),
                ])->save();
            }
        }

        return $next($request);
    }
}
