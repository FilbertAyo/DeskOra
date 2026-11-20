<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Request as FinanceRequest;
use App\Models\RequestApproval;
use App\Models\RequisitionItem;
use App\Models\RequisitionRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Cache::flush();
    }

    public function test_admin_can_view_and_update_forms_logo(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['status' => 'active']);
        $admin->givePermissionTo('manage system settings');

        $this->actingAs($admin)
            ->get(route('settings.system.edit', ['section' => 'logos']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/System/Edit')
                ->where('section', 'logos')
                ->has('logos.formsLogoUrl')
                ->has('updateUrl'));

        $file = UploadedFile::fake()->image('forms-logo.png', 200, 80);

        $this->actingAs($admin)
            ->put(route('settings.system.update'), [
                'section' => 'logos',
                'forms_logo' => $file,
            ])
            ->assertRedirect(route('settings.system.edit', ['section' => 'logos']))
            ->assertSessionHas('success');

        $path = app(SystemSettingService::class)->get(SystemSetting::KEY_FORMS_LOGO);
        $this->assertIsString($path);
        $this->assertStringStartsWith('storage/', $path);
        Storage::disk('public')->assertExists(substr($path, strlen('storage/')));
    }

    public function test_admin_can_update_brand_and_logos(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['status' => 'active']);
        $admin->givePermissionTo('manage system settings');

        $this->actingAs($admin)
            ->put(route('settings.system.update'), [
                'section' => 'brand',
                'app_name' => 'Adilisha Digital',
                'primary_color' => '#112233',
            ])
            ->assertRedirect(route('settings.system.edit', ['section' => 'brand']))
            ->assertSessionHas('success');

        $this->assertSame(
            'Adilisha Digital',
            app(SystemSettingService::class)->get(SystemSetting::KEY_APP_NAME)
        );
        $this->assertSame(
            '#112233',
            app(SystemSettingService::class)->get(SystemSetting::KEY_PRIMARY_COLOR)
        );

        $logo = UploadedFile::fake()->image('org-photo.png', 240, 120);
        $icon = UploadedFile::fake()->image('org-icon.png', 64, 64);

        $this->actingAs($admin)
            ->put(route('settings.system.update'), [
                'section' => 'logos',
                'logo' => $logo,
                'icon' => $icon,
            ])
            ->assertRedirect(route('settings.system.edit', ['section' => 'logos']))
            ->assertSessionHas('success');

        $branding = app(SystemSettingService::class)->branding();
        $this->assertFalse($branding['usingDefaultLogo']);
        $this->assertFalse($branding['usingDefaultIcon']);
        $this->assertStringStartsWith('storage/', $branding['logoPath']);
        $this->assertStringStartsWith('storage/', $branding['iconPath']);
        $this->assertSame('#112233', $branding['primaryColor']);
    }

    public function test_legacy_section_query_maps_to_grouped_tabs(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->givePermissionTo('manage system settings');

        $this->actingAs($admin)
            ->get(route('settings.system.edit', ['section' => 'appearance']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('section', 'brand'));

        $this->actingAs($admin)
            ->get(route('settings.system.edit', ['section' => 'forms']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('section', 'logos'));
    }

    public function test_user_without_permission_cannot_access_system_settings(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->get(route('settings.system.edit'))
            ->assertForbidden();
    }

    public function test_requisition_pdf_download_uses_configured_logo_and_includes_qr(): void
    {
        Storage::fake('public');

        $department = Department::create(['name' => 'Operations', 'status' => 'active']);
        $requester = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'signature' => 'signatures/test.png',
            'title' => 'Officer',
        ]);
        $authorizer = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'signature' => 'signatures/auth.png',
        ]);
        $authorizer->givePermissionTo(['authorize staff requisition', 'download staff requisition']);

        $logo = UploadedFile::fake()->image('org-logo.png', 180, 90);
        $stored = $logo->store('system/forms', 'public');
        app(SystemSettingService::class)->set(
            SystemSetting::KEY_FORMS_LOGO,
            'storage/'.$stored,
            SystemSetting::GROUP_FORMS,
            SystemSetting::TYPE_FILE,
            'Forms logo'
        );

        $request = FinanceRequest::create([
            'request_no' => 'SR900',
            'request_type' => 'Staff Requisition',
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        RequisitionRequest::create([
            'request_id' => $request->id,
            'project_program' => 'Youth Program',
            'activity_code' => 'ACT-01',
            'activity_title' => 'Training',
            'budget_code' => '2026',
            'amount' => 15000,
        ]);

        RequisitionItem::create([
            'request_id' => $request->id,
            'serial_no' => 1,
            'description' => 'Notebooks',
            'unit' => 'pcs',
            'cost' => 15000,
        ]);

        foreach (['review', 'approve', 'authorize'] as $step) {
            RequestApproval::create([
                'request_id' => $request->id,
                'step' => $step,
                'action' => 'approved',
                'actor_id' => $authorizer->id,
                'acted_at' => now(),
            ]);
        }

        $response = $this->actingAs($authorizer)
            ->get(route('petty.requisition.download', $request->id));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertGreaterThan(1000, strlen($response->getContent()));
    }
}
