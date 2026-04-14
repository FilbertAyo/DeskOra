<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAnonymousFeedbackToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('anonymous_feedback.public_token', '');
        $given = (string) $request->route('token', '');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            abort(404);
        }

        return $next($request);
    }
}
