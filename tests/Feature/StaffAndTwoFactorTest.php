<?php

namespace Tests\Feature;

use App\Filament\Pages\Staff;
use App\Models\Course;
use App\Models\PaymentMethod;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Notifications\AccountInvitation;
use App\Services\AccountAdministration;
use App\Services\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class StaffAndTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        return User::factory()->create(['role' => 'admin', 'staff_role' => $role]);
    }

    public function test_each_staff_role_only_opens_its_own_area(): void
    {
        $pages = [
            'content' => ['/admin/courses', '/admin/media-library', '/admin/course-studio', '/admin/categories'],
            'support' => ['/admin/support-tickets', '/admin/reviews', '/admin/contact-messages'],
            'finance' => ['/admin/payment-submissions', '/admin/orders', '/admin/payment-methods', '/admin/coupons'],
            'super' => ['/admin/staff', '/admin/settings', '/admin/audit-logs', '/admin/users'],
        ];
        foreach (['content', 'support', 'finance'] as $role) {
            $user = $this->staff($role);
            $this->actingAs($user)->get('/admin')->assertOk();
            $this->get('/admin/analytics')->assertOk();
            foreach ($pages as $area => $urls) {
                foreach ($urls as $url) {
                    $area === $role ? $this->get($url)->assertOk() : $this->get($url)->assertForbidden();
                }
            }
        }
        $this->actingAs($this->staff('support'))->get('/admin/enrollments')->assertOk();
        $this->actingAs($this->staff('finance'))->get('/admin/enrollments')->assertOk();
        $this->actingAs($this->staff('content'))->get('/admin/enrollments')->assertForbidden();
        foreach (array_merge(...array_values($pages)) as $url) {
            $this->actingAs($this->staff('super'))->get($url)->assertOk();
        }
    }

    public function test_payment_alerts_go_to_finance_and_receipts_are_finance_only(): void
    {
        $finance = $this->staff('finance');
        $support = $this->staff('support');
        $method = PaymentMethod::create(['name' => 'CBE', 'account_name' => 'A', 'account_number' => '1', 'active' => true]);
        $course = Course::factory()->create(['is_free' => false, 'price_minor' => 1000]);
        $this->actingAs(User::factory()->create())->post(route('checkout.bank.submit', $course->slug), ['payment_method_id' => $method->id, 'transaction_reference' => 'T1']);
        $this->assertSame(1, $finance->notifications()->count());
        $this->assertSame(0, $support->notifications()->count());
        $submission = PaymentSubmission::firstOrFail();
        $submission->update(['proof_path' => null]);
        $this->actingAs($support)->get(route('payment-proofs.show', $submission))->assertForbidden();
    }

    public function test_super_admin_manages_staff_and_the_last_super_admin_is_protected(): void
    {
        Notification::fake();
        $super = $this->staff('super');
        $this->actingAs($super);
        Livewire::test(Staff::class)->callAction('newStaff', ['name' => 'Fana Finance', 'email' => 'fana@academy.test', 'staff_role' => 'finance'])->assertHasNoActionErrors();
        $fana = User::where('email', 'fana@academy.test')->firstOrFail();
        $this->assertSame(['admin', 'finance'], [$fana->role, $fana->staff_role]);
        Notification::assertSentTo($fana, AccountInvitation::class);

        Livewire::test(Staff::class)->callTableAction('changeRole', $fana, ['staff_role' => 'support']);
        $this->assertSame('support', $fana->fresh()->staff_role);
        Livewire::test(Staff::class)->callTableAction('removeStaff', $fana);
        $this->assertSame(['student', null], [$fana->fresh()->role, $fana->fresh()->staff_role]);

        // The only super admin can't be demoted (by another super admin removing them).
        $other = $this->staff('super');
        $this->actingAs($other);
        Livewire::test(Staff::class)->callTableAction('changeRole', $super, ['staff_role' => 'content']);
        $this->assertSame('content', $super->fresh()->staff_role);
        $this->assertThrows(fn () => app(AccountAdministration::class)->setRole($super->fresh(), $other, 'admin', 'content'));
        $this->assertSame('super', $other->fresh()->staff_role);

        $this->actingAs($this->staff('content'))->get('/admin/staff')->assertForbidden();
    }

    public function test_staff_must_set_up_and_then_pass_two_step_sign_in(): void
    {
        config(['academy.require_two_factor' => true]);
        $admin = $this->staff('super');
        $this->actingAs($admin)->get('/admin/courses')->assertRedirect(route('two-factor.setup'));
        $this->get(route('two-factor.setup'))->assertOk()->assertSee('data:image', false);
        $secret = session('two_factor_setup_secret');

        $this->post(route('two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post(route('two-factor.confirm'), ['code' => $code])->assertOk()->assertSee('Save your recovery codes');
        $this->assertNotNull($admin->fresh()->two_factor_confirmed_at);
        $this->assertNotSame($secret, $admin->fresh()->getRawOriginal('two_factor_secret')); // stored encrypted
        $this->get('/admin/courses')->assertOk();

        // A new session must enter a code; a recovery code works once.
        $this->flushSession();
        $this->actingAs($admin->fresh())->get('/admin')->assertRedirect(route('two-factor.challenge'));
        $this->post(route('two-factor.verify'), ['code' => '123456'])->assertSessionHasErrors('code');
        $recovery = $admin->fresh()->two_factor_recovery_codes[0];
        $this->post(route('two-factor.verify'), ['code' => $recovery])->assertRedirect();
        $this->get('/admin')->assertOk();
        $this->assertNotContains($recovery, $admin->fresh()->two_factor_recovery_codes);

        // Learners are never asked.
        $this->flushSession();
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();

        // A super admin can reset a lost phone.
        app(TwoFactor::class)->reset($this->staff('super'), $admin->fresh());
        $this->assertNull($admin->fresh()->two_factor_confirmed_at);
    }
}
