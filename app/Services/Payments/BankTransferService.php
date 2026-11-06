<?php

namespace App\Services\Payments;

use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Notifications\EngagementNotification;
use App\Services\CouponService;
use App\Support\AcademySettings;
use App\Support\Admin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Manual bank-transfer checkout: the learner pays outside the site, submits a transaction ID and/or
 * screenshot, and an admin approves (paid + enrollment) or rejects it. Access is never granted on submission.
 */
class BankTransferService
{
    public function pendingSubmission(User $user, Course $course): ?PaymentSubmission
    {
        return PaymentSubmission::where('user_id', $user->id)->where('status', 'pending')
            ->whereHas('order.items', fn ($q) => $q->where('course_id', $course->id))->latest()->first();
    }

    public function submit(User $user, Course $course, PaymentMethod $method, ?string $reference, ?UploadedFile $proof, ?string $coupon = null): PaymentSubmission
    {
        abort_unless(AcademySettings::get('bank_checkout_enabled'), 503, 'Payments are paused right now.');
        abort_unless($user->status === 'active' && $user->hasVerifiedEmail(), 403);
        abort_unless(Course::published()->whereKey($course->id)->exists() && ! $course->is_free && $course->price_minor > 0, 404);
        abort_unless($method->active, 422, 'This payment method is no longer available.');
        $reference = filled($reference) ? Str::upper(trim($reference)) : null;
        if (! $reference && ! $proof) {
            throw ValidationException::withMessages(['transaction_reference' => 'Enter the transaction ID or upload a screenshot of the receipt.']);
        }
        if ($reference && PaymentSubmission::where('payment_method_id', $method->id)->where('transaction_reference', $reference)->exists()) {
            throw ValidationException::withMessages(['transaction_reference' => 'This transaction ID has already been submitted.']);
        }

        $path = $proof?->store('payment-proofs', 'local');
        try {
            $submission = DB::transaction(function () use ($user, $course, $method, $reference, $path, $coupon) {
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $existing = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->first();
                if ($existing?->status === 'revoked') {
                    throw ValidationException::withMessages(['course' => 'Your access to this course was removed by an admin. Please message support before paying again.']);
                }
                if ($existing && $existing->status === 'active' && ! $existing->expires_at?->isPast()) {
                    throw ValidationException::withMessages(['course' => 'You already have access to this course.']);
                }
                if ($this->pendingSubmission($user, $course)) {
                    throw ValidationException::withMessages(['course' => 'Your payment for this course is already waiting for review.']);
                }
                $order = Order::create(['user_id' => $user->id, 'reference' => (string) Str::uuid(), 'checkout_key' => hash('sha256', $user->id.'|bank|'.Str::uuid()), 'currency' => $course->currency, 'subtotal_minor' => $course->price_minor, 'discount_minor' => 0, 'total_minor' => $course->price_minor, 'status' => 'awaiting_payment']);
                $order->items()->create(['course_id' => $course->id, 'title_snapshot' => $course->title, 'unit_price_minor' => $course->price_minor]);
                if (filled($coupon)) {
                    app(CouponService::class)->reserve($order, $coupon);
                    $order->refresh();
                }
                $order->update(['vat_minor' => AcademySettings::vatIncluded($order->total_minor), 'vat_percent' => AcademySettings::get('vat_enabled') ? (float) AcademySettings::get('vat_percent') : 0]);
                Payment::create(['order_id' => $order->id, 'provider' => 'bank_transfer', 'idempotency_key' => (string) Str::uuid(), 'amount_minor' => $order->total_minor, 'currency' => $order->currency, 'status' => 'pending']);

                return PaymentSubmission::create(['order_id' => $order->id, 'user_id' => $user->id, 'payment_method_id' => $method->id, 'transaction_reference' => $reference, 'proof_path' => $path]);
            }, 3);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }

        $admins = User::staffFor('finance');
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new EngagementNotification(['title' => __('Payment to review'), 'body' => $user->name.' · '.$course->title.' · '.$method->name, 'url' => Admin::url('payment-submissions')]));
        }

        return $submission;
    }

    public function approve(PaymentSubmission $submission, User $admin): void
    {
        DB::transaction(function () use ($submission, $admin) {
            $submission = PaymentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($submission->order_id)->lockForUpdate()->with('items')->firstOrFail();
            if ($submission->status !== 'pending' || $order->status !== 'awaiting_payment') {
                throw ValidationException::withMessages(['submission' => 'This payment has already been reviewed.']);
            }
            foreach ($order->items as $item) {
                $existing = Enrollment::where('user_id', $order->user_id)->where('course_id', $item->course_id)->first();
                if ($existing?->status === 'revoked') {
                    throw ValidationException::withMessages(['submission' => 'An admin removed this learner’s access to the course. Restore or review it before approving a new payment.']);
                }
                if ($existing && $existing->status === 'active' && ! $existing->expires_at?->isPast()) {
                    continue; // already has access; the payment is still recorded
                }
                // New learner, or one coming back after a refund or expiry: (re)open access from this order.
                Enrollment::updateOrCreate(['user_id' => $order->user_id, 'course_id' => $item->course_id], ['source_order_item_id' => $item->id, 'source' => 'bank_transfer', 'status' => 'active', 'enrolled_at' => now(), 'expires_at' => null]);
            }
            Payment::where('order_id', $order->id)->update(['status' => 'verified_success', 'verified_at' => now(), 'provider_reference' => $submission->transaction_reference ? $submission->payment_method_id.':'.$submission->transaction_reference : null]);
            $order->update(['status' => 'paid']);
            CouponRedemption::where('order_id', $order->id)->where('status', 'reserved')->update(['status' => 'redeemed']);
            $submission->update(['status' => 'approved', 'reviewer_id' => $admin->id, 'reviewed_at' => now()]);
            AuditLog::create(['actor_id' => $admin->id, 'action' => 'payment.bank_transfer_approved', 'subject_type' => 'order', 'subject_id' => $order->id, 'changes' => ['submission' => $submission->id, 'amount_minor' => $order->total_minor]]);
        }, 3);
        $this->tell($submission, __('Payment approved'), __('Your payment was confirmed. You now have access to :course.', ['course' => $this->courseTitle($submission)]), '/my-learning');
    }

    public function reject(PaymentSubmission $submission, User $admin, string $reason): void
    {
        DB::transaction(function () use ($submission, $admin, $reason) {
            $submission = PaymentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($submission->order_id)->lockForUpdate()->firstOrFail();
            if ($submission->status !== 'pending') {
                throw ValidationException::withMessages(['submission' => 'This payment has already been reviewed.']);
            }
            Payment::where('order_id', $order->id)->update(['status' => 'verified_failure', 'verified_at' => now()]);
            $order->update(['status' => 'failed']);
            CouponRedemption::where('order_id', $order->id)->where('status', 'reserved')->update(['status' => 'released']);
            $submission->update(['status' => 'rejected', 'reviewer_id' => $admin->id, 'reviewed_at' => now(), 'rejection_reason' => $reason]);
            AuditLog::create(['actor_id' => $admin->id, 'action' => 'payment.bank_transfer_rejected', 'subject_type' => 'order', 'subject_id' => $order->id, 'changes' => ['submission' => $submission->id, 'reason' => $reason]]);
        }, 3);
        $this->tell($submission, __('Payment not confirmed'), __('We couldn’t confirm your payment for :course: :reason', ['course' => $this->courseTitle($submission), 'reason' => $reason]).' '.__('If the money did leave your account, message support — we can reopen this payment without you sending it again.'), '/payments');
    }

    /**
     * Undo a mistaken rejection: the submission goes back to "pending" so it can be approved with its original
     * transaction ID (which can't be submitted twice). Refused if the learner has since paid another way.
     */
    public function reopen(PaymentSubmission $submission, User $admin): void
    {
        DB::transaction(function () use ($submission, $admin) {
            $submission = PaymentSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($submission->order_id)->lockForUpdate()->with('items')->firstOrFail();
            if ($submission->status !== 'rejected' || $order->status !== 'failed') {
                throw ValidationException::withMessages(['submission' => 'Only a rejected payment can be reopened.']);
            }
            $courseIds = $order->items->pluck('course_id');
            $otherPending = PaymentSubmission::where('user_id', $submission->user_id)->where('status', 'pending')
                ->whereHas('order.items', fn ($q) => $q->whereIn('course_id', $courseIds))->exists();
            if ($otherPending) {
                throw ValidationException::withMessages(['submission' => 'The learner already has another payment waiting for review for this course.']);
            }
            Payment::where('order_id', $order->id)->update(['status' => 'pending', 'verified_at' => null]);
            $order->update(['status' => 'awaiting_payment']);
            CouponRedemption::where('order_id', $order->id)->where('status', 'released')->update(['status' => 'reserved']);
            $submission->update(['status' => 'pending', 'reviewer_id' => null, 'reviewed_at' => null, 'rejection_reason' => null]);
            AuditLog::create(['actor_id' => $admin->id, 'action' => 'payment.bank_transfer_reopened', 'subject_type' => 'order', 'subject_id' => $order->id, 'changes' => ['submission' => $submission->id]]);
        }, 3);
    }

    /** Price preview for the checkout page: checks a coupon without reserving it. */
    public function quote(User $user, Course $course, ?string $code): array
    {
        $discount = 0;
        $error = null;
        $code = CouponService::normalize($code);
        if ($code) {
            $coupon = Coupon::where('code', $code)->first();
            $reason = app(CouponService::class)->rejection($coupon, $user->id, $course->id);
            $error = $reason ? __($reason) : null;
            $discount = $error ? 0 : CouponService::discount($coupon, $course->price_minor);
        }
        $total = $course->price_minor - $discount;

        return ['code' => $error ? null : $code, 'error' => $error, 'subtotal' => $course->price_minor, 'discount' => $discount, 'total' => $total, 'vat' => AcademySettings::vatIncluded($total), 'vat_percent' => (float) AcademySettings::get('vat_percent')];
    }

    private function courseTitle(PaymentSubmission $submission): string
    {
        return (string) $submission->order()->first()?->items()->value('title_snapshot');
    }

    private function tell(PaymentSubmission $submission, string $title, string $body, string $url): void
    {
        $submission->user?->notify(new EngagementNotification(['title' => $title, 'body' => $body, 'url' => $url]));
    }
}
