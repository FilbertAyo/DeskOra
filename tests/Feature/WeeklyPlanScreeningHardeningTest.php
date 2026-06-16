<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\WeeklyPlan;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Models\WeeklyPlanActivity;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Carbon\Carbon;
use Tests\TestCase;
use App\Database\DocumentsUnionView;

class WeeklyPlanScreeningHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        // Ensure WorkPeriod::getCurrent() returns an open period for auth checks.
        Carbon::setTestNow(Carbon::create(2025, 4, 2, 10, 0, 0));

        // `Document` is backed by a SQL union view (`documents`) over domain tables.
        // SQLite test DB doesn't have it by default, so recreate it for these tests.
        DocumentsUnionView::recreate(withUnicodeCollation: false);
    }

    public function test_weekly_plan_draft_saves_with_main_target_and_activity_together(): void
    {
        [$currentPeriod, $nextPeriod] = $this->createCurrentAndNextPeriods();
        Notification::fake();

        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);
        $member = $this->createUserInDepartment($department, []);

        $response = $this->actingAs($owner)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'action' => 'draft',
            'main_target' => 'My main target',
            'content' => [
                'objective' => [
                    'reflection_progress' => 'Reflection text',
                ],
                'planned_activities' => [
                    [
                        'day' => 'Monday',
                        'activity' => 'Activity 1',
                        'location' => 'Office',
                        'team_member_ids' => [$member->id],
                        'notes_inclusion' => 'Notes',
                    ],
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Document saved as draft.');
        $response->assertSessionDoesntHaveErrors();

        $weeklyPlan = WeeklyPlan::query()
            ->where('user_id', $owner->id)
            ->where('period_id', $nextPeriod->id)
            ->where('state', WeeklyPlan::STATE_DRAFT)
            ->firstOrFail();

        $this->assertSame(1, WeeklyPlanActivity::query()->where('weekly_plan_id', (string) $weeklyPlan->id)->count());
    }

    public function test_weekly_plan_draft_allows_incomplete_activity_rows_and_discards_them(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();
        Notification::fake();

        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);
        $member = $this->createUserInDepartment($department, []);

        $response = $this->actingAs($owner)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'action' => 'draft',
            'main_target' => 'My main target',
            'content' => [
                'objective' => [
                    'reflection_progress' => 'Reflection text',
                ],
                'planned_activities' => [
                    [
                        'day' => 'Monday',
                        'activity' => '', // incomplete row
                        'location' => 'Office',
                        'team_member_ids' => [$member->id],
                        'notes_inclusion' => 'Notes',
                    ],
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Document saved as draft.');
        $response->assertSessionDoesntHaveErrors();

        $weeklyPlan = WeeklyPlan::query()
            ->where('user_id', $owner->id)
            ->where('period_id', $nextPeriod->id)
            ->where('state', WeeklyPlan::STATE_DRAFT)
            ->firstOrFail();

        $this->assertSame(0, WeeklyPlanActivity::query()->where('weekly_plan_id', (string) $weeklyPlan->id)->count());
    }

    public function test_weekly_plan_submit_rejects_incomplete_activity_rows(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();
        Notification::fake();

        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);

        $response = $this->actingAs($owner)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'action' => 'submit',
            'main_target' => 'My main target',
            'content' => [
                'objective' => [
                    'reflection_progress' => 'Reflection text',
                ],
                'planned_activities' => [
                    [
                        'day' => 'Monday',
                        'activity' => '', // incomplete
                        'location' => 'Office',
                        'team_member_ids' => [],
                        'notes_inclusion' => 'Notes',
                    ],
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors();

        $this->assertSame(
            0,
            WeeklyPlan::query()
                ->where('user_id', $owner->id)
                ->where('period_id', $nextPeriod->id)
                ->count()
        );
    }

    public function test_store_weekly_plan_redirect_target_is_reachable(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();
        Notification::fake();

        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);

        $response = $this->actingAs($owner)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'action' => 'draft',
            'main_target' => 'My main target',
            'content' => [
                'planned_activities' => [
                    [
                        'day' => 'Monday',
                        'activity' => 'Activity 1',
                        'location' => 'Office',
                        'team_member_ids' => [],
                        'notes_inclusion' => '',
                    ],
                ],
            ],
        ]);

        $response->assertRedirect();

        // The persisted weekly plan id must equal the Document id used in the
        // redirect, otherwise viewing the freshly saved plan returns 404.
        $weeklyPlan = WeeklyPlan::query()
            ->where('user_id', $owner->id)
            ->where('period_id', $nextPeriod->id)
            ->firstOrFail();

        $this->assertNotNull(Document::find((string) $weeklyPlan->id));

        // Following the post-save redirect should resolve, not 404.
        $followUp = $this->actingAs($owner)->get((string) $response->headers->get('Location'));
        $followUp->assertOk();
    }

    public function test_edit_weekly_plan_draft_saves_with_empty_main_target(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();
        Notification::fake();

        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);

        $plan = Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'user_id' => $owner->id,
            'title' => 'Draft plan',
            'main_target' => 'Initial target',
            'state' => Document::STATE_DRAFT,
        ]);

        $response = $this->actingAs($owner)->put(route('documents.update', $plan->id), [
            'action' => 'draft',
            'main_target' => '',
            'content' => [
                'objective' => [
                    'reflection_progress' => 'Updated reflection',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $this->assertSame(
            1,
            WeeklyPlan::query()
                ->where('user_id', $owner->id)
                ->where('period_id', $nextPeriod->id)
                ->where('state', WeeklyPlan::STATE_DRAFT)
                ->count()
        );
    }

    public function test_edit_weekly_plan_submit_requires_main_target(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();
        Notification::fake();

        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);

        $plan = Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'user_id' => $owner->id,
            'title' => 'Draft plan',
            'main_target' => 'Initial target',
            'state' => Document::STATE_DRAFT,
        ]);

        $response = $this->actingAs($owner)->put(route('documents.update', $plan->id), [
            'action' => 'submit',
            'main_target' => '',
            'content' => [
                'planned_activities' => [
                    [
                        'day' => 'Monday',
                        'activity' => 'Activity 1',
                        'location' => 'Office',
                        'team_member_ids' => [],
                        'notes_inclusion' => '',
                    ],
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('main_target');

        // Plan must remain a draft (submission rejected).
        $this->assertSame(
            WeeklyPlan::STATE_DRAFT,
            WeeklyPlan::query()->where('user_id', $owner->id)->where('period_id', $nextPeriod->id)->firstOrFail()->state
        );
    }

    public function test_create_weekly_plan_redirects_to_edit_when_draft_already_exists(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();

        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);

        Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'user_id' => $owner->id,
            'title' => 'Existing draft',
            'main_target' => 'Existing target',
            'state' => Document::STATE_DRAFT,
        ]);

        $existing = Document::findWeeklyPlanForPeriodOwner((string) $nextPeriod->id, (string) $owner->id);
        $this->assertNotNull($existing);

        $response = $this->actingAs($owner)->get(route('documents.create', [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
        ]));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/documents/'.$existing->id.'/edit', $location);
    }

    public function test_store_weekly_plan_reports_duplicate_validation_for_submitted_plan(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();

        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);

        Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'user_id' => $owner->id,
            'title' => 'Submitted plan',
            'main_target' => 'Submitted target',
            'state' => Document::STATE_SUBMITTED,
        ]);

        $response = $this->actingAs($owner)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'action' => 'draft',
            'main_target' => 'Another target',
            'content' => [
                'planned_activities' => [
                    [
                        'day' => 'Monday',
                        'activity' => 'Activity 1',
                        'location' => 'Office',
                        'team_member_ids' => [],
                        'notes_inclusion' => '',
                    ],
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('period_id');
        $response->assertSessionHas('existing_document_url');
    }

    public function test_token_mismatch_sets_warning_flash_on_staff_pages(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();

        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);

        $payload = [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'action' => 'draft',
            'main_target' => 'My main target',
            'content' => [
                'objective' => [
                    'reflection_progress' => 'Reflection text',
                ],
                'planned_activities' => [
                    [
                        'day' => 'Monday',
                        'activity' => 'Activity 1',
                        'location' => 'Office',
                        'team_member_ids' => [],
                        'notes_inclusion' => '',
                    ],
                ],
            ],
            // Included to mimic a typical form payload.
            '_token' => 'invalid-token',
        ];

        // In PHPUnit feature tests, Laravel's `VerifyCsrfToken` middleware is bypassed,
        // so we simulate the TokenMismatchException directly.
        $referer = route('documents.create', ['type' => Document::TYPE_WEEKLY_PLAN]);

        $request = Request::create(
            route('documents.store'),
            'POST',
            $payload,
            [],
            [],
            [
                'HTTP_ACCEPT' => 'text/html',
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ]
        );
        $request->setRequestFormat('html');
        $request->headers->remove('X-Requested-With');
        $request->headers->set('referer', $referer);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $owner);

        $exception = new TokenMismatchException('CSRF token mismatch.');
        /** @var ExceptionHandler $handler */
        $handler = $this->app->make(ExceptionHandler::class);
        $response = $handler->render($request, $exception);

        $this->assertSame(419, $response->getStatusCode());

        $content = (string) $response->getContent();

        // Blade 419 page wording can vary slightly between setups, but this substring is stable.
        $this->assertStringContainsString('Please refresh the page and try again', $content);
    }

    /**
     * @return array{0: WorkPeriod, 1: WorkPeriod}
     */
    private function createCurrentAndNextPeriods(): array
    {
        $current = WorkPeriod::create([
            'year' => 2025,
            'week_number' => 14,
            'week_start_date' => '2025-03-31',
            'week_end_date' => '2025-04-06',
            'plan_deadline' => '2025-04-04',
            'report_deadline' => '2025-04-05',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $next = WorkPeriod::create([
            'year' => 2025,
            'week_number' => 15,
            'week_start_date' => '2025-04-07',
            'week_end_date' => '2025-04-13',
            'plan_deadline' => '2025-04-11',
            'report_deadline' => '2025-04-12',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        return [$current, $next];
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function createUserInDepartment(Department $department, array $permissions = []): User
    {
        $user = User::factory()->create([
            'department_id' => $department->id,
        ]);

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }
}

