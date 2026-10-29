<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\VideoAsset;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademyController extends Controller
{
    private function dates(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $start = CarbonImmutable::parse($data['from'] ?? now('Africa/Nairobi')->startOfMonth()->toDateString(), 'Africa/Nairobi')->startOfDay()->utc();
        $end = CarbonImmutable::parse($data['to'] ?? now('Africa/Nairobi')->toDateString(), 'Africa/Nairobi')->endOfDay()->utc();
        abort_unless($end->greaterThanOrEqualTo($start) && $start->diffInDays($end) <= 366, 422, 'Choose a date range of up to one year.');

        return [$start, $end];
    }

    public function index(Request $request): View
    {
        [$start,$end] = $this->dates($request);
        $metrics = ['New student accounts' => User::where('role', 'student')->whereBetween('created_at', [$start, $end])->count(), 'New enrollments' => Enrollment::whereBetween('enrolled_at', [$start, $end])->count(), 'Learners saving progress' => LessonProgress::whereBetween('lesson_progress.updated_at', [$start, $end])->join('enrollments', 'enrollments.id', '=', 'lesson_progress.enrollment_id')->distinct()->count('enrollments.user_id'), 'Verified ETB payments (minor units)' => Payment::where('currency', 'ETB')->where('status', 'verified_success')->whereBetween('verified_at', [$start, $end])->sum('amount_minor')];
        $enrollments = Enrollment::with('user', 'course')->latest('enrolled_at')->paginate(20, ['*'], 'enrollments_page');
        $audits = AuditLog::latest()->paginate(20, ['*'], 'audit_page');
        $coupons = Coupon::latest()->paginate(20, ['*'], 'coupons_page');
        $media = VideoAsset::latest()->paginate(20, ['*'], 'media_page');
        $content = SiteSetting::content();
        $courses = Course::orderBy('title')->get(['id', 'title']);

        return view('admin.operations', compact('start', 'end', 'metrics', 'enrollments', 'audits', 'coupons', 'media', 'content', 'courses'));
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['banner' => ['nullable', 'string', 'max:300'], 'about' => ['nullable', 'string', 'max:10000'], 'faq' => ['nullable', 'string', 'max:10000'], 'contact_email' => ['nullable', 'email', 'max:255']]);
        DB::transaction(function () use ($data, $request): void {
            SiteSetting::updateOrCreate(['key' => 'site_content'], ['value' => $data]);
            $this->audit($request, 'settings.updated', 'site', 0, ['keys' => array_keys($data)]);
        });

        return back()->with('status', 'Site content saved. Legal policy text remains subject to review.');
    }

    public function revoke(Request $request, Enrollment $enrollment): RedirectResponse
    {
        DB::transaction(function () use ($request, $enrollment): void {
            User::whereKey($enrollment->user_id)->lockForUpdate()->firstOrFail();
            $locked = Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            $locked->update(['status' => 'revoked']);
            $this->audit($request, 'enrollment.revoked', 'enrollment', $locked->id, ['status' => 'revoked']);
        });

        return back()->with('status', 'Enrollment access revoked. Learning and financial history retained.');
    }

    public function coupon(Request $request): RedirectResponse
    {
        $request->merge(['code' => is_string($request->input('code')) ? Str::upper(trim($request->input('code'))) : $request->input('code')]);
        $data = $request->validate(['code' => ['required', 'string', 'regex:/^[A-Z0-9_-]{3,50}$/', 'unique:coupons,code'], 'percent' => ['required', 'integer', 'min:1', 'max:99'], 'max_uses' => ['required', 'integer', 'min:1', 'max:100000'], 'per_user_limit' => ['required', 'integer', 'min:1', 'max:100'], 'course_id' => ['nullable', 'integer', 'exists:courses,id'], 'starts_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after:starts_at']]);
        DB::transaction(function () use ($request, $data): void {
            $coupon = Coupon::create($data + ['active' => true]);
            $this->audit($request, 'coupon.created', 'coupon', $coupon->id, ['percent' => $coupon->percent]);
        });

        return back()->with('status', 'Coupon created. Real checkout remains disabled.');
    }

    public function toggleCoupon(Request $request, Coupon $coupon): RedirectResponse
    {
        DB::transaction(function () use ($request, $coupon): void {
            $coupon = Coupon::whereKey($coupon->id)->lockForUpdate()->firstOrFail();
            $coupon->update(['active' => ! $coupon->active]);
            $this->audit($request, 'coupon.toggled', 'coupon', $coupon->id, ['active' => $coupon->active]);
        });

        return back()->with('status', 'Coupon availability updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        [$start,$end] = $this->dates($request);

        return response()->streamDownload(function () use ($start, $end): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Learner', 'Course', 'Status', 'Enrolled UTC'], ',', '"', '');
            Enrollment::with('user', 'course')->whereBetween('enrolled_at', [$start, $end])->orderBy('id')->chunkById(100, function ($rows) use ($stream): void {
                foreach ($rows as $row) {
                    $safe = fn (string $value): string => preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;
                    fputcsv($stream, [$safe($row->user->name), $safe($row->course->title), $row->status, $row->enrolled_at->toIso8601String()], ',', '"', '');
                }
            });
            fclose($stream);
        }, 'academy-enrollments.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }

    private function audit(Request $request,string $action,string $type,int $id,array $changes): void
    {
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'changes' => $changes]);
    }
}
