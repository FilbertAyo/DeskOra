<?php

namespace App\Providers;

use App\Data\WeeklySubmissionReminder;
use App\Models\Document;
use App\Models\Petty;
use App\Models\WorkPeriod;
use App\Services\WeeklySubmissionReminderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {
            $pendingCount = 0;
            $approvedCount = 0;
            $processingCount = 0;

            if (Auth::check()) {
                /** @var \App\Models\User $user */
                $user = Auth::user();
                $counts = Cache::remember('petty-nav-counts-user-'.$user->id, now()->addSeconds(45), function () use ($user) {
                    return Petty::query()
                        ->whereIn('department_id', $user->departmentScopeIds())
                        ->whereIn('status', ['pending', 'approved'])
                        ->selectRaw('status, COUNT(*) as total')
                        ->groupBy('status')
                        ->pluck('total', 'status');
                });

                $pendingCount = (int) ($counts['pending'] ?? 0);
                $approvedCount = (int) ($counts['approved'] ?? 0);
            }

            // This one doesn't depend on user.
            $processingCount = Cache::remember('petty-nav-count-processing', now()->addSeconds(45), function () {
                return Petty::where('status', 'processing')->count();
            });

            $view->with([
                'pendingPettiesCount' => $pendingCount,
                'processingPettiesCount' => $processingCount,
                'approvedPettiesCount' => $approvedCount,
            ]);
        });

        View::composer('layouts.navigation', function ($view) {
            $currentPeriod = Cache::remember('current-work-period-nav', now()->addSeconds(60), function () {
                return WorkPeriod::getCurrent();
            });

            $matrixRows = collect();
            if ($currentPeriod && Auth::check()) {
                /** @var \App\Models\User $user */
                $user = Auth::user();
                $departmentScopeIds = $user->departmentScopeIds();

                $matrixRows = Document::query()
                    ->where('type', Document::TYPE_WEEKLY_PLAN)
                    ->where('state', Document::STATE_SUBMITTED)
                    ->where('period_id', $currentPeriod->id)
                    ->whereNotNull('main_target')
                    ->where('main_target', '!=', '')
                    ->whereHas('user', function ($userQuery) use ($departmentScopeIds) {
                        $userQuery->whereIn('department_id', $departmentScopeIds);
                    })
                    ->with(['user:id,name,department_id', 'user.department:id,name'])
                    ->latest('updated_at')
                    ->get(['user_id', 'main_target', 'content', 'updated_at'])
                    ->groupBy('user_id')
                    ->map(fn ($plans) => $plans->first())
                    ->values()
                    ->sortBy(fn ($plan) => strtolower($plan->user?->name ?? ''))
                    ->values();
            }

            $view->with([
                'currentPeriod' => $currentPeriod,
                'matrixRows' => $matrixRows,
            ]);
        });

        // Slot views do not inherit layout composer data; omit layouts.app (reminder toast is dashboard-only).
        $weeklyReminderViews = [
            'dashboard',
            'documents.employee-dashboard',
        ];

        View::composer($weeklyReminderViews, function ($view) {
            if (! Auth::check()) {
                $view->with('weeklySubmissionReminder', new WeeklySubmissionReminder(false, null, null, []));

                return;
            }

            /** @var \App\Models\User $user */
            $user = Auth::user();
            if (($user->status ?? '') !== 'active') {
                $view->with('weeklySubmissionReminder', new WeeklySubmissionReminder(false, null, null, []));

                return;
            }

            $view->with(
                'weeklySubmissionReminder',
                app(WeeklySubmissionReminderService::class)->forUser($user)
            );
        });
    }
}
