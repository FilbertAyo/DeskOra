<?php

namespace App\Http\Middleware;

use App\Http\Controllers\StaffAppreciationNominationController;
use App\Models\AnonymousFeedback;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\PendingApprovalGate;
use App\Services\StaffAppreciationService;
use App\Support\PortalNav;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'branding' => [
                'appName' => config('app.name', 'Adilisha Portal'),
                'productName' => config('branding.product_name', 'DeskOra'),
                'logo' => asset('assets/images/logo.png'),
                'logoIcon' => asset('assets/images/logo-icon.png'),
            ],
            'auth' => [
                'user' => $user instanceof User ? $this->userPayload($user) : null,
            ],
            'nav' => $user instanceof User ? PortalNav::forUser($user) : ['groups' => []],
            'currentPeriod' => $user instanceof User ? $this->currentPeriod() : null,
            'notifications' => $user instanceof User ? $this->notifications($user) : [
                'unread' => 0,
                'items' => [],
                'canManage' => false,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
                'status' => $request->session()->get('status'),
            ],
            'overlays' => $user instanceof User ? $this->overlays($request, $user) : [
                'demo' => false,
                'approvalGate' => null,
                'nomination' => null,
                'anonymousFeedback' => null,
            ],
        ];
    }

    /**
     * @return array{demo: bool, approvalGate: array<string, mixed>|null, nomination: array{periodId: string, label: string}|null, anonymousFeedback: array{count: int, hideOnCurrentPage: bool}|null}
     */
    private function overlays(Request $request, User $user): array
    {
        $demo = $user->isDemoViewer();
        $approvalGate = null;
        $nomination = null;
        $anonymousFeedback = null;

        if (($user->status ?? '') === 'active' && ! $demo && $request->hasSession()) {
            $gate = app(PendingApprovalGate::class);

            if ($gate->userIsResponsible($user)) {
                $onWorkRoute = $gate->isOnWorkRoute($request->route()?->getName());
                $snapshot = $gate->snapshotFor($user, $request->session(), ! $onWorkRoute);

                if ($snapshot->showModal && ! $onWorkRoute) {
                    $approvalGate = $snapshot->toArray();
                }
            }

            if ($approvalGate === null && Schema::hasTable('nomination_periods')) {
                $service = app(StaffAppreciationService::class);
                $period = $service->currentOpenPeriod();

                if ($period && ! $service->hasResponded($period, $user)) {
                    $snoozed = $request->session()->get(StaffAppreciationNominationController::SNOOZE_SESSION_KEY);

                    if ($snoozed !== $period->id) {
                        $nomination = [
                            'periodId' => (string) $period->id,
                            'label' => $period->label(),
                        ];
                    }
                }
            }
        }

        if (
            ($user->status ?? '') === 'active'
            && $user->can('manage anonymous feedback')
            && Schema::hasTable('anonymous_feedback')
        ) {
            $count = (int) Cache::remember(
                'anonymous-feedback-new-count',
                now()->addSeconds(45),
                fn () => AnonymousFeedback::query()->new()->count()
            );

            if ($count > 0) {
                $anonymousFeedback = [
                    'count' => $count,
                    'hideOnCurrentPage' => $request->routeIs('anonymous-feedback.*'),
                ];
            }
        }

        return [
            'demo' => $demo,
            'approvalGate' => $approvalGate,
            'nomination' => $nomination,
            'anonymousFeedback' => $anonymousFeedback,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $name = (string) $user->name;

        return [
            'id' => $user->id,
            'name' => $name,
            'email' => $user->email,
            'status' => $user->status,
            'isDemo' => $user->isDemoViewer(),
            'avatar' => asset($user->file ?: 'assets/images/profile.png'),
            'initials' => $this->initials($name),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }

    /**
     * @return array{week_number: int|string|null, date_range: string, status: string}|null
     */
    private function currentPeriod(): ?array
    {
        $period = Cache::remember('current-work-period-nav', now()->addSeconds(60), function () {
            return WorkPeriod::getCurrent();
        });

        if (! $period) {
            return null;
        }

        return [
            'week_number' => $period->week_number,
            'date_range' => $period->date_range,
            'status' => (string) $period->status,
        ];
    }

    /**
     * @return array{unread: int, canManage: bool, items: array<int, array<string, string>>}
     */
    private function notifications(User $user): array
    {
        $items = $user->unreadNotifications()->latest()->limit(5)->get()->map(function ($notification) {
            $data = is_array($notification->data ?? null) ? $notification->data : [];

            return [
                'id' => (string) $notification->id,
                'title' => (string) ($data['title'] ?? 'Notification'),
                'message' => (string) ($data['message'] ?? 'You have a new update.'),
                'time' => (string) ($notification->created_at?->diffForHumans() ?? ''),
                'href' => route('notifications.open', $notification->id),
            ];
        })->all();

        return [
            'unread' => $user->unreadNotifications()->count(),
            'canManage' => $user->can('view notification settings'),
            'items' => $items,
        ];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : 'U';
    }
}
