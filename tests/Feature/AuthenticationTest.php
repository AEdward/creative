<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_pages_are_available(): void
    {
        foreach (['login', 'register', 'forgot-password'] as $page) {
            $this->get('/'.$page)->assertOk();
        }
    }

    public function test_registration_ignores_privileged_role_input_and_sends_verification(): void
    {
        Notification::fake();
        $this->post('/register', ['name' => 'New Learner', 'email' => 'learner@example.test', 'password' => 'Long-test-password123!', 'password_confirmation' => 'Long-test-password123!', 'role' => 'admin', 'status' => 'active'])->assertRedirect('/verify-email');
        $user = User::where('email', 'learner@example.test')->firstOrFail();
        $this->assertSame('student', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_valid_login_regenerates_session_and_logout_invalidates_authentication(): void
    {
        $user = User::factory()->create(['password' => 'Long-test-password123!']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Long-test-password123!'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_login_popup_is_offered_to_guests_and_keeps_its_errors_separate(): void
    {
        $this->get('/courses')->assertOk()->assertSee('id="login-dialog"', false)->assertSee('data-login-open', false);
        $this->get('/login')->assertOk()->assertDontSee('id="login-dialog"', false);

        $user = User::factory()->create(['password' => 'Long-test-password123!']);
        $this->from('/contact')->post('/login', ['email' => $user->email, 'password' => 'wrong', 'auth_modal' => '1'])
            ->assertRedirect('/contact')
            ->assertSessionHasErrorsIn('login', 'email')
            ->assertSessionDoesntHaveErrors('email');
        $this->assertGuest();
        $this->from('/contact')->followingRedirects()->post('/login', ['email' => $user->email, 'password' => 'wrong', 'auth_modal' => '1'])
            ->assertSee('data-open-on-load', false)
            ->assertSee('These credentials do not match an active account.');

        $this->post('/login', ['email' => $user->email, 'password' => 'Long-test-password123!', 'auth_modal' => '1'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->get('/courses')->assertDontSee('id="login-dialog"', false);
    }

    public function test_login_is_rate_limited_and_invalid_credentials_do_not_authenticate(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_suspended_accounts_cannot_log_in(): void
    {
        $user = User::factory()->create(['status' => 'suspended', 'password' => 'Long-test-password123!']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Long-test-password123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_unverified_users_cannot_access_dashboard_and_valid_signed_links_verify(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/dashboard')->assertRedirect('/verify-email');
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect('/dashboard');
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_link_cannot_verify_another_account(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $other->id, 'hash' => sha1($other->email)]);
        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_password_reset_notification_and_valid_token_work(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
        $token = Password::createToken($user);
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'Replacement-password123!', 'password_confirmation' => 'Replacement-password123!'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('Replacement-password123!', $user->fresh()->password));
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'Another-password123!', 'password_confirmation' => 'Another-password123!'])->assertSessionHasErrors('email');
    }

    public function test_registration_requires_unique_email_and_confirmed_password(): void
    {
        $user = User::factory()->create();
        $this->post('/register', ['name' => 'Learner', 'email' => $user->email, 'password' => 'short', 'password_confirmation' => 'different'])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_malformed_auth_fields_render_validation_feedback_without_server_errors(): void
    {
        $this->from('/login')->post('/login', ['email' => ['invalid'], 'password' => 'bad'])->assertSessionHasErrors('email');
        $this->withCookie(config('session.cookie'), session()->getId())->get('/login')->assertOk()->assertSee('The email field must be a string.');
        $this->from('/register')->post('/register', ['name' => ['invalid'], 'email' => 'valid@example.test', 'password' => 'Long-test-password123!', 'password_confirmation' => 'Long-test-password123!'])->assertSessionHasErrors('name');
        $this->withCookie(config('session.cookie'), session()->getId())->get('/register')->assertOk()->assertSee('The name field must be a string.');
    }

    public function test_login_attempts_do_not_consume_the_password_reset_rate_limit(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect()->assertSessionHas('status');
    }
}
