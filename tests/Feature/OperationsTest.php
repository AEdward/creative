<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_change_content_revoke_access_and_export(): void
    {
        $student = User::factory()->create();
        $enrollment = Enrollment::factory()->create(['user_id' => $student->id]);
        $this->actingAs($student)->get('/academy-admin')->assertForbidden();
        $this->post('/academy-admin/settings', ['banner' => 'Welcome creators'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/academy-admin')->assertOk()->assertSee('Public site content');
        $this->actingAs($admin)->post('/academy-admin/settings', ['banner' => 'Welcome creators', 'about' => 'Our learning space', 'faq' => 'Ask us a question', 'contact_email' => 'help@example.test'])->assertRedirect();
        $this->get('/')->assertOk()->assertSee('Welcome creators');
        $this->post('/academy-admin/enrollments/'.$enrollment->id.'/revoke')->assertRedirect();
        $this->assertSame('revoked', $enrollment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'enrollment.revoked']);
        $student->forceFill(['name' => '=HYPERLINK("bad")'])->save();
        $response = $this->get('/academy-admin/export?from=2026-01-01&to=2026-12-31')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }

    public function test_coupon_discount_is_integer_and_limits_and_expiry_are_enforced(): void
    {
        config(['academy.payments_enabled' => true]);
        $this->app->instance(PaymentGateway::class, new FakeGateway);
        $coupon = Coupon::factory()->create(['code' => 'CREATE25', 'percent' => 25, 'max_uses' => 1]);
        $course = Course::factory()->create(['is_free' => false, 'price_minor' => 10001]);
        $order = app(CheckoutService::class)->create(User::factory()->create(), $course, (string) Str::uuid(), 'create25');
        $this->assertSame(2500, $order->discount_minor);
        $this->assertSame(7501, $order->total_minor);
        $this->assertSame(7501, $order->payment->amount_minor);
        try {
            app(CheckoutService::class)->create(User::factory()->create(), $course, (string) Str::uuid(), 'CREATE25');
            $this->fail('Coupon limit ignored');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 1);
        }
        $coupon->update(['max_uses' => 10, 'expires_at' => now()->subMinute()]);
        try {
            app(CheckoutService::class)->create(User::factory()->create(), $course, (string) Str::uuid(), 'CREATE25');
            $this->fail('Expired coupon accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 1);
        }
    }

    public function test_maintenance_expires_only_stale_unpaid_orders(): void
    {
        config(['academy.payments_enabled' => true]);
        $this->app->instance(PaymentGateway::class, new FakeGateway);
        $old = app(CheckoutService::class)->create(User::factory()->create(), Course::factory()->create(), (string) Str::uuid());
        $old->forceFill(['created_at' => now()->subDays(2)])->save();
        $this->artisan('academy:expire-orders')->assertSuccessful();
        $this->assertSame('expired', $old->fresh()->status);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_sitemap_omits_private_content_and_locale_is_validated(): void
    {
        $visible = Course::factory()->create(['slug' => 'visible-course']);
        Course::factory()->create(['slug' => 'secret-draft', 'status' => 'draft']);
        $this->get('/sitemap.xml')->assertOk()->assertSee('visible-course')->assertDontSee('secret-draft')->assertDontSee('/admin');
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin');
        $this->post('/locale', ['locale' => 'am'])->assertRedirect();
        $this->get('/')->assertSee('lang="am"', false);
        $this->post('/locale', ['locale' => 'invalid'])->assertSessionHasErrors('locale');
    }
}
