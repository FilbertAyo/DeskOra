<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InventoryReturnUrl
{
    public static function sanitize(?string $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return self::inventoryPath($url);
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        $allowedHosts = array_unique(array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            request()->getHost(),
        ]));

        if (! in_array(strtolower($parts['host']), array_map('strtolower', $allowedHosts), true)) {
            return null;
        }

        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return self::inventoryPath($path);
    }

    public static function redirect(Request $request, string $fallback): RedirectResponse
    {
        $url = self::sanitize($request->input('return_url'))
            ?: self::sanitize(url()->previous());

        return redirect($url ?: $fallback);
    }

    private static function inventoryPath(string $path): ?string
    {
        $normalized = '/'.ltrim($path, '/');

        if (! str_starts_with($normalized, '/inventory')) {
            return null;
        }

        if (str_contains($normalized, "\n") || str_contains($normalized, "\r")) {
            return null;
        }

        return $normalized;
    }
}
