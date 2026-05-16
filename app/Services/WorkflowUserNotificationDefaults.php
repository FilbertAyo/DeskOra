<?php

namespace App\Services;

use App\Models\User;

class WorkflowUserNotificationDefaults
{
    /**
     * Ensure approvers and finance processors receive workflow emails by default.
     *
     * @param  list<string>  $roleNames
     */
    public static function apply(User $user, array $roleNames): void
    {
        if ($user->receivesEmailNotifications()) {
            return;
        }

        $workflowRoles = config('workflow_notifications.roles_default_email_channel', []);
        if ($workflowRoles === []) {
            return;
        }

        $hasWorkflowRole = collect($roleNames)
            ->intersect($workflowRoles)
            ->isNotEmpty();

        if (! $hasWorkflowRole) {
            return;
        }

        $channel = (string) ($user->notification_channel ?? 'sms');
        if (in_array($channel, ['email', 'both'], true)) {
            return;
        }

        $user->forceFill(['notification_channel' => 'email'])->save();
    }
}
