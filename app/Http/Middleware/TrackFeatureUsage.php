<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\FeatureUsage\FeatureUsageRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackFeatureUsage
{
    public function __construct(
        protected FeatureUsageRecorder $recorder,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->recorder->shouldTrackPageView($request)) {
            return $response;
        }

        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return $response;
        }

        $userId = (string) $user->id;
        $routeName = $request->route()?->getName();
        $path = '/'.ltrim($request->path(), '/');

        dispatch(function () use ($userId, $routeName, $path) {
            $user = User::query()->find($userId);
            if ($user === null || ! filled($routeName)) {
                return;
            }

            $recorder = app(FeatureUsageRecorder::class);
            $mapping = $recorder->resolveRouteMapping($routeName);
            if ($mapping === null) {
                return;
            }

            $recorder->record(
                user: $user,
                featureKey: $mapping['feature_key'],
                eventType: \App\Models\FeatureUsageEvent::EVENT_PAGE_VIEW,
                module: $mapping['module'],
                routeName: $routeName,
                path: \Illuminate\Support\Str::limit($path, 500, ''),
                properties: null,
                dedupe: true,
            );
        })->afterResponse();

        return $response;
    }
}
