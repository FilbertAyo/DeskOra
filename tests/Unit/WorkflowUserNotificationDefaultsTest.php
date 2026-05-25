<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\WorkflowUserNotificationDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowUserNotificationDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_document_approver_role_upgrades_sms_channel_to_email(): void
    {
        $user = User::factory()->create([
            'notification_channel' => 'sms',
        ]);

        WorkflowUserNotificationDefaults::apply($user, ['document_approver']);

        $this->assertSame('email', $user->fresh()->notification_channel);
    }

    public function test_staff_role_does_not_change_notification_channel(): void
    {
        $user = User::factory()->create([
            'notification_channel' => 'sms',
        ]);

        WorkflowUserNotificationDefaults::apply($user, ['staff']);

        $this->assertSame('sms', $user->fresh()->notification_channel);
    }

    public function test_existing_both_channel_is_left_unchanged(): void
    {
        $user = User::factory()->create([
            'notification_channel' => 'both',
        ]);

        WorkflowUserNotificationDefaults::apply($user, ['document_approver']);

        $this->assertSame('both', $user->fresh()->notification_channel);
    }
}
