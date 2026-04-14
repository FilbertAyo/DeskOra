<?php

namespace App\Providers;

use App\Data\WeeklySubmissionReminder;
use App\Models\Petty;
use App\Services\WeeklySubmissionReminderService;
use Illuminate\Support\Facades\Auth;
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
                $departmentIds = $user->departmentScopeIds();

                $pendingCount = Petty::whereIn('department_id', $departmentIds)
                    ->where('status', 'pending')
                    ->count();

                $approvedCount = Petty::whereIn('department_id', $departmentIds)
                    ->where('status', 'approved')
                    ->count();
            }

            // This one doesn't depend on user
            $processingCount = Petty::where('status', 'processing')->count();

            $view->with([
                'pendingPettiesCount' => $pendingCount,
                'processingPettiesCount' => $processingCount,
                'approvedPettiesCount' => $approvedCount,
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
