<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;

class DocumentPermissionService
{
    public function canUserCreateType(User $user, string $type, ?WorkPeriod $period = null): bool
    {
        if (in_array($type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT, Document::TYPE_ACTION_LOG], true)) {
            if ($period && ! $period->isOpen()) {
                return false;
            }

            if (! $period) {
                $period = WorkPeriod::getCurrent();
                if (! $period || ! $period->isOpen()) {
                    return false;
                }
            }
        }

        if ($user->hasPermissionTo('approve documents')) {
            return true;
        }

        return match ($type) {
            Document::TYPE_WEEKLY_PLAN,
            Document::TYPE_WEEKLY_REPORT,
            Document::TYPE_MONTHLY_REPORT => $user->hasAnyPermission([
                'create weekly plan',
                'create weekly report',
                'create monthly report',
            ]),
            Document::TYPE_ACTION_LOG => $user->hasPermissionTo('create action logs'),
            default => false,
        };
    }

    /**
     * @return array<string, string>
     */
    public function availableTypesForUser(User $user): array
    {
        $availableTypes = [];

        foreach (Document::getTypes() as $type => $name) {
            if ($this->canUserCreateType($user, $type)) {
                $availableTypes[$type] = $name;
            }
        }

        return $availableTypes;
    }
}
