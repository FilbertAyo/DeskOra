<?php

namespace App\Services\FeatureUsage;

use App\Models\FeatureUsageEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FeatureUsageRecorder
{
    public function resolveRouteMapping(?string $routeName): ?array
    {
        if (! filled($routeName)) {
            return null;
        }

        $map = config('feature-usage.route_modules', []);

        foreach ($map as $pattern => $meta) {
            if ($this->routeMatches((string) $pattern, $routeName)) {
                return [
                    'module' => $meta['module'],
                    'feature_key' => $meta['feature_key'],
                ];
            }
        }

        $fallback = config('feature-usage.fallback', [
            'module' => 'Other',
            'feature_key' => 'module.other',
        ]);

        return [
            'module' => $fallback['module'],
            'feature_key' => $fallback['feature_key'],
        ];
    }

    public function shouldTrackPageView(Request $request): bool
    {
        if (! $request->user()) {
            return false;
        }

        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($request->ajax() || $request->expectsJson()) {
            return false;
        }

        $path = '/'.ltrim($request->path(), '/');
        foreach (config('feature-usage.excluded_path_prefixes', []) as $prefix) {
            if (Str::startsWith($path, $prefix)) {
                return false;
            }
        }

        $routeName = $request->route()?->getName();
        if (! filled($routeName)) {
            return false;
        }

        if (in_array($routeName, config('feature-usage.excluded_routes', []), true)) {
            return false;
        }

        foreach (config('feature-usage.excluded_route_prefixes', []) as $prefix) {
            if (Str::startsWith($routeName, $prefix)) {
                return false;
            }
        }

        return true;
    }

    public function recordPageView(User $user, Request $request): void
    {
        $routeName = $request->route()?->getName();
        $mapping = $this->resolveRouteMapping($routeName);
        if ($mapping === null) {
            return;
        }

        $this->record(
            user: $user,
            featureKey: $mapping['feature_key'],
            eventType: FeatureUsageEvent::EVENT_PAGE_VIEW,
            module: $mapping['module'],
            routeName: $routeName,
            path: Str::limit('/'.ltrim($request->path(), '/'), 500, ''),
            properties: null,
            dedupe: true,
        );
    }

    public function recordFeatureClick(User $user, string $featureKey, ?array $properties = null): bool
    {
        $features = config('feature-usage.features', []);
        if (! array_key_exists($featureKey, $features)) {
            return false;
        }

        $meta = $features[$featureKey];

        $this->record(
            user: $user,
            featureKey: $featureKey,
            eventType: FeatureUsageEvent::EVENT_FEATURE_CLICK,
            module: $meta['module'],
            routeName: null,
            path: null,
            properties: $properties,
            dedupe: false,
        );

        return true;
    }

    public function record(
        User $user,
        string $featureKey,
        string $eventType,
        string $module,
        ?string $routeName = null,
        ?string $path = null,
        ?array $properties = null,
        bool $dedupe = false,
    ): void {
        if ($dedupe && $this->isDuplicate($user->id, $featureKey)) {
            return;
        }

        FeatureUsageEvent::query()->create([
            'user_id' => $user->id,
            'feature_key' => $featureKey,
            'event_type' => $eventType,
            'route_name' => $routeName,
            'path' => $path,
            'module' => $module,
            'properties' => $properties,
            'created_at' => now(),
        ]);

        if ($dedupe) {
            $this->markDeduped($user->id, $featureKey);
        }
    }

    public function featureLabel(string $featureKey): string
    {
        $features = config('feature-usage.features', []);
        if (isset($features[$featureKey]['label'])) {
            return $features[$featureKey]['label'];
        }

        $modules = config('feature-usage.route_modules', []);
        foreach ($modules as $meta) {
            if (($meta['feature_key'] ?? null) === $featureKey) {
                return $meta['module'];
            }
        }

        return Str::of($featureKey)
            ->afterLast('.')
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    protected function routeMatches(string $pattern, string $routeName): bool
    {
        if ($pattern === $routeName) {
            return true;
        }

        if (str_ends_with($pattern, '*')) {
            $prefix = substr($pattern, 0, -1);

            return str_starts_with($routeName, $prefix);
        }

        return false;
    }

    protected function isDuplicate(string $userId, string $featureKey): bool
    {
        $seconds = (int) config('feature-usage.dedupe_seconds', 30);
        if ($seconds <= 0) {
            return false;
        }

        return Cache::has($this->dedupeCacheKey($userId, $featureKey));
    }

    protected function markDeduped(string $userId, string $featureKey): void
    {
        $seconds = (int) config('feature-usage.dedupe_seconds', 30);
        if ($seconds <= 0) {
            return;
        }

        Cache::put($this->dedupeCacheKey($userId, $featureKey), true, $seconds);
    }

    protected function dedupeCacheKey(string $userId, string $featureKey): string
    {
        return 'feature_usage_dedupe:'.$userId.':'.$featureKey;
    }
}
