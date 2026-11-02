<?php

namespace Tests\Feature;

use App\Filament\Resources\PaymentSubmissions\Pages\ManagePaymentSubmissions;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BankTransferCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function setUpCheckout(): array
    {
        $method = PaymentMethod::create(['name' => 'Commercial Bank of Ethiopia', 'account_name' => 'Creative Lab Academy', 'account_number' => '1000123456789', 'active' => true]);
        $course = Course::factory()->create(['is_free' => false, 'price_minor' => 120000, 'currency' => 'ETB', 'title' => 'Video Editing Masterclass']);

        return [$method, $course, User::factory()->create()];
    }

    public function test_learner_chooses_a_bank_sees_account_details_and_submits_proof(): void
    {
        Storage::fake('local');
        [$method, $course, $learner] = $this->setUpCheckout();
        PaymentMethod::create(['name' => 'Hidden bank', 'account_name' => 'x', 'account_number' => '9', 'active' => false]);

        $this->actingAs($learner)->get(route('checkout.bank', $course->slug))->assertOk()->assertSee('Commercial Bank of Ethiopia')->assertDontSee('Hidden bank');
        $this->get(route('checkout.bank', [$course->slug, 'method' => $method->id]))->assertOk()->assertSee('1000123456789')->assertSee('Creative Lab Academy');

        $this->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'transaction_reference' => 'ft24312abc', 'proof' => UploadedFile::fake()->image('receipt.png')])->assertRedirect(route('checkout.bank', $course->slug));
        $submission = PaymentSubmission::firstOrFail();
        $this->assertSame('FT24312ABC', $submission->transaction_reference);
        Storage::disk('local')->assertExists($submission->proof_path);
        $this->assertSame('awaiting_payment', $submission->order->status);
        $this->assertDatabaseCount('enrollments', 0);

        $this->get(route('checkout.bank', $course->slug))->assertSee('Waiting for confirmation');
        $this->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'transaction_reference' => 'OTHER1'])->assertSessionHasErrors('course');
    }

    public function test_submission_needs_a_reference_or_screenshot_and_references_cannot_be_reused(): void
    {
        [$method, $course, $learner] = $this->setUpCheckout();
        $this->actingAs($learner)->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id])->assertSessionHasErrors('transaction_reference');
        $this->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'transaction_reference' => 'DUP123'])->assertSessionHasNoErrors();

        $other = User::factory()->create();
        $this->actingAs($other)->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'transaction_reference' => 'dup123'])->assertSessionHasErrors('transaction_reference');
        $this->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'transaction_reference' => 'X', 'proof' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('proof');
    }

    public function test_admin_approval_enrolls_once_and_rejection_lets_the_learner_retry(): void
    {
        [$method, $course, $learner] = $this->setUpCheckout();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($learner)->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'transaction_reference' => 'FIRST1']);
        $first = PaymentSubmission::firstOrFail();

        $this->actingAs($admin);
        Livewire::test(ManagePaymentSubmissions::class)->callTableAction('reject', $first, ['reason' => 'Amount not received']);
        $this->assertSame('rejected', $first->fresh()->status);
        $this->assertSame('failed', $first->order->fresh()->status);
        $this->assertDatabaseCount('enrollments', 0);

        $this->actingAs($learner)->get(route('checkout.bank', $course->slug))->assertSee('Amount not received');
        $this->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'transaction_reference' => 'SECOND2'])->assertSessionHasNoErrors();
        $second = PaymentSubmission::where('transaction_reference', 'SECOND2')->firstOrFail();

        $this->actingAs($admin);
        Livewire::test(ManagePaymentSubmissions::class)->callTableAction('approve', $second);
        $this->assertSame('paid', $second->order->fresh()->status);
        $this->assertDatabaseHas('enrollments', ['user_id' => $learner->id, 'course_id' => $course->id, 'source' => 'bank_transfer', 'status' => 'active']);
        $this->assertTrue($learner->notifications()->where('data->title', 'Payment approved')->exists());
        $this->assertThrows(fn () => app(\App\Services\Payments\BankTransferService::class)->approve($second, $admin), \Illuminate\Validation\ValidationException::class);
        $this->assertSame(1, Enrollment::count());

        $this->actingAs($learner)->get(route('checkout.bank', $course->slug))->assertRedirect(route('learning.index'));
    }

    public function test_proofs_are_private_and_admin_pages_are_admin_only(): void
    {
        Storage::fake('local');
        [$method, $course, $learner] = $this->setUpCheckout();
        $this->actingAs($learner)->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'proof' => UploadedFile::fake()->image('r.png')]);
        $submission = PaymentSubmission::firstOrFail();

        $this->get(route('payment-proofs.show', $submission))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('payment-proofs.show', $submission))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'instructor']))->get('/admin/payment-submissions')->assertRedirect(route('dashboard'));
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/payment-submissions')->assertOk();
        $this->get('/admin/payment-methods')->assertOk()->assertSee('Commercial Bank of Ethiopia');
        $this->get(route('payment-proofs.show', $submission))->assertOk();
    }

    public function test_orders_waiting_for_review_do_not_expire(): void
    {
        [$method, $course, $learner] = $this->setUpCheckout();
        $this->actingAs($learner)->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'transaction_reference' => 'WAIT1']);
        Order::query()->update(['created_at' => now()->subDays(3)]);
        $this->artisan('academy:expire-orders')->assertSuccessful();
        $this->assertSame('awaiting_payment', Order::first()->status);
    }

    public function test_free_courses_and_enrolled_learners_skip_checkout(): void
    {
        [, $course, $learner] = $this->setUpCheckout();
        $free = Course::factory()->create(['is_free' => true, 'price_minor' => 0]);
        $this->actingAs($learner)->get(route('checkout.bank', $free->slug))->assertRedirect(route('courses.show', $free->slug));
        Enrollment::factory()->create(['user_id' => $learner->id, 'course_id' => $course->id]);
        $this->get(route('checkout.bank', $course->slug))->assertRedirect(route('learning.index'));
    }
}
