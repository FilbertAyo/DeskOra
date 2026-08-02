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
use Illuminate\View\View;

class WeeklyWorkLogController extends Controller
{
    public function __construct(
        private readonly WeeklyWorkLogActionBridgeService $actionBridge,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', WeeklyWorkLog::class);

        /** @var User $user */
        $user = Auth::user();
        $currentPeriod = WorkPeriod::getCurrent();
        $periodId = $request->string('period_id')->toString() ?: ($currentPeriod?->id);
        $period = $periodId ? WorkPeriod::query()->find($periodId) : $currentPeriod;

        $canViewTeam = $user->can('approve documents');
        $viewUserId = (string) $user->id;
        if ($canViewTeam && $request->filled('user_id')) {
            $viewUserId = $request->string('user_id')->toString();
        }

        $logs = collect();
        $days = collect();
        if ($period) {
            $logs = WeeklyWorkLog::query()
                ->where('user_id', $viewUserId)
                ->where('period_id', $period->id)
                ->orderBy('log_date')
                ->get()
                ->keyBy(fn (WeeklyWorkLog $log) => $log->log_date->toDateString());

            $start = Carbon::parse($period->week_start_date)->startOfDay();
            $end = Carbon::parse($period->week_end_date)->startOfDay();
            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                $key = $day->toDateString();
                $days->push([
                    'date' => $day->copy(),
                    'key' => $key,
                    'log' => $logs->get($key),
                    'is_today' => $key === now()->toDateString(),
                ]);
            }
        }

        $periods = WorkPeriod::query()
            ->orderByDesc('year')
            ->orderByDesc('week_number')
            ->limit(40)
            ->get();

        $teamUsers = $canViewTeam
            ? User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])
            : collect();

        $todayLog = $currentPeriod
            ? WeeklyWorkLog::query()
                ->where('user_id', $user->id)
                ->where('period_id', $currentPeriod->id)
                ->whereDate('log_date', now()->toDateString())
                ->first()
            : null;

        return view('weekly-work-logs.index', compact(
            'period',
            'periods',
            'currentPeriod',
            'days',
            'todayLog',
            'canViewTeam',
            'teamUsers',
            'viewUserId',
        ));
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
            ->route('weekly-work-logs.index', ['period_id' => $period->id])
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
            ->route('weekly-work-logs.index', ['period_id' => $weeklyWorkLog->period_id])
            ->with('success', 'Work log updated.');
    }

    public function createAction(WeeklyWorkLog $weeklyWorkLog): RedirectResponse
    {
        $this->authorize('createActionFromBlocker', $weeklyWorkLog);

        try {
            $this->actionBridge->createActionFromBlocker($weeklyWorkLog, Auth::user());
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('weekly-work-logs.index', ['period_id' => $weeklyWorkLog->period_id])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('weekly-work-logs.index', ['period_id' => $weeklyWorkLog->period_id])
            ->with('success', 'Action item created from your blocker.');
    }
}
