<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings;
use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Filament\Resources\PaymentSubmissions\Pages\ManagePaymentSubmissions;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Support\AcademySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    private function paidOrder(): array
    {
        $method = PaymentMethod::create(['name' => 'CBE', 'account_name' => 'Academy', 'account_number' => '100', 'active' => true]);
        $course = Course::factory()->create(['is_free' => false, 'price_minor' => 115000, 'currency' => 'ETB']);
        $learner = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($learner)->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'transaction_reference' => 'PAID1']);
        $this->actingAs($admin);
        Livewire::test(ManagePaymentSubmissions::class)->callTableAction('approve', PaymentSubmission::firstOrFail());

        return [$course, $learner, $admin, Order::firstOrFail()];
    }

    public function test_admin_settings_save_and_legal_pages_publish_admin_text(): void
    {
        $this->get('/privacy')->assertSee('Personal Data Protection Proclamation');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(Settings::class)
            ->set('settings.contact_email', 'hello@academy.test')->set('settings.contact_phone', '+251 911 000000')
            ->set('settings.privacy', "We keep your data safe.\n\nSecond paragraph.")
            ->call('save')->assertHasNoErrors();
        $this->get('/privacy')->assertOk()->assertSee('We keep your data safe.')->assertDontSee('Personal Data Protection Proclamation');
        $this->get('/terms')->assertSee('lifetime access');
        $this->get('/contact')->assertSee('hello@academy.test')->assertSee('+251 911 000000');
        Livewire::test(Settings::class)->set('settings.vat_percent', 90)->call('save')->assertHasErrors('settings.vat_percent');
        $this->actingAs(User::factory()->create(['role' => 'instructor']))->get('/admin/settings')->assertRedirect(route('instructor.dashboard'));
    }

    public function test_coupon_and_vat_apply_to_bank_checkout_and_are_recorded(): void
    {
        AcademySettings::save(['vat_enabled' => true, 'vat_percent' => 15]);
        $method = PaymentMethod::create(['name' => 'CBE', 'account_name' => 'Academy', 'account_number' => '100', 'active' => true]);
        $course = Course::factory()->create(['is_free' => false, 'price_minor' => 100000, 'currency' => 'ETB']);
        Coupon::create(['code' => 'SAVE20', 'percent' => 20, 'active' => true, 'max_uses' => 5, 'per_user_limit' => 1]);
        $learner = User::factory()->create();

        $this->actingAs($learner)->get(route('checkout.bank', [$course->slug, 'method' => $method->id, 'coupon' => 'save20']))
            ->assertOk()->assertSee('ETB 800.00')->assertSee('Includes VAT (15%)')->assertSee('ETB 104.35');
        $this->get(route('checkout.bank', [$course->slug, 'method' => $method->id, 'coupon' => 'NOPE']))->assertSee('This coupon is not valid.');

        $this->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'transaction_reference' => 'C1', 'coupon' => 'SAVE20'])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertSame([100000, 20000, 80000, 10435], [$order->subtotal_minor, $order->discount_minor, $order->total_minor, $order->vat_minor]);
        $this->assertDatabaseHas('coupon_redemptions', ['order_id' => $order->id, 'status' => 'reserved']);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(ManagePaymentSubmissions::class)->callTableAction('approve', PaymentSubmission::firstOrFail());
        $this->assertDatabaseHas('coupon_redemptions', ['order_id' => $order->id, 'status' => 'redeemed']);
    }

    public function test_paused_payments_block_new_submissions(): void
    {
        AcademySettings::save(['bank_checkout_enabled' => false]);
        $method = PaymentMethod::create(['name' => 'CBE', 'account_name' => 'Academy', 'account_number' => '100', 'active' => true]);
        $course = Course::factory()->create(['is_free' => false, 'price_minor' => 1000]);
        $this->actingAs(User::factory()->create())->get(route('checkout.bank', $course->slug))->assertOk()->assertSee('Payments are paused');
        $this->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'transaction_reference' => 'X1'])->assertStatus(503);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_learner_requests_refund_and_admin_approval_removes_access(): void
    {
        [$course, $learner, $admin, $order] = $this->paidOrder();
        $this->actingAs($learner)->get('/payments')->assertOk()->assertSee('Request a refund')->assertSee('ETB 1,150.00');
        $this->post(route('payments.refund', $order->reference), ['reason' => 'short'])->assertSessionHasErrors('reason');
        $this->post(route('payments.refund', $order->reference), ['reason' => 'The course was not what I expected.'])->assertSessionHasNoErrors();
        $this->assertSame('requested', $order->fresh()->refund_status);
        $this->post(route('payments.refund', $order->reference), ['reason' => 'Asking a second time please.'])->assertSessionHasErrors('reason');
        $this->actingAs(User::factory()->create())->post(route('payments.refund', $order->reference), ['reason' => 'Not my order at all.'])->assertNotFound();

        $this->actingAs($admin);
        Livewire::test(ManageOrders::class)->callTableAction('refund', $order, ['note' => 'Sent back to your CBE account.']);
        $this->assertSame(['refunded', 'refunded'], [$order->fresh()->status, $order->fresh()->refund_status]);
        $this->assertSame('refunded', Enrollment::where('user_id', $learner->id)->value('status'));
        $this->assertSame('refunded', $order->payment()->value('status'));
        $this->assertTrue($learner->notifications()->where('data->title', 'Refund approved')->exists());
    }

    public function test_admin_can_decline_a_refund_request(): void
    {
        [, $learner, $admin, $order] = $this->paidOrder();
        $this->actingAs($learner)->post(route('payments.refund', $order->reference), ['reason' => 'Changed my mind about this.']);
        $this->actingAs($admin);
        Livewire::test(ManageOrders::class)->callTableAction('decline', $order, ['note' => 'You completed most of the course.']);
        $this->assertSame(['paid', 'declined'], [$order->fresh()->status, $order->fresh()->refund_status]);
        $this->assertSame('active', Enrollment::where('user_id', $learner->id)->value('status'));
        $this->actingAs($learner)->get('/payments')->assertSee('Refund declined: You completed most of the course.')->assertDontSee('Request a refund');
    }
}
