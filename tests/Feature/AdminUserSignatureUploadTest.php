<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminUserSignatureUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_upload_signature_for_user(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['status' => 'active']);
        $admin->givePermissionTo('users management settings');

        $target = User::factory()->create([
            'status' => 'active',
            'signature' => null,
        ]);

        $file = UploadedFile::fake()->image('signature.png');

        $response = $this->actingAs($admin)->post(route('admin.signature.update', $target), [
            'signature' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $target->refresh();
        $this->assertNotNull($target->signature);
        $this->assertStringStartsWith('storage/signatures/', $target->signature);
        Storage::disk('public')->assertExists(str_replace('storage/', '', $target->signature));
    }

    public function test_user_without_permission_cannot_upload_signature(): void
    {
        Storage::fake('public');

        $actor = User::factory()->create(['status' => 'active']);
        $target = User::factory()->create(['status' => 'active', 'signature' => null]);

        $response = $this->actingAs($actor)->post(route('admin.signature.update', $target), [
            'signature' => UploadedFile::fake()->image('signature.png'),
        ]);

        $response->assertForbidden();
        $this->assertNull($target->fresh()->signature);
    }

    public function test_edit_page_shows_signature_upload_and_hides_notification_channel(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->givePermissionTo('users management settings');

        $target = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($admin)->get(route('admin.edit', $target));

        $response->assertOk();
        $response->assertSee('Upload Signature', false);
        $response->assertDontSee('Notification Channel', false);
        $response->assertDontSee('name="notification_channel"', false);
    }
}
