<?php

namespace App\Http\Controllers;

use App\Exceptions\StaffAppreciationException;
use App\Models\User;
use App\Services\StaffAppreciationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffAppreciationNominationController extends Controller
{
    /**
     * Session key used to snooze ("Remind Me Later") the prompt for the rest of
     * the current browser session. Stores the period id it was snoozed for.
     */
    public const SNOOZE_SESSION_KEY = 'appreciation_snoozed_period';

    public function __construct(private readonly StaffAppreciationService $service) {}

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
            $this->service->recordNomination($actor, $nominee, $validated['reason'] ?? null);
        } catch (StaffAppreciationException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $request->session()->forget(self::SNOOZE_SESSION_KEY);

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
            $this->service->skip($actor);
        } catch (StaffAppreciationException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $request->session()->forget(self::SNOOZE_SESSION_KEY);

        return response()->json([
            'ok' => true,
            'message' => 'No problem. You will not be prompted again this month.',
        ]);
    }

    public function remindLater(Request $request): JsonResponse
    {
        $period = $this->service->currentOpenPeriod();

        if ($period) {
            $request->session()->put(self::SNOOZE_SESSION_KEY, $period->id);
        }

        return response()->json(['ok' => true]);
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
