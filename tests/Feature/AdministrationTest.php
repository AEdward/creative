<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Services\AccountAdministration;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bootstrap_uses_a_prompted_password_and_never_promotes_existing_users(): void
    {
        $this->artisan('academy:admin', ['email' => 'operator@example.test', '--name' => 'Local operator'])
            ->expectsQuestion('Password (at least 12 characters)', 'A-strong-test-password123!')->assertExitCode(0);
        $user = User::where('email', 'operator@example.test')->firstOrFail();
        $this->assertSame('admin', $user->role);
        $this->assertTrue(Hash::check('A-strong-test-password123!', $user->password));
        $this->artisan('academy:admin', ['email' => $user->email, '--name' => 'Other'])->assertExitCode(1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_administrator_can_open_the_panel_and_management_pages(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['/admin', '/admin/categories', '/admin/users', '/admin/courses', '/admin/contact-messages', '/admin/orders'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_student_cannot_access_admin_management_pages(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['/admin/categories', '/admin/users', '/admin/courses', '/admin/contact-messages', '/admin/orders'] as $url) {
            $this->get($url)->assertRedirect(route('dashboard'));
        }
    }

    public function test_default_seeding_creates_taxonomy_without_fake_users_or_courses(): void
    {
        $this->seed();
        $this->seed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('courses', 0);
        $this->assertDatabaseCount('categories', 5);
    }

    public function test_instructor_approval_is_audited_and_requires_an_administrator(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create();
        app(AccountAdministration::class)->approveInstructor($admin, $student);
        $this->assertSame('instructor', $student->fresh()->role);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'instructor.approved', 'subject_id' => $student->id]);
        $this->expectException(AuthorizationException::class);
        app(AccountAdministration::class)->approveInstructor($student, User::factory()->create());
    }

    public function test_account_suspension_rotates_remember_token_and_records_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = User::factory()->create();
        $oldToken = $student->remember_token;
        app(AccountAdministration::class)->setStatus($admin, $student, 'suspended');
        $this->assertNotSame($oldToken, $student->fresh()->remember_token);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'subject_id' => $student->id, 'action' => 'account.status_changed']);
    }

    public function test_order_ledger_displays_owned_financial_records_without_creation_actions(): void
    {
        config(['academy.payments_enabled' => true]);
        app()->instance(PaymentGateway::class, new FakeGateway);
        $order = app(CheckoutService::class)->create(User::factory()->create(), Course::factory()->create(), (string) Str::uuid());
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/orders')->assertOk()->assertSee($order->reference)->assertDontSee('Create order');
    }
}
