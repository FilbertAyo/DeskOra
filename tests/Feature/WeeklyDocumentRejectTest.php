<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WeeklyDocumentRejectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_reviewer_reject_returns_only_that_plan_to_draft_for_resubmission(): void
    {
        $period = $this->openPeriod();
        $staff = $this->staff();
        $reviewer = $this->reviewer();
        [$plan, $report] = $this->submittedPair($staff, $period);

        $this->actingAs($reviewer)
            ->get(route('documents.show', $plan))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('document.rejectUrl', route('documents.reject', $plan))
                ->where('document.state', Document::STATE_SUBMITTED));

        $this->actingAs($reviewer)
            ->get(route('documents.reviewer-show', $plan))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('document.rejectUrl', route('documents.reject', $plan)));

        $this->actingAs($staff)
            ->get(route('documents.show', $plan))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('document.rejectUrl', null));

        $this->actingAs($staff)
            ->post(route('documents.reject', $plan), ['reason' => 'Needs more detail in the activities.'])
            ->assertForbidden();

        $this->actingAs($reviewer)
            ->post(route('documents.reject', $plan), ['reason' => 'No'])
            ->assertSessionHasErrors('reason');
        $this->assertSame(Document::STATE_SUBMITTED, $plan->fresh()->state);

        $this->actingAs($reviewer)
            ->post(route('documents.reject', $plan), [
                'reason' => 'Please add the field visit to Monday.',
            ])
            ->assertRedirect(route('documents.show', $plan));

        $plan->refresh();
        $report->refresh();
        $this->assertSame(Document::STATE_DRAFT, $plan->state);
        $this->assertSame('Please add the field visit to Monday.', $plan->review_rejection_reason);
        $this->assertSame(Document::STATE_SUBMITTED, $report->state);
        $this->assertNull($report->review_rejection_reason);

        $this->actingAs($staff)
            ->get(route('documents.edit', $plan))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('document.reviewRejectionReason', 'Please add the field visit to Monday.')
                ->where('document.canEdit', true));

        $activity = [
            'day' => 'Monday',
            'activity' => 'Field visit',
            'location' => 'Site',
            'team_member_ids' => [],
            'notes_inclusion' => '',
        ];

        $this->actingAs($staff)
            ->put(route('documents.update', $plan), [
                'action' => 'draft',
                'main_target' => 'Revised target',
                'content' => ['planned_activities' => [$activity]],
            ])
            ->assertRedirect();

        $plan->refresh();
        $this->assertSame(Document::STATE_DRAFT, $plan->state);
        $this->assertSame('Revised target', $plan->main_target);
        $this->assertSame('Please add the field visit to Monday.', $plan->review_rejection_reason);

        $this->actingAs($staff)
            ->put(route('documents.update', $plan), [
                'action' => 'submit',
                'main_target' => 'Revised target',
                'content' => ['planned_activities' => [$activity]],
            ])
            ->assertRedirect();

        $plan->refresh();
        $this->assertSame(Document::STATE_SUBMITTED, $plan->state);
        $this->assertNull($plan->review_rejection_reason);
        $this->assertSame(Document::STATE_SUBMITTED, $report->fresh()->state);
    }

    public function test_reviewer_reject_returns_only_that_report_to_draft_for_resubmission(): void
    {
        $period = $this->openPeriod();
        $staff = $this->staff();
        $reviewer = $this->reviewer();
        [$plan, $report] = $this->submittedPair($staff, $period);

        $this->actingAs($reviewer)
            ->get(route('documents.show', $report))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('document.rejectUrl', route('documents.reject', $report)));

        $this->actingAs($reviewer)
            ->post(route('documents.reject', $report), [
                'reason' => 'The accomplishment status needs an explanation.',
            ])
            ->assertRedirect(route('documents.show', $report));

        $report->refresh();
        $plan->refresh();
        $this->assertSame(Document::STATE_DRAFT, $report->state);
        $this->assertSame('The accomplishment status needs an explanation.', $report->review_rejection_reason);
        $this->assertSame(Document::STATE_SUBMITTED, $plan->state);

        $this->actingAs($staff)
            ->put(route('documents.update', $report), [
                'action' => 'submit',
                'wizard_review' => '1',
                'target_accomplished_status' => 'completed',
            ])
            ->assertRedirect();

        $report->refresh();
        $this->assertSame(Document::STATE_SUBMITTED, $report->state);
        $this->assertNull($report->review_rejection_reason);
    }

    private function openPeriod(): WorkPeriod
    {
        return WorkPeriod::create([
            'year' => 2026,
            'week_number' => 16,
            'week_start_date' => '2026-04-13',
            'week_end_date' => '2026-04-19',
            'plan_deadline' => '2026-04-17',
            'report_deadline' => '2026-04-18',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);
    }

    private function staff(): User
    {
        $department = Department::create(['name' => 'Programs', 'status' => 'active']);
        $staff = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
        ]);
        $staff->givePermissionTo(['create weekly plan', 'create weekly report']);

        return $staff;
    }

    private function reviewer(): User
    {
        $reviewer = User::factory()->create(['status' => 'active']);
        $reviewer->givePermissionTo('view all documents');

        return $reviewer;
    }

    /**
     * @return array{0: Document, 1: Document}
     */
    private function submittedPair(User $staff, WorkPeriod $period): array
    {
        $plan = Document::create([
            'user_id' => $staff->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Week plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Original target',
            'content' => [
                'planned_activities' => [
                    ['day' => 'Monday', 'activity' => 'Desk work'],
                ],
            ],
        ]);

        $report = Document::create([
            'user_id' => $staff->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Week report',
            'state' => Document::STATE_SUBMITTED,
            'content' => [],
        ]);

        return [$plan, $report];
    }
}
