<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\PortalAiContextResolver;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalAiContextResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_navigation_questions_do_not_load_live_data(): void
    {
        $user = $this->makeUser();

        $context = app(PortalAiContextResolver::class)->resolve(
            $user,
            'How do I submit a weekly plan?'
        );

        $this->assertFalse($context['has_live_data']);
        $this->assertSame('', $context['snapshot']);
    }

    public function test_status_questions_include_weekly_submission_snapshot(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 4, 7, 10, 0, 0));
        [, $currentPeriod] = $this->createCurrentAndNextPeriods();
        $user = $this->makeUser(['create weekly plan', 'create weekly report']);

        Document::create([
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $currentPeriod->id,
            'user_id' => $user->id,
            'title' => 'My report',
            'main_target' => 'Weekly target',
            'state' => Document::STATE_SUBMITTED,
            'content' => [],
        ]);

        $context = app(PortalAiContextResolver::class)->resolve(
            $user,
            'Have I submitted my weekly report?'
        );

        $this->assertTrue($context['has_live_data']);
        $this->assertStringContainsString('Weekly submissions for this user', $context['snapshot']);
        $this->assertStringContainsString('submitted', strtolower($context['snapshot']));
    }

    public function test_navigation_documents_question_does_not_load_live_data(): void
    {
        $user = $this->makeUser();

        $context = app(PortalAiContextResolver::class)->resolve(
            $user,
            'How do I open all documents?'
        );

        $this->assertFalse($context['has_live_data']);
    }

    public function test_my_shared_documents_question_includes_document_counts(): void
    {
        $user = $this->makeUser();

        $context = app(PortalAiContextResolver::class)->resolve(
            $user,
            'How many documents are shared with me?'
        );

        $this->assertTrue($context['has_live_data']);
        $this->assertStringContainsString('Documents for this user', $context['snapshot']);
    }

    private function makeUser(array $permissions = []): User
    {
        $department = Department::create(['name' => 'Programs']);
        $user = User::factory()->create([
            'department_id' => $department->id,
            'title' => 'EdTech Lead',
        ]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    /**
     * @return array{0: WorkPeriod, 1: WorkPeriod}
     */
    private function createCurrentAndNextPeriods(): array
    {
        $currentStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $nextStart = $currentStart->copy()->addWeek();

        $current = WorkPeriod::create([
            'year' => $currentStart->isoWeekYear(),
            'week_number' => $currentStart->isoWeek(),
            'week_start_date' => $currentStart->format('Y-m-d'),
            'week_end_date' => $currentStart->copy()->addDays(6)->format('Y-m-d'),
            'plan_deadline' => $currentStart->copy()->subDays(2)->format('Y-m-d'),
            'report_deadline' => $currentStart->copy()->addDays(5)->format('Y-m-d'),
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $next = WorkPeriod::create([
            'year' => $nextStart->isoWeekYear(),
            'week_number' => $nextStart->isoWeek(),
            'week_start_date' => $nextStart->format('Y-m-d'),
            'week_end_date' => $nextStart->copy()->addDays(6)->format('Y-m-d'),
            'plan_deadline' => $nextStart->copy()->subDays(2)->format('Y-m-d'),
            'report_deadline' => $nextStart->copy()->addDays(5)->format('Y-m-d'),
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        return [$next, $current];
    }
}
