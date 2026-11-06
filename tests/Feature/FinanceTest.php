<?php

namespace Tests\Feature;

use App\Filament\Pages\Finance;
use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Filament\Resources\PaymentSubmissions\Pages\ManagePaymentSubmissions;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Support\FinanceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_totals_breakdowns_and_accountant_csv(): void
    {
        $finance = User::factory()->create(['role' => 'admin', 'staff_role' => 'finance']);
        $cbe = PaymentMethod::create(['name' => 'CBE', 'account_name' => 'A', 'account_number' => '1', 'active' => true]);
        $tele = PaymentMethod::create(['name' => 'telebirr', 'account_name' => 'A', 'account_number' => '2', 'active' => true]);
        $editing = Course::factory()->create(['title' => 'Editing', 'is_free' => false, 'price_minor' => 100000]);
        $design = Course::factory()->create(['title' => 'Design', 'is_free' => false, 'price_minor' => 50000]);
        Coupon::create(['code' => 'HALF', 'percent' => 50, 'active' => true, 'max_uses' => 10, 'per_user_limit' => 1]);

        $buy = function (Course $course, PaymentMethod $method, string $ref, ?string $coupon = null) use ($finance): Order {
            $learner = User::factory()->create(['name' => $ref === 'X3' ? '=HYPERLINK("bad")' : 'Learner '.$ref]);
            $this->actingAs($learner)->post(route('checkout.bank.submit', $course->slug), array_filter(['payment_method_id' => $method->id, 'transaction_reference' => $ref, 'coupon' => $coupon]))->assertSessionHasNoErrors();
            $this->actingAs($finance);
            Livewire::test(ManagePaymentSubmissions::class)->callTableAction('approve', PaymentSubmission::where('transaction_reference', $ref)->firstOrFail());

            return PaymentSubmission::where('transaction_reference', $ref)->firstOrFail()->order;
        };
        $buy($editing, $cbe, 'X1');                 // 1,000.00
        $buy($editing, $tele, 'X2', 'HALF');        //   500.00 (500 discount)
        $refunded = $buy($design, $cbe, 'X3');      //   500.00, then refunded
        Livewire::test(ManageOrders::class)->callTableAction('refund', $refunded, ['note' => null]);
        // Waiting for approval
        $this->actingAs(User::factory()->create())->post(route('checkout.bank.submit', $design->slug), ['payment_method_id' => $cbe->id, 'transaction_reference' => 'X4']);

        $report = FinanceReport::forDays(now('Africa/Nairobi')->toDateString(), now('Africa/Nairobi')->toDateString());
        $totals = $report->totals();
        $this->assertSame([3, 200000, 50000, 150000, 50000, 50000, 1], [$totals['orders'], $totals['received'], $totals['refunded'], $totals['net'], $totals['discounts'], $totals['pending'], $totals['pending_count']]);
        $this->assertEquals(['Editing' => 150000, 'Design' => 50000], $report->byCourse()->pluck('received', 'course')->map(fn ($v) => (int) $v)->all());
        $this->assertEquals(['CBE' => 150000, 'telebirr' => 50000], $report->byBank()->pluck('received', 'bank')->map(fn ($v) => (int) $v)->all());
        $this->assertEquals(['HALF' => 1], $report->coupons()->pluck('uses', 'code')->map(fn ($v) => (int) $v)->all());

        $this->actingAs($finance)->get('/admin/finance')->assertOk()->assertSee('ETB 2,000.00')->assertSee('ETB 1,500.00')->assertSee('Download CSV for accountant');
        Livewire::test(Finance::class)->call('preset', 'last-month')->assertOk();

        $csv = $this->get(route('finance.export', ['from' => now('Africa/Nairobi')->toDateString(), 'to' => now('Africa/Nairobi')->toDateString()]))
            ->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringContainsString('Order reference', $csv);
        $this->assertStringContainsString('X2', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertCount(5, explode("\n", trim($csv))); // header + 4 orders

        $this->actingAs(User::factory()->create(['role' => 'admin', 'staff_role' => 'support']))->get('/admin/finance')->assertForbidden();
        $this->get(route('finance.export', ['from' => '2026-01-01', 'to' => '2026-12-31']))->assertForbidden();
    }
}
