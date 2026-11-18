<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Models\YearPlan;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MonthlyReportPdfUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_create_form_lists_only_months_without_reports_for_year_plan(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('create monthly report');

        YearPlan::create([
            'year' => 2026,
            'created_by' => $user->id,
        ]);

        Document::create([
            'user_id' => $user->id,
            'type' => Document::TYPE_MONTHLY_REPORT,
            'title' => 'Monthly Report - January 2026',
            'state' => Document::STATE_SUBMITTED,
            'month' => 1,
            'year' => 2026,
            'content' => [],
        ]);

        $this->actingAs($user)
            ->get(route('documents.create', ['type' => Document::TYPE_MONTHLY_REPORT, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Documents/MonthlyReportForm')
                ->where('defaultYear', 2026)
                ->where('existingReportMonthsByYear.2026', [1])
                ->has('yearChoices')
                ->where('yearChoices.0', 2026));
    }

    public function test_staff_can_create_monthly_report_by_uploading_pdf_for_open_month(): void
    {
        Storage::fake('public');

        /** @var User $user */
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('create monthly report');

        YearPlan::create([
            'year' => 2026,
            'created_by' => $user->id,
        ]);

        $file = UploadedFile::fake()->create('march-report.pdf', 120, 'application/pdf');

        $this->actingAs($user)
            ->post(route('documents.store'), [
                'type' => Document::TYPE_MONTHLY_REPORT,
                'year' => 2026,
                'month' => 3,
                'file' => $file,
                'action' => 'submit',
            ])
            ->assertRedirect();

        $document = Document::query()
            ->where('user_id', $user->id)
            ->where('type', Document::TYPE_MONTHLY_REPORT)
            ->where('year', 2026)
            ->where('month', 3)
            ->first();

        $this->assertNotNull($document);
        $this->assertSame(Document::STATE_SUBMITTED, $document->state);
        $this->assertNotEmpty($document->file_path);
        $this->assertSame('march-report.pdf', $document->original_filename);
        Storage::disk('public')->assertExists($document->file_path);
    }

    public function test_monthly_report_create_requires_pdf(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('create monthly report');

        YearPlan::create([
            'year' => 2026,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('documents.create', ['type' => Document::TYPE_MONTHLY_REPORT, 'year' => 2026]))
            ->post(route('documents.store'), [
                'type' => Document::TYPE_MONTHLY_REPORT,
                'year' => 2026,
                'month' => 4,
                'action' => 'draft',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('file');
    }
}
