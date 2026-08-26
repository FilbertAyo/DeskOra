<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoReadOnly
{
    /**
     * Mutating route names a demo viewer may still call.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTE_NAMES = [
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isDemoViewer()) {
            return $next($request);
        }

        if ($request->isMethodSafe() || $this->isAllowedMutation($request)) {
            return $next($request);
        }

        $message = (string) config('demo.message');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
            ], 403);
        }

        return back()
            ->with('warning', $message);
    }

    private function isAllowedMutation(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if (is_string($routeName) && in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return true;
        }

        return $request->is('logout');
    }
}
