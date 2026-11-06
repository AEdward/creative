<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Filament\Resources\PaymentSubmissions\Pages\ManagePaymentSubmissions;
use App\Models\Conversation;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PaymentMethod;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Notifications\EngagementNotification;
use App\Providers\AppServiceProvider;
use App\Services\CouponService;
use App\Services\Payments\BankTransferService;
use App\Services\Payments\WebhookSignature;
use App\Support\BackgroundHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private function setUpShop(): array
    {
        $method = PaymentMethod::create(['name' => 'CBE', 'account_name' => 'Academy', 'account_number' => '100', 'active' => true]);
        $course = Course::factory()->create(['is_free' => false, 'price_minor' => 50000, 'currency' => 'ETB']);

        return [$method, $course, User::factory()->create(), User::factory()->create(['role' => 'admin'])];
    }

    private function pay(User $learner, Course $course, PaymentMethod $method, string $ref): PaymentSubmission
    {
        $this->actingAs($learner)->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'transaction_reference' => $ref])->assertSessionHasNoErrors();

        return PaymentSubmission::where('transaction_reference', $ref)->firstOrFail();
    }

    /** ARCH-001 + QA-001 */
    public function test_refunded_learner_can_buy_again_and_gets_access_back(): void
    {
        [$method, $course, $learner, $admin] = $this->setUpShop();
        $first = $this->pay($learner, $course, $method, 'BUY1');
        $this->actingAs($admin);
        Livewire::test(ManagePaymentSubmissions::class)->callTableAction('approve', $first);
        Livewire::test(ManageOrders::class)->callTableAction('refund', $first->order, ['note' => null]);
        $this->assertSame('refunded', Enrollment::where('user_id', $learner->id)->value('status'));

        $this->actingAs($learner)->get(route('checkout.bank', $course->slug))->assertOk()->assertSee('Choose Payment Method');
        $second = $this->pay($learner, $course, $method, 'BUY2');
        $this->actingAs($admin);
        Livewire::test(ManagePaymentSubmissions::class)->callTableAction('approve', $second);
        $this->assertSame(1, Enrollment::count());
        $this->assertSame('active', Enrollment::value('status'));
        $this->actingAs($learner)->get(route('checkout.bank', $course->slug))->assertRedirect(route('learning.index'));
    }

    /** ARCH-001 + QA-001: an admin removal still blocks buying, with an honest message instead of a payment form. */
    public function test_admin_revoked_learner_is_told_to_contact_support_and_cannot_submit(): void
    {
        [$method, $course, $learner] = $this->setUpShop();
        Enrollment::factory()->create(['user_id' => $learner->id, 'course_id' => $course->id, 'status' => 'revoked']);
        $this->actingAs($learner)->get(route('checkout.bank', $course->slug))->assertOk()->assertSee('Please contact support first')->assertDontSee('Choose Payment Method');
        $this->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'transaction_reference' => 'X9'])
            ->assertSessionHasErrors(['course' => 'Your access to this course was removed by an admin. Please message support before paying again.']);
        $this->assertDatabaseCount('payment_submissions', 0);
    }

    /** QA-003 */
    public function test_admin_can_reopen_a_mistaken_rejection_and_approve_it(): void
    {
        [$method, $course, $learner, $admin] = $this->setUpShop();
        Coupon::create(['code' => 'HALF', 'percent' => 50, 'active' => true, 'max_uses' => 1, 'per_user_limit' => 1]);
        $this->actingAs($learner)->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'transaction_reference' => 'REAL1', 'coupon' => 'HALF']);
        $submission = PaymentSubmission::firstOrFail();
        $this->actingAs($admin);
        Livewire::test(ManagePaymentSubmissions::class)->callTableAction('reject', $submission, ['reason' => 'Not received yet']);
        $this->assertDatabaseHas('coupon_redemptions', ['order_id' => $submission->order_id, 'status' => 'released']);

        Livewire::test(ManagePaymentSubmissions::class)->filterTable('status', 'rejected')->assertTableActionVisible('reopen', $submission)->callTableAction('reopen', $submission);
        $this->assertSame(['pending', 'awaiting_payment'], [$submission->fresh()->status, $submission->order->fresh()->status]);
        $this->assertDatabaseHas('coupon_redemptions', ['order_id' => $submission->order_id, 'status' => 'reserved']);
        Livewire::test(ManagePaymentSubmissions::class)->callTableAction('approve', $submission);
        $this->assertSame('active', Enrollment::where('user_id', $learner->id)->value('status'));
        $this->assertDatabaseHas('coupon_redemptions', ['order_id' => $submission->order_id, 'status' => 'redeemed']);
        $this->assertThrows(fn () => app(BankTransferService::class)->reopen($submission->fresh(), $admin), ValidationException::class);
    }

    /** QA-002 + ARCH-002 */
    public function test_longest_allowed_coupon_code_works_end_to_end(): void
    {
        [$method, $course, $learner, $admin] = $this->setUpShop();
        $code = str_repeat('A', CouponService::MAX_CODE_LENGTH);
        $this->actingAs($admin)->post('/academy-admin/coupons', ['code' => $code, 'percent' => 10, 'max_uses' => 5, 'per_user_limit' => 1])->assertSessionHasNoErrors();
        $this->post('/academy-admin/coupons', ['code' => $code.'B', 'percent' => 10, 'max_uses' => 5, 'per_user_limit' => 1])->assertSessionHasErrors('code');

        $this->actingAs($learner)->get(route('checkout.bank', [$course->slug, 'method' => $method->id, 'coupon' => strtolower($code)]))->assertSee('Coupon applied.');
        $this->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'transaction_reference' => 'LONG1', 'coupon' => $code])->assertSessionHasNoErrors();
        $this->assertSame(45000, PaymentSubmission::firstOrFail()->order->total_minor);
    }

    /** SEC-001 */
    public function test_notification_links_never_redirect_off_site(): void
    {
        $user = User::factory()->create();
        foreach (['/\\evil.test' => false, '//evil.test' => false, 'https://evil.test/x' => false, "/ok\x01" => false, 'javascript:alert(1)' => false, '/my-learning' => true, url('/payments') => true] as $url => $allowed) {
            $user->notifications()->delete();
            $user->notify(new EngagementNotification(['title' => 'T', 'body' => 'B', 'url' => $url]));
            $id = $user->notifications()->value('id');
            $response = $this->actingAs($user)->get(route('notifications.open', $id));
            $response->assertRedirect($allowed ? $url : route('notifications.index'));
        }
    }

    /** SEC-002 */
    public function test_pdf_proofs_download_and_all_proofs_are_sandboxed(): void
    {
        Storage::fake('local');
        [$method, $course, $learner] = $this->setUpShop();
        $this->actingAs($learner)->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'proof' => UploadedFile::fake()->createWithContent('r.pdf', '%PDF-1.4
1 0 obj<<>>endobj
trailer<<>>
%%EOF
')]);
        $response = $this->get(route('payment-proofs.show', PaymentSubmission::firstOrFail()))->assertOk();
        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
    }

    /** SEC-003 */
    public function test_production_forces_debug_off_and_secure_cookies(): void
    {
        config(['app.debug' => true, 'session.secure' => false]);
        $this->app['env'] = 'production';
        (new AppServiceProvider($this->app))->boot();
        $this->assertFalse(config('app.debug'));
        $this->assertTrue(config('session.secure'));
    }

    /** SEC-004b */
    public function test_webhook_signature_rejects_unsigned_tampered_and_stale_callbacks(): void
    {
        $body = '{"order":"x","amount":100}';
        $now = time();
        WebhookSignature::verify('secret', $body, WebhookSignature::sign('secret', $body, $now), (string) $now);
        foreach ([[null, (string) $now], ['bad', (string) $now], [WebhookSignature::sign('secret', $body.' ', $now), (string) $now], [WebhookSignature::sign('secret', $body, $now - 900), (string) ($now - 900)]] as [$sig, $ts]) {
            $this->assertThrows(fn () => WebhookSignature::verify('secret', $body, $sig, $ts), ValidationException::class);
        }
        $this->assertThrows(fn () => WebhookSignature::verify('', $body, 'x', (string) $now), ValidationException::class);
    }

    /** ARCH-004 */
    public function test_dashboard_warns_when_scheduler_or_queue_is_stuck(): void
    {
        $this->assertStringContainsString('never run', BackgroundHealth::problems()[0]);
        BackgroundHealth::beat();
        config(['queue.default' => 'database']);
        $this->assertSame([], BackgroundHealth::problems());
        Cache::forever(BackgroundHealth::HEARTBEAT_KEY, now()->subMinutes(30)->timestamp);
        \DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subHour()->timestamp, 'created_at' => now()->subHour()->timestamp]);
        $this->assertCount(2, BackgroundHealth::problems());
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin')->assertOk()->assertSee('queued job(s) have waited over 15 minutes');
    }

    public function test_notifications_are_emailed_unless_the_learner_switches_email_off(): void
    {
        $learner = User::factory()->create();
        $note = new EngagementNotification(['title' => 'Payment approved', 'body' => 'You now have access.', 'url' => '/my-learning']);
        $this->assertSame(['database', 'mail'], $note->via($learner));
        $mail = $note->toMail($learner);
        $this->assertSame('Payment approved', $mail->subject);
        $this->assertSame(url('/my-learning'), $mail->actionUrl);
        $this->assertSame(route('notifications.index'), (new EngagementNotification(['title' => 'x', 'url' => '//evil.test']))->toMail($learner)->actionUrl);

        $this->actingAs($learner)->post(route('notifications.preferences'), ['announcements' => 1, 'support' => 1, 'learning' => 1, 'email' => 0])->assertSessionHasNoErrors();
        $this->assertSame(['database'], $note->via($learner));
        $this->assertSame(['database'], $note->via(User::factory()->unverified()->create()));
    }

    /** FE-004 + FE-006 */
    public function test_lesson_list_announces_completion_and_sidebar_counts_unread_in_sql(): void
    {
        $learner = User::factory()->create();
        $course = Course::factory()->create(['is_free' => true, 'price_minor' => 0]);
        $lesson = $course->modules()->create(['title' => 'M'])->lessons()->create(['title' => 'Intro', 'type' => 'text', 'body' => 'x', 'published' => true]);
        Enrollment::factory()->create(['user_id' => $learner->id, 'course_id' => $course->id]);
        $this->actingAs($learner)->get(route('learning.show', [$course->slug, $lesson->id]))->assertOk()
            ->assertSee('Not completed:')->assertSee('aria-label="Lesson navigation"', false);

        $conversation = Conversation::create(['student_id' => $learner->id, 'type' => 'support', 'subject' => 'Help', 'last_message_at' => now()]);
        $this->assertSame(1, Conversation::visibleTo($learner)->unreadFor($learner)->count());
        $conversation->update(['student_read_at' => now()->addSecond()]);
        $this->assertSame(0, Conversation::visibleTo($learner)->unreadFor($learner)->count());
    }
}
