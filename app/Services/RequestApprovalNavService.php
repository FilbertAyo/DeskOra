<?php

namespace App\Services;

use App\Models\Request as FinanceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class RequestApprovalNavService
{
    public static function cacheKeyFor(User $user): string
    {
        return 'request-approval-nav-counts-user-'.$user->id;
    }

    public function forgetCountsFor(User $user): void
    {
        Cache::forget(self::cacheKeyFor($user));
        Cache::forget(self::cacheKeyFor($user).'-stages');
    }

    /**
     * @return array{
     *     review: int,
     *     approve: int,
     *     authorize: int,
     *     total: int
     * }
     */
    public function countsFor(User $user): array
    {
        $breakdown = $this->stageBreakdown($user);
        $counts = [
            'review' => array_sum($breakdown['review'] ?? []),
            'approve' => array_sum($breakdown['approve'] ?? []),
            'authorize' => array_sum($breakdown['authorize'] ?? []),
        ];
        $counts['total'] = $counts['review'] + $counts['approve'] + $counts['authorize'];

        return $counts;
    }

    public function countForStageAndTypes(User $user, string $stage, ?array $types = null): int
    {
        $stageCounts = $this->stageBreakdown($user)[$stage] ?? [];

        if ($types === null) {
            return array_sum($stageCounts);
        }

        $total = 0;
        foreach ($types as $type) {
            $total += (int) ($stageCounts[$type] ?? 0);
        }

        return $total;
    }

    /**
     * One grouped count per approval stage, shared by the sidebar, dashboard, and approval gate.
     *
     * @return array<string, array<string, int>>
     */
    private function stageBreakdown(User $user): array
    {
        return Cache::remember(
            self::cacheKeyFor($user).'-stages',
            now()->addSeconds(45),
            function () use ($user) {
                $breakdown = [];

                foreach (FinanceRequest::APPROVAL_STAGES as $stage) {
                    if (! FinanceRequest::userCanAccessStage($user, $stage)) {
                        $breakdown[$stage] = [];

                        continue;
                    }

                    $types = FinanceRequest::requestTypesUserCanActOnAtStage($user, $stage);
                    if ($types === []) {
                        $breakdown[$stage] = [];

                        continue;
                    }

                    $rows = FinanceRequest::query()
                        ->actionableForUserAtStage($user, $stage)
                        ->selectRaw('request_type, COUNT(*) as total')
                        ->groupBy('request_type')
                        ->pluck('total', 'request_type');

                    $breakdown[$stage] = $rows
                        ->map(fn ($total) => (int) $total)
                        ->all();
                }

                return $breakdown;
            }
        );
    }
}
