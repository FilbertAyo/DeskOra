<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class GreetingService
{
    public const SLOT_MORNING = 'morning';

    public const SLOT_AFTERNOON = 'afternoon';

    public const SLOT_EVENING = 'evening';

    public const SLOT_NIGHT = 'night';

    /**
     * @return array{
     *     salutation: string,
     *     firstName: string,
     *     message: string,
     *     full: string,
     *     slot: string
     * }
     */
    public function forUser(User $user, ?Carbon $now = null): array
    {
        $now = ($now ?? Carbon::now())->timezone(config('app.timezone'));
        $slot = $this->resolveSlot($now);
        $cacheKey = sprintf(
            'dashboard-greeting:%s:%s:%s',
            $user->id,
            $now->format('Y-m-d'),
            $slot
        );

        return Cache::remember($cacheKey, $this->cacheTtlForSlot($now, $slot), function () use ($user, $slot) {
            $firstName = $this->firstNameFrom($user->name);
            $salutation = $this->salutationForSlot($slot);
            $message = $this->randomMessageForSlot($slot);

            return [
                'salutation' => $salutation,
                'firstName' => $firstName,
                'message' => $message,
                'full' => sprintf('%s, %s! %s', $salutation, $firstName, $message),
                'slot' => $slot,
            ];
        });
    }

    public function resolveSlot(Carbon $now): string
    {
        $hour = (int) $now->format('G');

        if ($hour >= 5 && $hour <= 11) {
            return self::SLOT_MORNING;
        }

        if ($hour >= 12 && $hour <= 16) {
            return self::SLOT_AFTERNOON;
        }

        if ($hour >= 17 && $hour <= 21) {
            return self::SLOT_EVENING;
        }

        return self::SLOT_NIGHT;
    }

    public function salutationForSlot(string $slot): string
    {
        return match ($slot) {
            self::SLOT_MORNING => 'Good Morning',
            self::SLOT_AFTERNOON => 'Good Afternoon',
            self::SLOT_EVENING => 'Good Evening',
            default => 'Hello',
        };
    }

    public function firstNameFrom(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'there';
        }

        return explode(' ', $name)[0];
    }

    private function randomMessageForSlot(string $slot): string
    {
        $messages = config("greetings.{$slot}", []);

        if (! is_array($messages) || $messages === []) {
            return 'Welcome back.';
        }

        return (string) Arr::random($messages);
    }

    private function cacheTtlForSlot(Carbon $now, string $slot): Carbon
    {
        $endOfSlot = match ($slot) {
            self::SLOT_MORNING => $now->copy()->setTime(11, 59, 59),
            self::SLOT_AFTERNOON => $now->copy()->setTime(16, 59, 59),
            self::SLOT_EVENING => $now->copy()->setTime(21, 59, 59),
            default => $now->hour >= 22
                ? $now->copy()->endOfDay()
                : $now->copy()->setTime(4, 59, 59),
        };

        if ($endOfSlot->lessThanOrEqualTo($now)) {
            $endOfSlot = $now->copy()->endOfDay();
        }

        return $endOfSlot;
    }
}
