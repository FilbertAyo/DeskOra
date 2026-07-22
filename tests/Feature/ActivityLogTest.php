<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_activity_log(): void
    {
        $this->get(route('activity-log.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_view_activity_log(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('activity-log.index'))
            ->assertForbidden();
    }

    public function test_user_with_permission_can_view_activity_log_index(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo('view activity log');

        $this->actingAs($user)
            ->get(route('activity-log.index'))
            ->assertOk()
            ->assertSee('Activity log', false)
            ->assertSee('activityLogDetailModal', false);
    }

    public function test_model_update_records_activity_for_authenticated_causer(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo('view activity log');

        $department = Department::create([
            'name' => 'ActivityLogDept',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $department->update(['name' => 'ActivityLogDeptUpdated']);

        $table = config('activitylog.table_name');

        $this->assertDatabaseHas($table, [
            'causer_id' => $user->id,
            'causer_type' => User::class,
            'subject_id' => $department->id,
            'subject_type' => Department::class,
        ]);

        $this->assertGreaterThanOrEqual(1, Activity::query()->where('subject_id', $department->id)->count());
        $this->assertTrue(
            Activity::query()
                ->where('subject_id', $department->id)
                ->where('event', 'updated')
                ->exists()
        );
    }

    public function test_document_content_update_records_activity(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo('view activity log');

        $this->actingAs($user);

        $document = Document::create([
            'user_id' => $user->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Staff plan',
            'state' => Document::STATE_DRAFT,
            'content' => ['activities' => [['title' => 'A']]],
        ]);

        $document->update([
            'content' => ['activities' => [['title' => 'B']]],
        ]);

        $activity = Activity::query()
            ->where('subject_type', Document::class)
            ->where('subject_id', $document->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($user->id, $activity->causer_id);
        $this->assertStringContainsString('weekly plan', strtolower($activity->description));
        $this->assertTrue(data_get($activity->properties, 'attributes.content._summarized') === true);
    }

    public function test_activity_detail_returns_json_for_modal(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo('view activity log');

        $this->actingAs($user);

        $department = Department::create([
            'name' => 'ModalDept',
            'status' => 'active',
        ]);
        $department->update(['name' => 'ModalDeptUpdated']);

        $activity = Activity::query()
            ->where('subject_id', $department->id)
            ->latest('id')
            ->firstOrFail();

        $this->getJson(route('activity-log.show', $activity))
            ->assertOk()
            ->assertJsonPath('id', $activity->id)
            ->assertJsonPath('staff', $user->name)
            ->assertJsonStructure([
                'id',
                'when',
                'staff',
                'log_name',
                'event',
                'description',
                'subject',
                'properties',
                'properties_pretty',
            ]);
    }
}
