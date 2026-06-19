<?php

namespace App\Services;

use App\Models\Request as FinanceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class RequestApprovalNavService
{
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
        return Cache::remember(
            'request-approval-nav-counts-user-'.$user->id,
            now()->addSeconds(45),
            function () use ($user) {
                $counts = [
                    'review' => 0,
                    'approve' => 0,
                    'authorize' => 0,
                ];

                foreach (FinanceRequest::APPROVAL_STAGES as $stage) {
                    if (! FinanceRequest::userCanAccessStage($user, $stage)) {
                        continue;
                    }

                    $types = FinanceRequest::requestTypesUserCanActOnAtStage($user, $stage);
                    if ($types === []) {
                        continue;
                    }

                    $counts[$stage] = FinanceRequest::query()
                        ->actionableForUserAtStage($user, $stage)
                        ->count();
                }

                $counts['total'] = $counts['review'] + $counts['approve'] + $counts['authorize'];

                return $counts;
            }
        );
    }

    public function countForStageAndTypes(User $user, string $stage, ?array $types = null): int
    {
        if (! FinanceRequest::userCanAccessStage($user, $stage)) {
            return 0;
        }

        $actionableTypes = FinanceRequest::requestTypesUserCanActOnAtStage($user, $stage);
        if ($types !== null) {
            $actionableTypes = array_values(array_intersect($actionableTypes, $types));
        }

        if ($actionableTypes === []) {
            return 0;
        }

        return FinanceRequest::query()
            ->actionableForUserAtStage($user, $stage)
            ->whereIn('request_type', $actionableTypes)
            ->count();
    }
}
