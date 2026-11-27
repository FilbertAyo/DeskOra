<?php

namespace App\Http\Controllers;

use App\Exceptions\StaffAppreciationException;
use App\Models\NominationPeriod;
use App\Models\User;
use App\Services\StaffAppreciationService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class StaffAppreciationNominationController extends Controller
{
    /**
     * Session key used to snooze ("Remind Me Later") the prompt. Stores the
     * period id it was snoozed for.
     */
    public const SNOOZE_SESSION_KEY = 'appreciation_snoozed_period';

    /**
     * How long "Remind Me Later" hides the prompt (cache + client localStorage).
     * Long enough that a normal page refresh / same-day navigation will not
     * re-prompt, while still allowing a later reminder before the window closes.
     */
    public const SNOOZE_HOURS = 12;

    public function __construct(private readonly StaffAppreciationService $service) {}

    public static function snoozeCacheKey(string|int $userId, string|int $periodId): string
    {
        return 'appreciation_snooze:'.(string) $userId.':'.(string) $periodId;
    }

    /**
     * Whether the nomination prompt is currently snoozed for this user/period.
     */
    public static function isSnoozed(Session $session, User $user, NominationPeriod $period): bool
    {
        $periodId = (string) $period->id;

        if ((string) $session->get(self::SNOOZE_SESSION_KEY, '') === $periodId) {
            return true;
        }

        return Cache::has(self::snoozeCacheKey($user->id, $periodId));
    }

    /**
     * Persist a snooze that survives refresh (session + cache backup).
     */
    public static function snoozeFor(Session $session, User $user, NominationPeriod $period): void
    {
        $periodId = (string) $period->id;
        $session->put(self::SNOOZE_SESSION_KEY, $periodId);
        Cache::put(
            self::snoozeCacheKey($user->id, $periodId),
            true,
            now()->addHours(self::SNOOZE_HOURS)
        );
    }

    public static function clearSnooze(Session $session, ?User $user = null, ?NominationPeriod $period = null): void
    {
        $session->forget(self::SNOOZE_SESSION_KEY);

        if ($user && $period) {
            Cache::forget(self::snoozeCacheKey($user->id, $period->id));
        }
    }
    public function store(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'nominee_id' => [
                'required',
                'string',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('status', 'active')),
                Rule::notIn([$actor->id]),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'nominee_id.not_in' => 'You cannot nominate yourself.',
            'nominee_id.exists' => 'Please select an active colleague to nominate.',
            'nominee_id.required' => 'Please select a colleague to nominate.',
        ]);

        $nominee = User::query()->where('status', 'active')->findOrFail($validated['nominee_id']);

        try {
            $period = $this->service->recordNomination($actor, $nominee, $validated['reason'] ?? null);
        } catch (StaffAppreciationException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        self::clearSnooze($request->session(), $actor, $period);

        return response()->json([
            'ok' => true,
            'message' => 'Thank you! Your anonymous nomination has been recorded.',
        ]);
    }

    public function skip(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $period = $this->service->skip($actor);
        } catch (StaffAppreciationException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        self::clearSnooze($request->session(), $actor, $period);

        return response()->json([
            'ok' => true,
            'message' => 'No problem. You will not be prompted again this month.',
        ]);
    }

    public function remindLater(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $period = $this->service->currentOpenPeriod();

        if ($period && $actor) {
            self::snoozeFor($request->session(), $actor, $period);
        }

        return response()->json(['ok' => true, 'snooze_hours' => self::SNOOZE_HOURS]);
    }

    public function staffSearch(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $term = trim((string) $request->query('q', ''));

        $users = User::query()
            ->with('department:id,name')
            ->where('status', 'active')
            ->whereKeyNot($actor->id)
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('title', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->limit(15)
            ->get();

        $results = $users->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'title' => $user->title,
            'department' => $user->department?->name,
            'avatar_url' => $user->file ? asset($user->file) : asset('assets/images/profile.png'),
        ]);

        return response()->json(['data' => $results]);
    }
}
