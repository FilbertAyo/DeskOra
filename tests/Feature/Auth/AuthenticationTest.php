<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use SadiqSalau\LaravelOtp\Facades\Otp;
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

    public function test_users_can_choose_to_be_remembered_within_active_window(): void
    {
        $user = User::factory()->create([
            'last_login_at' => now()->subDay(),
            'remember_expires_at' => now()->addDays(2),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertCookieExpired(Auth::guard()->getRecallerName());
        $this->assertNull($user->fresh()->remember_token);
        $this->assertTrue($user->fresh()->remember_expires_at->greaterThan(now()->addDays(3)));
    }

    public function test_remember_me_skips_otp_within_four_day_window(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'last_login_at' => now()->subDays(2),
            'remember_expires_at' => now()->addDays(2),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        Notification::assertNothingSent();
    }

    public function test_remember_me_requires_otp_after_four_day_window_expires(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'last_login_at' => now()->subDays(5),
            'remember_expires_at' => now()->subHour(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login.verify-otp'));
        Notification::assertCount(1);
    }

    public function test_without_remember_me_session_login_always_requires_otp(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'last_login_at' => now()->subHour(),
            'remember_expires_at' => null,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login.verify-otp'));
        Notification::assertCount(1);
    }

    public function test_login_inside_window_skips_otp_when_remember_is_unchecked(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'last_login_at' => now()->subDay(),
            'remember_expires_at' => now()->addDays(2),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertCookieExpired(Auth::guard()->getRecallerName());
        Notification::assertNothingSent();
        $this->assertTrue($user->fresh()->remember_expires_at->greaterThan(now()->addDays(3)));
    }

    public function test_remember_cookie_does_not_restore_session_after_timeout(): void
    {
        $user = User::factory()->create([
            'remember_expires_at' => now()->addDays(2),
            'remember_token' => 'remember-token-value',
        ]);

        $recaller = $user->getAuthIdentifier().'|'.$user->getRememberToken().'|'.$user->getAuthPassword();

        $response = $this->withCookie(Auth::guard('web')->getRecallerName(), $recaller)
            ->get('/dashboard');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_active_use_slides_the_remember_window(): void
    {
        $user = User::factory()->create([
            'remember_expires_at' => now()->addHours(12),
        ]);

        $this->actingAs($user)->get('/session/ping')->assertOk();

        $this->assertTrue($user->fresh()->remember_expires_at->greaterThan(now()->addDays(3)));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'last_login_at' => now()->subHours(12),
            'remember_expires_at' => now()->addDay(),
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
            'remember_expires_at' => now()->addDay(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_users_without_prior_login_are_sent_to_otp_verification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'last_login_at' => null,
            'remember_expires_at' => null,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login.verify-otp'));
    }

    public function test_valid_otp_with_remember_completes_login_and_opens_window(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'last_login_at' => now()->subDays(5),
            'remember_expires_at' => null,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $cacheKey = 'otp_'.md5('login:'.$user->email);
        $otpData = Cache::get($cacheKey);

        $this->assertNotNull($otpData);

        $response = $this->post('/login/verify-otp', [
            'code' => $otpData['code'],
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertCookieExpired(Auth::guard()->getRecallerName());
        $this->assertNull($user->fresh()->remember_token);

        $fresh = $user->fresh();
        $this->assertTrue($fresh->last_login_at->greaterThan(now()->subMinute()));
        $this->assertTrue($fresh->remember_expires_at->greaterThan(now()->addDays(3)));
    }

    public function test_valid_otp_without_remember_does_not_open_window(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'last_login_at' => now()->subDays(5),
            'remember_expires_at' => null,
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
        $this->assertNull($user->fresh()->remember_expires_at);
    }

    public function test_invalid_otp_does_not_authenticate_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'last_login_at' => now()->subDays(5),
            'remember_expires_at' => null,
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

    public function test_login_stays_on_the_form_when_the_verification_email_fails(): void
    {
        $user = User::factory()->create([
            'last_login_at' => null,
            'remember_expires_at' => null,
        ]);

        Otp::shouldReceive('identifier')->once()->andReturnSelf();
        Otp::shouldReceive('send')->once()->andThrow(new \RuntimeException('smtp down'));

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create([
            'last_login_at' => now()->subHours(12),
            'remember_expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
        $response->assertCookieExpired(Auth::guard()->getRecallerName());
    }
}
