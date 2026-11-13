<?php

namespace App\Http\Controllers;

use App\Models\FeatureUsageEvent;
use App\Models\User;
use App\Services\FeatureUsage\FeatureUsageAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeatureUsageController extends Controller
{
    public function index(Request $request, FeatureUsageAnalyticsService $analytics): Response
    {
        $this->authorize('viewAny', FeatureUsageEvent::class);

        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->subDays(29)->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $module = $request->input('module');
        $eventType = $request->input('event_type');
        $userId = $request->input('user_id');

        $summary = $analytics->summarize(
            from: $from,
            to: $to,
            module: filled($module) ? (string) $module : null,
            eventType: filled($eventType) ? (string) $eventType : null,
            userId: filled($userId) ? (string) $userId : null,
        );

        $staffUsers = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        $modules = $analytics->availableModules();

        return Inertia::render('Settings/FeatureUsage/Index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'activeUsers' => $summary['active_users'],
            'totalInteractions' => $summary['total_interactions'],
            'moduleRows' => $summary['modules'],
            'featureRows' => $summary['features'],
            'chart' => $summary['chart'],
            'staffUsers' => $staffUsers,
            'modules' => $modules,
            'selectedModule' => $module,
            'selectedEventType' => $eventType,
            'selectedUserId' => $userId,
        ]);
    }
}
