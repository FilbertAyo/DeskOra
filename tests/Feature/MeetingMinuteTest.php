<?php

namespace Tests\Feature;

use App\Models\MeetingMinute;
use App\Models\User;
use App\Models\WorkPeriod;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeetingMinuteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_authenticated_staff_can_access_index(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('staff');
        $this->createPeriod(2026, 10, '2026-03-02', '2026-03-08');

        $response = $this->actingAs($user)
            ->get(route('meeting-minutes.index'));

        $response->assertOk()
            ->assertSee('Meeting Minutes')
            ->assertSee('Not Available');
    }

    public function test_authenticated_staff_can_view_existing_minutes(): void
    {
        Storage::fake('public');

        /** @var User $user */
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('staff');
        $period = $this->createPeriod(2026, 10, '2026-03-02', '2026-03-08');
        $minute = $this->createMinuteForPeriod($period, $user);

        $this->actingAs($user)
            ->get(route('meeting-minutes.show', $minute))
            ->assertOk()
            ->assertSee('Week 10 of 2026');
    }

    public function test_user_without_manage_minutes_cannot_access_create_or_store(): void
    {
        Storage::fake('public');

        /** @var User $user */
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('staff');
        $period = $this->createPeriod(2026, 10, '2026-03-02', '2026-03-08');
        $file = UploadedFile::fake()->create('minutes.pdf', 100, 'application/pdf');

        $this->actingAs($user)
            ->get(route('meeting-minutes.create', ['period_id' => $period->id]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('meeting-minutes.store'), [
                'period_id' => $period->id,
                'file' => $file,
            ])
            ->assertForbidden();
    }

    public function test_user_with_manage_minutes_can_upload_pdf_for_period(): void
    {
        Storage::fake('public');

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->givePermissionTo('manage minutes');
        $period = $this->createPeriod(2026, 10, '2026-03-02', '2026-03-08');
        $file = UploadedFile::fake()->create('minutes.pdf', 100, 'application/pdf');

        $response = $this->actingAs($manager)
            ->post(route('meeting-minutes.store'), [
                'period_id' => $period->id,
                'file' => $file,
            ]);

        $minute = MeetingMinute::query()->where('period_id', $period->id)->first();
        $this->assertNotNull($minute);
        $response->assertRedirect(route('meeting-minutes.show', $minute));

        Storage::disk('public')->assertExists($minute->path);
        $this->assertSame('minutes.pdf', $minute->stored_name);
        $this->assertSame((string) $manager->id, (string) $minute->uploaded_by);
    }

    public function test_cannot_create_duplicate_minutes_for_same_period(): void
    {
        Storage::fake('public');

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->givePermissionTo('manage minutes');
        $period = $this->createPeriod(2026, 10, '2026-03-02', '2026-03-08');
        $this->createMinuteForPeriod($period, $manager);

        $file = UploadedFile::fake()->create('another.pdf', 100, 'application/pdf');

        $this->actingAs($manager)
            ->post(route('meeting-minutes.store'), [
                'period_id' => $period->id,
                'file' => $file,
            ])
            ->assertSessionHasErrors('period_id');

        $this->assertSame(1, MeetingMinute::query()->where('period_id', $period->id)->count());
    }

    public function test_view_route_returns_inline_pdf_and_download_returns_attachment(): void
    {
        Storage::fake('public');

        /** @var User $user */
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('staff');
        $period = $this->createPeriod(2026, 10, '2026-03-02', '2026-03-08');
        $minute = $this->createMinuteForPeriod($period, $user);

        $viewResponse = $this->actingAs($user)
            ->get(route('meeting-minutes.view', $minute));

        $viewResponse->assertOk();
        $this->assertStringContainsString('inline', (string) $viewResponse->headers->get('content-disposition'));
        $this->assertSame('application/pdf', $viewResponse->headers->get('content-type'));

        $downloadResponse = $this->actingAs($user)
            ->get(route('meeting-minutes.download', $minute));

        $downloadResponse->assertOk();
        $this->assertStringContainsString('attachment', (string) $downloadResponse->headers->get('content-disposition'));
    }

    public function test_user_with_manage_minutes_can_update_existing_minutes(): void
    {
        Storage::fake('public');

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->givePermissionTo('manage minutes');
        $period = $this->createPeriod(2026, 10, '2026-03-02', '2026-03-08');
        $minute = $this->createMinuteForPeriod($period, $manager);
        $oldPath = $minute->path;

        $newFile = UploadedFile::fake()->create('updated-minutes.pdf', 120, 'application/pdf');

        $response = $this->actingAs($manager)
            ->put(route('meeting-minutes.update', $minute), [
                'file' => $newFile,
            ]);

        $response->assertRedirect(route('meeting-minutes.show', $minute));

        $minute->refresh();
        $this->assertSame('updated-minutes.pdf', $minute->stored_name);
        $this->assertNotSame($oldPath, $minute->path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($minute->path);
    }

    public function test_user_without_manage_minutes_cannot_edit_or_update(): void
    {
        Storage::fake('public');

        /** @var User $staff */
        $staff = User::factory()->create();
        $staff->assignRole('staff');

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->givePermissionTo('manage minutes');

        $period = $this->createPeriod(2026, 10, '2026-03-02', '2026-03-08');
        $minute = $this->createMinuteForPeriod($period, $manager);
        $file = UploadedFile::fake()->create('updated.pdf', 100, 'application/pdf');

        $this->actingAs($staff)
            ->get(route('meeting-minutes.edit', $minute))
            ->assertForbidden();

        $this->actingAs($staff)
            ->put(route('meeting-minutes.update', $minute), [
                'file' => $file,
            ])
            ->assertForbidden();
    }

    private function createPeriod(int $year, int $weekNumber, string $start, string $end): WorkPeriod
    {
        return WorkPeriod::create([
            'year' => $year,
            'week_number' => $weekNumber,
            'week_start_date' => $start,
            'week_end_date' => $end,
            'plan_deadline' => $end,
            'report_deadline' => $end,
            'status' => WorkPeriod::STATUS_OPEN,
        ]);
    }

    private function createMinuteForPeriod(WorkPeriod $period, User $user): MeetingMinute
    {
        $file = UploadedFile::fake()->create('minutes.pdf', 100, 'application/pdf');
        $path = $file->store('meeting-minutes/'.$period->id, 'public');

        return MeetingMinute::create([
            'period_id' => $period->id,
            'stored_name' => 'minutes.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => 100,
            'uploaded_by' => $user->id,
        ]);
    }
}
