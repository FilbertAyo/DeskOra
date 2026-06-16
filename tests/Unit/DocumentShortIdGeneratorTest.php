<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Support\ShortId;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentShortIdGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_new_documents_receive_four_character_ids(): void
    {
        $period = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 16,
            'week_start_date' => '2026-04-13',
            'week_end_date' => '2026-04-19',
            'plan_deadline' => '2026-04-17',
            'report_deadline' => '2026-04-18',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo(['create weekly plan']);

        $document = Document::create([
            'user_id' => $user->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Short id plan',
            'state' => Document::STATE_DRAFT,
            'content' => [],
        ]);

        $this->assertTrue(ShortId::is((string) $document->id));
        $this->assertSame(4, strlen((string) $document->id));
    }

    public function test_generated_ids_are_globally_unique_across_document_types(): void
    {
        $generator = app(\App\Services\ShortIdGenerator::class);
        $period = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 17,
            'week_start_date' => '2026-04-20',
            'week_end_date' => '2026-04-26',
            'plan_deadline' => '2026-04-24',
            'report_deadline' => '2026-04-25',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo(['create weekly plan', 'create weekly report']);

        $plan = Document::create([
            'user_id' => $user->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Plan',
            'state' => Document::STATE_DRAFT,
            'content' => [],
        ]);

        $report = Document::create([
            'user_id' => $user->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Report',
            'state' => Document::STATE_DRAFT,
            'content' => [],
        ]);

        $this->assertNotSame($plan->id, $report->id);
        $this->assertTrue($generator->isAvailable((string) $plan->id) === false);
    }
}
