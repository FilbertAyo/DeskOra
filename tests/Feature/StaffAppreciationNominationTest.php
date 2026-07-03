<?php

namespace Tests\Feature;

use App\Http\Controllers\StaffAppreciationNominationController;
use App\Models\NominationParticipation;
use App\Models\NominationPeriod;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAppreciationNominationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        // Inside the nomination window (application timezone).
        Carbon::setTestNow(Carbon::create(2026, 7, 27, 10, 0, 0));
    }

    private function activeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['status' => 'active'], $attributes));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_modal_is_shown_to_eligible_user(): void
    {
        $user = $this->activeUser();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('Monthly Staff Appreciation Nomination', false);
    }

    public function test_modal_hidden_after_user_responds(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user)->post(route('appreciation.skip'))->assertOk();

        $response = $this->actingAs($user)->get(route('profile.edit'));
        $response->assertDontSee('Monthly Staff Appreciation Nomination', false);
    }

    public function test_user_can_submit_anonymous_nomination(): void
    {
        $user = $this->activeUser();
        $colleague = $this->activeUser();

        $response = $this->actingAs($user)->postJson(route('appreciation.nominate'), [
            'nominee_id' => $colleague->id,
            'reason' => 'Always willing to help.',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('nominations', [
            'nominee_id' => $colleague->id,
            'reason' => 'Always willing to help.',
        ]);
        $this->assertDatabaseHas('nomination_participations', [
            'user_id' => $user->id,
            'status' => NominationParticipation::STATUS_NOMINATED,
        ]);
    }

    public function test_user_cannot_nominate_themselves(): void
    {
        $user = $this->activeUser();

        $response = $this->actingAs($user)->postJson(route('appreciation.nominate'), [
            'nominee_id' => $user->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('nominations', 0);
    }

    public function test_second_nomination_is_rejected(): void
    {
        $user = $this->activeUser();
        $colleague = $this->activeUser();

        $this->actingAs($user)->postJson(route('appreciation.nominate'), [
            'nominee_id' => $colleague->id,
        ])->assertOk();

        $this->actingAs($user)->postJson(route('appreciation.nominate'), [
            'nominee_id' => $colleague->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('nominations', 1);
    }

    public function test_remind_later_snoozes_for_the_session(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user)
            ->post(route('appreciation.remind-later'))
            ->assertOk()
            ->assertSessionHas(StaffAppreciationNominationController::SNOOZE_SESSION_KEY);

        $response = $this->actingAs($user)->get(route('profile.edit'));
        $response->assertDontSee('Monthly Staff Appreciation Nomination', false);
    }

    public function test_staff_search_excludes_self_and_inactive(): void
    {
        $user = $this->activeUser(['name' => 'Alice Actor']);
        $match = $this->activeUser(['name' => 'Bob Colleague']);
        User::factory()->create(['name' => 'Cara Colleague', 'status' => 'inactive']);

        $response = $this->actingAs($user)->getJson(route('appreciation.staff-search', ['q' => 'Colleague']));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($match->id, $ids);
        $this->assertNotContains($user->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_management_view_requires_permission(): void
    {
        $staff = $this->activeUser();
        $this->actingAs($staff)->get(route('management.appreciation.index'))->assertForbidden();

        $manager = $this->activeUser();
        $manager->givePermissionTo('view appreciation nominations');
        $this->actingAs($manager)->get(route('management.appreciation.index'))->assertOk();
    }

    public function test_management_can_view_period_results(): void
    {
        $manager = $this->activeUser();
        $manager->givePermissionTo('view appreciation nominations');

        $voter = $this->activeUser();
        $nominee = $this->activeUser(['name' => 'Nominated Person']);
        app(\App\Services\StaffAppreciationService::class)->recordNomination($voter, $nominee, 'Outstanding');

        $period = NominationPeriod::query()->first();

        $response = $this->actingAs($manager)->get(route('management.appreciation.show', $period));
        $response->assertOk();
        $response->assertSee('Nominated Person', false);
        $response->assertSee('Outstanding', false);
    }
}
