<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WeeklyWorkLog;
use App\Models\WorkPeriod;
use App\Services\WeeklyWorkLogActionBridgeService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WeeklyWorkLogController extends Controller
{
    public function __construct(
        private readonly WeeklyWorkLogActionBridgeService $actionBridge,
    ) {}

    public function index(): RedirectResponse
    {
        $this->authorize('viewAny', WeeklyWorkLog::class);

        return redirect()->route('dashboard');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', WeeklyWorkLog::class);

        /** @var User $user */
        $user = Auth::user();
        $validated = $request->validate([
            'period_id' => ['required', 'string', 'exists:work_periods,id'],
            'log_date' => ['required', 'date'],
            'done' => ['required', 'string', 'max:4000'],
            'blocker' => ['nullable', 'string', 'max:4000'],
            'next' => ['nullable', 'string', 'max:4000'],
        ]);

        $period = WorkPeriod::query()->findOrFail($validated['period_id']);
        $logDate = Carbon::parse($validated['log_date'])->startOfDay();
        $weekStart = Carbon::parse($period->week_start_date)->startOfDay();
        $weekEnd = Carbon::parse($period->week_end_date)->startOfDay();

        if ($logDate->lt($weekStart) || $logDate->gt($weekEnd)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Log date must fall within the selected work period.');
        }

        $log = WeeklyWorkLog::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'period_id' => $period->id,
                'log_date' => $logDate->toDateString(),
            ],
            [
                'done' => trim($validated['done']),
                'blocker' => filled($validated['blocker'] ?? null) ? trim((string) $validated['blocker']) : null,
                'next' => filled($validated['next'] ?? null) ? trim((string) $validated['next']) : null,
            ]
        );

        $this->authorize('update', $log);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'id' => $log->id,
                'message' => 'Work log saved for '.$logDate->format('D, M j').'.',
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'Work log saved for '.$logDate->format('D, M j').'.');
    }

    public function update(Request $request, WeeklyWorkLog $weeklyWorkLog): RedirectResponse
    {
        $this->authorize('update', $weeklyWorkLog);

        $validated = $request->validate([
            'done' => ['required', 'string', 'max:4000'],
            'blocker' => ['nullable', 'string', 'max:4000'],
            'next' => ['nullable', 'string', 'max:4000'],
        ]);

        $weeklyWorkLog->update([
            'done' => trim($validated['done']),
            'blocker' => filled($validated['blocker'] ?? null) ? trim((string) $validated['blocker']) : null,
            'next' => filled($validated['next'] ?? null) ? trim((string) $validated['next']) : null,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Work log updated.');
    }

    public function createAction(WeeklyWorkLog $weeklyWorkLog): RedirectResponse
    {
        $this->authorize('createActionFromBlocker', $weeklyWorkLog);

        try {
            $this->actionBridge->createActionFromBlocker($weeklyWorkLog, Auth::user());
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('dashboard')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'Action item created from your blocker.');
    }
}
