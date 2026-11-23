<?php

namespace App\Http\Responses;

use App\Support\DocumentUpdateMessages;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InertiaErrorResponse
{
    /** @var list<int> */
    public const STATUSES = [403, 404, 419, 500, 503];

    public static function render(Request $request, Response $response, Throwable $exception): Response
    {
        if ($response->isRedirection() || $request->is('api/*')) {
            return $response;
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return $response;
        }

        $status = $response->getStatusCode();

        if (! in_array($status, self::STATUSES, true)) {
            return $response;
        }

        return Inertia::render('Errors/Show', self::props($status, $exception))
            ->toResponse($request)
            ->setStatusCode($status);
    }

    /**
     * @return array{status: int, title: string, message: string}
     */
    public static function props(int $status, ?Throwable $exception = null): array
    {
        [$title, $message] = self::copy($status, $exception);

        return [
            'status' => $status,
            'title' => $title,
            'message' => $message,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function copy(int $status, ?Throwable $exception): array
    {
        $raw = trim((string) $exception?->getMessage());

        return match ($status) {
            403 => [
                DocumentUpdateMessages::isClosedPeriodMessage($raw)
                    ? 'Editing no longer available'
                    : 'Permission denied',
                ($raw !== '' && ! in_array($raw, ['This action is unauthorized.', 'Forbidden'], true))
                    ? $raw
                    : "It seems like you don't have access to perform this action.",
            ],
            404 => [
                'Page not found',
                "The page you are looking for doesn't exist or may have been moved.",
            ],
            419 => [
                'Page expired',
                'Your session has expired. Please refresh the page and try again.',
            ],
            503 => [
                'Under maintenance',
                'The system is currently down for maintenance. Please check back soon.',
            ],
            default => [
                'Something went wrong',
                'Something went wrong on our end. Please try again later.',
            ],
        };
    }
}
