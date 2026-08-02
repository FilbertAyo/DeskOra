<?php

namespace Tests\Feature;

use App\Models\FeatureUsageEvent;
use App\Models\User;
use App\Services\FeatureUsage\FeatureUsageAnalyticsService;
use App\Services\FeatureUsage\FeatureUsageRecorder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FeatureUsageAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_feature_usage_panel(): void
    {
        $this->get(route('feature-usage.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_view_feature_usage(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('feature-usage.index'))
            ->assertForbidden();
    }

    public function test_user_with_permission_can_view_feature_usage_panel(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('view feature usage');

        $this->actingAs($user)
            ->get(route('feature-usage.index'))
            ->assertOk()
            ->assertSee('Feature usage', false)
            ->assertSee('Modules', false)
            ->assertSee('Features &amp; pages', false)
            ->assertSee('Adoption', false)
            ->assertSee('Share', false);
    }

    public function test_authenticated_page_visit_records_page_view(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();

        $this->assertDatabaseHas('feature_usage_events', [
            'user_id' => $user->id,
            'feature_key' => 'module.profile',
            'event_type' => FeatureUsageEvent::EVENT_PAGE_VIEW,
            'module' => 'Profile',
        ]);
    }

    public function test_ingest_endpoint_records_known_feature_click(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->postJson(route('analytics.events.store'), [
                'feature_key' => 'feature.dashboard.performance_details',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('feature_usage_events', [
            'user_id' => $user->id,
            'feature_key' => 'feature.dashboard.performance_details',
            'event_type' => FeatureUsageEvent::EVENT_FEATURE_CLICK,
            'module' => 'Dashboard',
        ]);
    }

    public function test_ingest_endpoint_rejects_unknown_feature_key(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->postJson(route('analytics.events.store'), [
                'feature_key' => 'feature.unknown.not_registered',
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('feature_usage_events', [
            'feature_key' => 'feature.unknown.not_registered',
        ]);
    }

    public function test_page_view_dedupes_within_window(): void
    {
        Cache::flush();

        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create(['status' => 'active']);
        $recorder = app(FeatureUsageRecorder::class);

        $recorder->record(
            user: $user,
            featureKey: 'module.dashboard',
            eventType: FeatureUsageEvent::EVENT_PAGE_VIEW,
            module: 'Dashboard',
            routeName: 'dashboard',
            path: '/dashboard',
            dedupe: true,
        );

        $recorder->record(
            user: $user,
            featureKey: 'module.dashboard',
            eventType: FeatureUsageEvent::EVENT_PAGE_VIEW,
            module: 'Dashboard',
            routeName: 'dashboard',
            path: '/dashboard',
            dedupe: true,
        );

        $this->assertSame(1, FeatureUsageEvent::query()->where('feature_key', 'module.dashboard')->count());
    }

    public function test_analytics_service_computes_adoption_and_share(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $activeA = User::factory()->create(['status' => 'active']);
        $activeB = User::factory()->create(['status' => 'active']);
        User::factory()->create(['status' => 'inactive']);

        $recorder = app(FeatureUsageRecorder::class);

        $recorder->record(
            user: $activeA,
            featureKey: 'module.dashboard',
            eventType: FeatureUsageEvent::EVENT_PAGE_VIEW,
            module: 'Dashboard',
        );
        $recorder->record(
            user: $activeA,
            featureKey: 'module.dashboard',
            eventType: FeatureUsageEvent::EVENT_PAGE_VIEW,
            module: 'Dashboard',
        );
        $recorder->record(
            user: $activeB,
            featureKey: 'module.requests.petty',
            eventType: FeatureUsageEvent::EVENT_PAGE_VIEW,
            module: 'Requests',
        );

        $summary = app(FeatureUsageAnalyticsService::class)->summarize(
            from: now()->subDay(),
            to: now()->addDay(),
        );

        $this->assertSame(2, $summary['active_users']);
        $this->assertSame(3, $summary['total_interactions']);

        $dashboard = $summary['modules']->firstWhere('key', 'Dashboard');
        $this->assertNotNull($dashboard);
        $this->assertSame(2, $dashboard['interactions']);
        $this->assertSame(1, $dashboard['unique_users']);
        $this->assertSame(50.0, $dashboard['adoption']);
        $this->assertSame(66.7, $dashboard['share']);

        $requests = $summary['modules']->firstWhere('key', 'Requests');
        $this->assertNotNull($requests);
        $this->assertSame(33.3, $requests['share']);
    }

    public function test_prune_command_deletes_old_events(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create(['status' => 'active']);

        $old = FeatureUsageEvent::query()->create([
            'user_id' => $user->id,
            'feature_key' => 'module.dashboard',
            'event_type' => FeatureUsageEvent::EVENT_PAGE_VIEW,
            'module' => 'Dashboard',
            'created_at' => now()->subDays(400),
        ]);

        $recent = FeatureUsageEvent::query()->create([
            'user_id' => $user->id,
            'feature_key' => 'module.requests.petty',
            'event_type' => FeatureUsageEvent::EVENT_PAGE_VIEW,
            'module' => 'Requests',
            'created_at' => now()->subDay(),
        ]);

        $this->artisan('feature-usage:prune', ['--days' => 365])
            ->assertSuccessful();

        $this->assertDatabaseMissing('feature_usage_events', ['id' => $old->id]);
        $this->assertDatabaseHas('feature_usage_events', ['id' => $recent->id]);
    }
}
