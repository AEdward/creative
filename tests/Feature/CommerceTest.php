<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\CheckoutService;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommerceTest extends TestCase
{
    use RefreshDatabase;

    private function checkout(?User $user = null, ?Course $course = null, ?string $key = null): Order
    {
        config(['academy.payments_enabled' => true]);
        app()->instance(PaymentGateway::class, new FakeGateway);

        return app(CheckoutService::class)->create($user ?? User::factory()->create(), $course ?? Course::factory()->create(['is_free' => false, 'price_minor' => 120050]), $key ?? (string) Str::uuid());
    }

    private function event(Order $order, array $overrides = []): array
    {
        return array_merge(['event_id' => (string) Str::uuid(), 'order_reference' => $order->reference, 'provider_reference' => $order->payment->provider_reference, 'amount_minor' => $order->total_minor, 'currency' => $order->currency, 'success' => true], $overrides);
    }

    public function test_real_checkout_is_disabled_without_a_verified_gateway(): void
    {
        $course = Course::factory()->create(['is_free' => false]);
        $this->actingAs(User::factory()->create())->postJson('/checkout/'.$course->slug, ['idempotency_key' => (string) Str::uuid()])->assertServiceUnavailable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_uses_integer_price_snapshots_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['is_free' => false, 'price_minor' => 120050]);
        $key = (string) Str::uuid();
        $first = $this->checkout($user, $course, $key);
        $second = $this->checkout($user, $course, $key);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(120050, $first->total_minor);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_verified_success_grants_enrollment_once_even_when_event_is_replayed(): void
    {
        $order = $this->checkout();
        $event = $this->event($order);
        app(PaymentProcessor::class)->handle(new FakeGateway, $event);
        app(PaymentProcessor::class)->handle(new FakeGateway, $event);
        app(PaymentProcessor::class)->handle(new FakeGateway, $this->event($order));
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('verified_success', $order->payment->fresh()->status);
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('payment_events', 2);
    }

    public function test_wrong_amount_currency_or_provider_reference_never_grants_access(): void
    {
        foreach ([['amount_minor' => 1], ['currency' => 'USD'], ['provider_reference' => 'wrong']] as $override) {
            $order = $this->checkout();
            try {
                app(PaymentProcessor::class)->handle(new FakeGateway, $this->event($order, $override));
                $this->fail('Invalid receipt accepted');
            } catch (ValidationException) {
                $this->assertSame('awaiting_payment', $order->fresh()->status);
            }
        }
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_failure_does_not_enroll_and_late_failure_does_not_downgrade_paid_order(): void
    {
        $failed = $this->checkout();
        app(PaymentProcessor::class)->handle(new FakeGateway, $this->event($failed, ['success' => false]));
        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertDatabaseCount('enrollments', 0);
        $paid = $this->checkout();
        app(PaymentProcessor::class)->handle(new FakeGateway, $this->event($paid));
        app(PaymentProcessor::class)->handle(new FakeGateway, $this->event($paid, ['success' => false]));
        $this->assertSame('paid', $paid->fresh()->status);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_reusing_event_identity_with_different_content_is_rejected(): void
    {
        $order = $this->checkout();
        $event = $this->event($order);
        app(PaymentProcessor::class)->handle(new FakeGateway, $event);
        $this->expectException(ValidationException::class);
        app(PaymentProcessor::class)->handle(new FakeGateway, array_merge($event, ['success' => false]));
    }

    public function test_fake_gateway_cannot_verify_payments_in_production(): void
    {
        $order = $this->checkout();
        $event = $this->event($order);
        app()->instance('env', 'production');
        $this->expectException(\LogicException::class);
        (new FakeGateway)->verify($event);
    }

    public function test_payment_history_is_scoped_to_its_owner(): void
    {
        $owner = User::factory()->create();
        $order = $this->checkout($owner);
        $this->actingAs($owner)->get('/payments')->assertOk()->assertSee($order->reference);
        $this->actingAs(User::factory()->create())->get('/payments')->assertOk()->assertDontSee($order->reference);
    }

    public function test_existing_revoked_membership_cannot_be_bypassed_by_starting_checkout(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id, 'status' => 'revoked']);
        $this->expectException(ValidationException::class);
        $this->checkout($user, $course);
    }

    public function test_opaque_provider_event_ids_preserve_case(): void
    {
        $order = $this->checkout();
        app(PaymentProcessor::class)->handle(new FakeGateway, $this->event($order, ['event_id' => 'CaseSensitiveEvent']));
        app(PaymentProcessor::class)->handle(new FakeGateway, $this->event($order, ['event_id' => 'casesensitiveevent']));
        $this->assertDatabaseCount('payment_events', 2);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_terminal_failure_is_not_automatically_overridden_by_conflicting_success(): void
    {
        $order = $this->checkout();
        app(PaymentProcessor::class)->handle(new FakeGateway, $this->event($order, ['success' => false]));
        try {
            app(PaymentProcessor::class)->handle(new FakeGateway, $this->event($order, ['success' => true]));
            $this->fail('Terminal failure was overridden');
        } catch (ValidationException) {
            $this->assertSame('failed', $order->fresh()->status);
            $this->assertDatabaseCount('enrollments', 0);
        }
    }

    public function test_another_checkout_key_is_rejected_while_a_payment_is_pending(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['is_free' => false]);
        $first = $this->checkout($user, $course);
        try {
            $this->checkout($user, $course);
            $this->fail('Another key was silently accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 1);
        }
    }

    public function test_enrollment_created_during_checkout_prevents_a_second_order(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['is_free' => false]);
        $injected = false;
        DB::listen(function ($query) use ($user, $course, &$injected): void {
            if (! $injected && str_contains($query->sql, 'exists') && str_contains($query->sql, 'enrollments')) {
                $injected = true;
                Enrollment::factory()->create(['user_id' => $user->id, 'course_id' => $course->id]);
            }
        });
        try {
            $this->checkout($user, $course);
            $this->fail('Checkout must recheck enrollment under the shared account lock.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('enrollments', 1);
        }
    }
}
