<?php

namespace Tests\Feature;

use App\Filament\Pages\Analytics;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Enrollments\Pages\ManageEnrollments;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Support\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_change_content_revoke_access_and_export(): void
    {
        $student = User::factory()->create();
        $enrollment = Enrollment::factory()->create(['user_id' => $student->id]);
        $this->actingAs($student)->get('/admin/enrollments')->assertRedirect(route('dashboard'));
        $this->get('/academy-admin/export?from=2026-01-01&to=2026-12-31')->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/academy-admin')->assertRedirect('/admin/enrollments');
        $this->get('/admin/enrollments')->assertOk()->assertSee($student->name);

        Livewire::test(Settings::class)->set('content.banner', 'Welcome creators')->set('content.about', 'Our learning space')->call('save')->assertHasNoErrors();
        $this->get('/')->assertOk()->assertSee('Welcome creators');
        $this->get('/about')->assertSee('Our learning space');

        Livewire::test(ManageEnrollments::class)->callTableAction('revoke', $enrollment);
        $this->assertSame('revoked', $enrollment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'enrollment.revoked']);
        Livewire::test(ManageEnrollments::class)->callTableAction('restore', $enrollment);
        $this->assertSame('active', $enrollment->fresh()->status);
        $this->get('/admin/audit-logs')->assertOk()->assertSee('enrollment.revoked');

        $student->forceFill(['name' => '=HYPERLINK("bad")'])->save();
        $response = $this->get('/academy-admin/export?from=2026-01-01&to=2026-12-31')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }

    public function test_admin_can_enroll_a_learner_without_payment(): void
    {
        $learner = User::factory()->create();
        $course = Course::factory()->create(['is_free' => false, 'price_minor' => 50000]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(ManageEnrollments::class)->callAction('grant', ['user_id' => $learner->id, 'course_id' => $course->id])->assertHasNoActionErrors();
        $this->assertDatabaseHas('enrollments', ['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'active', 'source' => 'admin']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'enrollment.granted']);
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
        // robots.txt must not advertise where the admin panel is.
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /my-learning')->assertDontSee('Disallow: /admin'."\n", false);
        config(['academy.admin_path' => '/hub-k7m2x9/']);
        $this->assertSame(url('hub-k7m2x9/courses'), Admin::url('courses'));
        // An admin subdomain wins over the path, and website pages opened on it go back to the main site.
        config(['academy.admin_domain' => 'creativeadmin2026.creativelabet.com', 'app.url' => 'https://creativelabet.com']);
        $this->assertSame('https://creativeadmin2026.creativelabet.com/courses', Admin::url('courses'));
        $this->assertSame('', Admin::path());
        $this->get('http://creativeadmin2026.creativelabet.com/about')->assertRedirect('https://creativelabet.com/about');
        $this->get('http://creativelabet.com/about')->assertOk();
        $this->post('/locale', ['locale' => 'am'])->assertRedirect();
        $this->get('/')->assertSee('lang="am"', false);
        $this->post('/locale', ['locale' => 'invalid'])->assertSessionHasErrors('locale');
    }

    public function test_analytics_uses_inclusive_nairobi_day_boundaries(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Enrollment::factory()->create(['enrolled_at' => '2026-09-30 21:30:00']); // 00:30 on Oct 1 in Nairobi
        Enrollment::factory()->create(['enrolled_at' => '2026-10-01 21:00:00']); // 00:00 on Oct 2 in Nairobi
        $this->actingAs($admin);
        Livewire::test(Analytics::class)->set('from', '2026-10-01')->set('to', '2026-10-01')
            ->assertSee('peak 1 / day')->assertSeeHtml('<td>1</td>')
            ->set('to', 'not-a-date')->assertHasErrors('to');
        $this->get('/admin/analytics')->assertOk()->assertSee('Daily activity');
        $this->actingAs(User::factory()->create())->get('/admin/analytics')->assertRedirect(route('dashboard'));
    }
}
