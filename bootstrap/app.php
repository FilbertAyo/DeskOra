<?php

use App\Http\Middleware\EnsureAnonymousFeedbackToken;
use App\Http\Middleware\PreventAuthResponseCache;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\BackfillDocumentDomainTables::class,
        \App\Console\Commands\VerifyDocumentDomainParity::class,
        \App\Console\Commands\MigrateDocumentIdsToShortIds::class,
        \App\Console\Commands\MigrateShortIds::class,
        \App\Console\Commands\ClearStaleSessionsAfterShortIdMigration::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Required when the app sits behind HTTPS reverse proxies (nginx, Cloudflare, Render, etc.).
        $middleware->trustProxies(at: '*');

        $middleware->alias([ // Use 'alias' for route middleware
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'feedback.token' => EnsureAnonymousFeedbackToken::class,
            'prevent.auth.cache' => PreventAuthResponseCache::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session expired. Please refresh the page and try again.',
                ], 419);
            }

            $message = 'Your session expired. Please refresh the page and try again.';

            if ($request->is('login', 'logout') || $request->routeIs('login', 'logout', 'password.*', 'register')) {
                return redirect()
                    ->route('login')
                    ->with('warning', $message);
            }

            return redirect()
                ->back()
                ->withInput($request->except('password', 'password_confirmation', '_token'))
                ->with('warning', $message);
        });
    })->create();
