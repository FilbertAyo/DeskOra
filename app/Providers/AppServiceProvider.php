<?php

namespace App\Providers;

use A89s\GooeyToast\Facades\GooeyToast;
use App\Data\WeeklySubmissionReminder;
use App\Database\DocumentsUnionView;
use App\Models\AnonymousFeedback;
use App\Models\Request as FinanceRequest;
use App\Models\WorkPeriod;
use App\Services\RequestApprovalNavService;
use App\Services\WeeklySubmissionReminderService;
use App\Support\ShortId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
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
        Validator::extend('short_id', static function (string $attribute, mixed $value): bool {
            return is_string($value) && ShortId::is($value);
        });

        Paginator::useBootstrapFive();

        $this->registerGooeyToastMacros();
        $this->ensureDocumentsUnionView();
        $this->configureSessionAndUrl();

        View::composer('layouts.navigation', function ($view) {
            $currentPeriod = Cache::remember('current-work-period-nav', now()->addSeconds(60), function () {
                return WorkPeriod::getCurrent();
            });

            $view->with([
                'currentPeriod' => $currentPeriod,
            ]);
        });

        // Nav badges + nomination modal — only on the authenticated app shell
        // (aside + FAB are @included here). Avoid View::composer('*').
        View::composer('layouts.app', function ($view) {
            $approvedCount = 0;
            $processingCount = 0;
            $approvalsReviewCount = 0;
            $approvalsApproveCount = 0;
            $approvalsAuthorizeCount = 0;
            $approvalsTotalCount = 0;
            $newAnonymousFeedbackCount = 0;
            $showStaffNominationModal = false;
            $staffNominationPeriod = null;
            $approvalGateSnapshot = \App\Data\PendingApprovalGateSnapshot::idle();
            $showApprovalGateModal = false;

            if (Auth::check() && Schema::hasTable('requests')) {
                /** @var \App\Models\User $user */
                $user = Auth::user();
                $approvalCounts = app(RequestApprovalNavService::class)->countsFor($user);

                $approvalsReviewCount = (int) ($approvalCounts['review'] ?? 0);
                $approvalsApproveCount = (int) ($approvalCounts['approve'] ?? 0);
                $approvalsAuthorizeCount = (int) ($approvalCounts['authorize'] ?? 0);
                $approvalsTotalCount = (int) ($approvalCounts['total'] ?? 0);

                $scopeKey = md5(implode(',', $user->departmentScopeIds()));
                $approvedCount = (int) Cache::remember(
                    'petty-nav-count-approved-'.$user->id.'-'.$scopeKey,
                    now()->addSeconds(45),
                    fn () => FinanceRequest::query()
                        ->whereIn('department_id', $user->departmentScopeIds())
                        ->whereIn('request_type', ['Petty Cash', 'Reimbursement'])
                        ->where('status', 'approved')
                        ->count()
                );
            }

            if (Schema::hasTable('requests')) {
                $processingCount = Cache::remember('petty-nav-count-processing', now()->addSeconds(45), function () {
                    return FinanceRequest::where('status', 'processing')->count();
                });
            }

            if (
                Auth::check()
                && Auth::user()->can('manage anonymous feedback')
                && Schema::hasTable('anonymous_feedback')
            ) {
                $newAnonymousFeedbackCount = (int) Cache::remember(
                    'anonymous-feedback-new-count',
                    now()->addSeconds(45),
                    fn () => AnonymousFeedback::query()->new()->count()
                );
            }

            if (Auth::check() && Schema::hasTable('nomination_periods')) {
                /** @var \App\Models\User $user */
                $user = Auth::user();
                $service = app(\App\Services\StaffAppreciationService::class);
                $staffNominationPeriod = $service->currentOpenPeriod();

                if ($staffNominationPeriod
                    && ($user->status ?? '') === 'active'
                    && ! $user->isDemoViewer()
                    && ! $service->hasResponded($staffNominationPeriod, $user)) {
                    $snoozed = session(\App\Http\Controllers\StaffAppreciationNominationController::SNOOZE_SESSION_KEY);
                    $showStaffNominationModal = $snoozed !== $staffNominationPeriod->id;
                }
            }

            if (Auth::check() && request()->hasSession() && ($user = Auth::user()) && ($user->status ?? '') === 'active' && ! $user->isDemoViewer()) {
                $gate = app(\App\Services\PendingApprovalGate::class);
                if ($gate->userIsResponsible($user)) {
                    $onWorkRoute = $gate->isOnWorkRoute(request()->route()?->getName());
                    $approvalGateSnapshot = $gate->snapshotFor(
                        $user,
                        request()->session(),
                        ! $onWorkRoute
                    );
                    $showApprovalGateModal = $approvalGateSnapshot->showModal && ! $onWorkRoute;
                }
            }

            $view->with([
                'approvalsReviewCount' => $approvalsReviewCount,
                'approvalsApproveCount' => $approvalsApproveCount,
                'approvalsAuthorizeCount' => $approvalsAuthorizeCount,
                'approvalsTotalCount' => $approvalsTotalCount,
                'processingPettiesCount' => $processingCount,
                'approvedPettiesCount' => $approvedCount,
                'newAnonymousFeedbackCount' => $newAnonymousFeedbackCount,
                'showStaffNominationModal' => $showStaffNominationModal,
                'staffNominationPeriod' => $staffNominationPeriod,
                'approvalGateSnapshot' => $approvalGateSnapshot,
                'showApprovalGateModal' => $showApprovalGateModal,
            ]);
        });
        // Slot views do not inherit layout composer data; omit layouts.app (reminder toast is dashboard-only).
        $weeklyReminderViews = [
            'dashboard',
            'documents.employee-dashboard',
            'documents.weekly.plans',
            'documents.weekly.reports',
        ];

        View::composer($weeklyReminderViews, function ($view) {
            if (! Auth::check()) {
                $view->with('weeklySubmissionReminder', new WeeklySubmissionReminder(false, null, null, []));
                $view->with('weeklyPlanSlot', new \App\Data\WeeklySubmissionSlot(null, null));
                $view->with('weeklyReportSlot', new \App\Data\WeeklySubmissionSlot(null, null));
                $view->with('weeklyGracePlanSlot', new \App\Data\WeeklySubmissionSlot(null, null));
                $view->with('weeklyGraceReportSlot', new \App\Data\WeeklySubmissionSlot(null, null));
                $view->with('weeklyPlanGraceEntries', collect());
                $view->with('weeklyReportGraceEntries', collect());
                $view->with('weeklyPlanGracePeriod', null);
                $view->with('weeklyPlanGraceEndsAt', null);
                $view->with('weeklyReportGracePeriod', null);
                $view->with('weeklyReportGraceEndsAt', null);

                return;
            }

            /** @var \App\Models\User $user */
            $user = Auth::user();
            if (($user->status ?? '') !== 'active') {
                $view->with('weeklySubmissionReminder', new WeeklySubmissionReminder(false, null, null, []));
                $view->with('weeklyPlanSlot', new \App\Data\WeeklySubmissionSlot(null, null));
                $view->with('weeklyReportSlot', new \App\Data\WeeklySubmissionSlot(null, null));
                $view->with('weeklyGracePlanSlot', new \App\Data\WeeklySubmissionSlot(null, null));
                $view->with('weeklyGraceReportSlot', new \App\Data\WeeklySubmissionSlot(null, null));
                $view->with('weeklyPlanGraceEntries', collect());
                $view->with('weeklyReportGraceEntries', collect());
                $view->with('weeklyPlanGracePeriod', null);
                $view->with('weeklyPlanGraceEndsAt', null);
                $view->with('weeklyReportGracePeriod', null);
                $view->with('weeklyReportGraceEndsAt', null);

                return;
            }

            $windows = app(\App\Services\WeeklySubmissionWindowService::class);
            $planGraceEntries = $windows->gracePlanEntries($user);
            $reportGraceEntries = $windows->graceReportEntries($user);
            $firstPlanGrace = $planGraceEntries->first();
            $firstReportGrace = $reportGraceEntries->first();

            $view->with(
                'weeklySubmissionReminder',
                app(WeeklySubmissionReminderService::class)->forUser($user)
            );
            $view->with('weeklyPlanSlot', $windows->resolvePlanSlot($user));
            $view->with('weeklyReportSlot', $windows->resolveReportSlot($user));
            $view->with('weeklyGracePlanSlot', $firstPlanGrace ?? new \App\Data\WeeklySubmissionSlot(null, null));
            $view->with('weeklyGraceReportSlot', $firstReportGrace ?? new \App\Data\WeeklySubmissionSlot(null, null));
            $view->with('weeklyPlanGraceEntries', $planGraceEntries);
            $view->with('weeklyReportGraceEntries', $reportGraceEntries);
            $view->with('weeklyPlanGracePeriod', $firstPlanGrace?->period);
            $view->with('weeklyPlanGraceEndsAt', $firstPlanGrace?->graceEndsAt);
            $view->with('weeklyReportGracePeriod', $firstReportGrace?->period);
            $view->with('weeklyReportGraceEndsAt', $firstReportGrace?->graceEndsAt);
        });
    }

    /**
     * Queue Gooey toasts on redirects without also flashing session('success').
     */
    protected function registerGooeyToastMacros(): void
    {
        RedirectResponse::macro('toastSuccess', function (string $title, ?string $message = null) {
            GooeyToast::success($title, $message);

            return $this;
        });

        RedirectResponse::macro('toastError', function (string $title, ?string $message = null) {
            GooeyToast::error($title, $message);

            return $this;
        });

        RedirectResponse::macro('toastWarning', function (string $title, ?string $message = null) {
            GooeyToast::warning($title, $message);

            return $this;
        });

        RedirectResponse::macro('toastInfo', function (string $title, ?string $message = null) {
            GooeyToast::info($title, $message);

            return $this;
        });

        RedirectResponse::macro('withGooeyToast', function (string $type, string $title, array $options = []) {
            $builder = GooeyToast::make($title, $type);

            if (! empty($options['message'])) {
                $builder->message((string) $options['message']);
            }

            if (! empty($options['details']) && is_array($options['details'])) {
                $builder->details($options['details']);
            }

            if (! empty($options['footer'])) {
                $builder->footer((string) $options['footer']);
            }

            if (! empty($options['actions']) && is_array($options['actions'])) {
                $builder->actions($options['actions']);
            }

            if (! empty($options['duration'])) {
                $builder->duration((int) $options['duration']);
            }

            if (! empty($options['persistent'])) {
                $builder->persistent();
            }

            $builder->send();

            return $this;
        });
    }

    /**
     * The Document model reads from a SQL view over domain tables. Recreate it
     * when missing so create/save redirects do not 404 after cutover.
     */
    protected function ensureDocumentsUnionView(): void
    {
        if (DocumentsUnionView::shouldDeferAutoEnsure()) {
            return;
        }

        try {
            if (! DocumentsUnionView::canCreate()) {
                return;
            }

            if (! DocumentsUnionView::exists()) {
                DocumentsUnionView::recreate(DB::connection()->getDriverName() === 'mysql');
            }
        } catch (\Throwable) {
            // Ignore when the database is not ready yet (CI composer hooks, missing sqlite file, etc.).
        }
    }

    /**
     * Fix common production misconfigurations that cause 419 CSRF / session errors.
     */
    protected function configureSessionAndUrl(): void
    {
        $domain = env('SESSION_DOMAIN');
        if ($domain === null || $domain === '' || strtolower((string) $domain) === 'null') {
            config(['session.domain' => null]);
        }

        $appUrl = (string) config('app.url', '');
        $useHttps = str_starts_with($appUrl, 'https://') && ! $this->app->environment('local');

        if (env('SESSION_SECURE_COOKIE') === null && $useHttps) {
            config(['session.secure' => true]);
        }

        if ($useHttps) {
            URL::forceScheme('https');
        }
    }
}
