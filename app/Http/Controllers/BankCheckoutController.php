<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PaymentMethod;
use App\Models\PaymentSubmission;
use App\Services\CouponService;
use App\Services\Payments\BankTransferService;
use App\Support\AcademySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankCheckoutController extends Controller
{
    public function show(Request $request, Course $course, BankTransferService $bank): View|RedirectResponse
    {
        abort_unless(Course::published()->whereKey($course->id)->exists(), 404);
        if (Enrollment::active()->where('user_id', $request->user()->id)->where('course_id', $course->id)->exists()) {
            return redirect()->route('learning.index')->with('status', 'You already have access to this course.');
        }
        if ($course->is_free || $course->price_minor <= 0) {
            return redirect()->route('courses.show', $course->slug);
        }
        $methods = PaymentMethod::active()->get();
        $selected = $methods->firstWhere('id', (int) $request->query('method'));

        return view('checkout.bank', [
            'course' => $course->load(['instructor', 'category']),
            'methods' => $methods,
            'selected' => $selected,
            'pending' => $bank->pendingSubmission($request->user(), $course)?->load('method'),
            'enabled' => (bool) AcademySettings::get('bank_checkout_enabled'),
            'quote' => $bank->quote($request->user(), $course, is_string($request->query('coupon')) ? mb_substr($request->query('coupon'), 0, CouponService::MAX_CODE_LENGTH) : null),
            'blocked' => Enrollment::where('user_id', $request->user()->id)->where('course_id', $course->id)->where('status', 'revoked')->exists(),
            'lastRejected' => PaymentSubmission::where('user_id', $request->user()->id)->where('status', 'rejected')->whereHas('order.items', fn ($q) => $q->where('course_id', $course->id))->latest('reviewed_at')->first(),
        ]);
    }

    public function submit(Request $request, Course $course, BankTransferService $bank): RedirectResponse
    {
        $data = $request->validate([
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('active', true)],
            'transaction_reference' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9 \-_\/.#]+$/'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'coupon' => ['nullable', 'string', 'max:'.CouponService::MAX_CODE_LENGTH],
        ]);
        $bank->submit($request->user(), $course, PaymentMethod::findOrFail($data['payment_method_id']), $data['transaction_reference'] ?? null, $request->file('proof'), $data['coupon'] ?? null);

        return redirect()->route('checkout.bank', $course->slug)->with('status', 'Payment submitted. We’ll review it and unlock your course once it’s confirmed.');
    }

    /** Payment screenshots are private: only admins (and the learner who uploaded it) can open them. */
    public function proof(Request $request, PaymentSubmission $submission): StreamedResponse
    {
        abort_unless($request->user()->can('administer-academy') || $submission->user_id === $request->user()->id, 403);
        abort_unless($submission->proof_path && Storage::disk('local')->exists($submission->proof_path), 404);

        // Learner-uploaded files: images may display inline, PDFs always download, and nothing can run scripts.
        $extension = strtolower(pathinfo($submission->proof_path, PATHINFO_EXTENSION));
        $disposition = $extension === 'pdf' ? 'attachment' : 'inline';
        // Type comes from the validated extension, never sniffed from learner-controlled bytes.
        $type = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'][$extension] ?? 'application/octet-stream';

        return Storage::disk('local')->response($submission->proof_path, 'payment-proof-'.$submission->id.'.'.$extension, [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
        ], $disposition);
    }
}
