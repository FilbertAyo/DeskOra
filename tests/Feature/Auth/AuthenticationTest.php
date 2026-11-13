<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    public function test_users_can_choose_to_be_remembered(): void
    {
        $user = User::factory()->create([
            'last_login_at' => now()->subHours(48),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertCookie(Auth::guard()->getRecallerName());
    }

    public function test_users_can_authenticate_using_the_login_screen_within_5_days(): void
    {
        $user = User::factory()->create([
            'last_login_at' => now()->subHours(48),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_users_inactive_for_24_to_48_hours_skip_otp_verification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'last_login_at' => now()->subHours(36),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        Notification::assertNothingSent();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'last_login_at' => now()->subHours(12),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_inactive_users_can_not_authenticate(): void
    {
        $user = User::factory()->create([
            'status' => 'suspended',
            'last_login_at' => now()->subHours(12),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_users_inactive_for_5_days_are_sent_to_otp_verification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'last_login_at' => now()->subHours(121),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login.verify-otp'));
        Notification::assertCount(1);
    }

    public function test_users_without_prior_login_are_sent_to_otp_verification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'last_login_at' => null,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login.verify-otp'));
    }

    public function test_valid_otp_completes_login_and_updates_last_login_at(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'last_login_at' => now()->subHours(121),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $cacheKey = 'otp_'.md5('login:'.$user->email);
        $otpData = Cache::get($cacheKey);

        $this->assertNotNull($otpData);

        $response = $this->post('/login/verify-otp', [
            'code' => $otpData['code'],
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertTrue($user->fresh()->last_login_at->greaterThan(now()->subMinute()));
    }

    public function test_invalid_otp_does_not_authenticate_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'last_login_at' => now()->subHours(121),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response = $this->post('/login/verify-otp', [
            'code' => '0000',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('code');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create([
            'last_login_at' => now()->subHours(12),
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
    }
}
