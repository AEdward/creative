<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\NotificationPreference;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\EngagementNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EngagementController extends Controller
{
    private function active(Request $request): void
    {
        abort_unless($request->user()->status === 'active', 403);
    }

    private function owner(Request $request, Course $course): void
    {
        $this->active($request);
        abort_unless($request->user()->can('instructor') && $course->instructor_id === $request->user()->id, 403);
    }

    private function eligible(Request $request, Course $course): bool
    {
        $enrollment = Enrollment::active()->where('user_id', $request->user()->id)->where('course_id', $course->id)->first();

        return $enrollment && LessonProgress::where('enrollment_id', $enrollment->id)->whereNotNull('completed_at')->whereHas('lesson', fn ($query) => $query->where('published', true)->whereHas('module', fn ($module) => $module->where('course_id', $course->id)))->exists();
    }

    public function reviews(Request $request, Course $course): View
    {
        abort_unless(Course::published()->whereKey($course->id)->exists(), 404);
        $query = CourseReview::where('course_id', $course->id)->where('status', 'approved');

        return view('engagement.reviews', ['course' => $course, 'average' => (clone $query)->avg('rating'), 'reviews' => $query->with('user')->latest()->paginate(15), 'eligible' => $request->user() && $request->user()->status === 'active' && $request->user()->hasVerifiedEmail() && $this->eligible($request, $course), 'ownReview' => $request->user() ? CourseReview::where('course_id', $course->id)->where('user_id', $request->user()->id)->first() : null]);
    }

    public function review(Request $request, Course $course): RedirectResponse
    {
        $this->active($request);
        abort_unless(Course::published()->whereKey($course->id)->exists() && $this->eligible($request, $course), 403);
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'body' => ['required', 'string', 'min:3', 'max:3000']]);
        DB::transaction(function () use ($request, $course, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if (CourseReview::where('course_id', $course->id)->where('user_id', $request->user()->id)->exists()) {
                throw ValidationException::withMessages(['body' => 'You have already reviewed this course.']);
            }CourseReview::create($data + ['course_id' => $course->id, 'user_id' => $request->user()->id, 'status' => DB::table('review_settings')->value('moderation_required') === 0 ? 'approved' : 'pending']);
        });

        return back()->with('status', 'Review submitted. Reviews may require moderation before publication.');
    }

    public function moderation(): View
    {
        return view('engagement.moderation', ['reviews' => CourseReview::with(['user', 'course'])->latest()->paginate(20), 'moderation' => DB::table('review_settings')->value('moderation_required') ?? true]);
    }

    public function moderate(Request $request, CourseReview $review): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'approved', 'rejected'])]]);
        $review->update($data);

        return back()->with('status', 'Review moderation saved.');
    }

    public function reviewSettings(Request $request): RedirectResponse
    {
        $data = $request->validate(['moderation_required' => ['required', 'boolean']]);
        DB::table('review_settings')->updateOrInsert(['id' => 1], $data + ['updated_at' => now(), 'created_at' => now()]);

        return back()->with('status', 'Review policy saved.');
    }

    public function announcements(Request $request, Course $course): View
    {
        $this->active($request);
        $managing = $request->user()->can('instructor') && $course->instructor_id === $request->user()->id;
        abort_unless($managing || Enrollment::active()->where('user_id', $request->user()->id)->where('course_id', $course->id)->exists(), 403);

        return view('engagement.announcements', ['course' => $course, 'managing' => $managing, 'announcements' => Announcement::where('course_id', $course->id)->latest()->paginate(15)]);
    }

    public function manageAnnouncements(Request $request, Course $course): View
    {
        $this->owner($request, $course);

        return $this->announcements($request, $course);
    }

    public function announce(Request $request, Course $course): RedirectResponse
    {
        $this->owner($request, $course);
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:10000']]);
        DB::transaction(function () use ($data, $request, $course) {
            $announcement = Announcement::create($data + ['course_id' => $course->id, 'user_id' => $request->user()->id]);
            User::where('status', 'active')->whereNotNull('email_verified_at')->whereIn('id', Enrollment::active()->where('course_id', $course->id)->select('user_id'))->chunkById(100, function ($users) use ($announcement, $course) {
                foreach ($users as $user) {
                    $this->notify($user, 'announcements', ['title' => $announcement->title, 'body' => $announcement->body, 'url' => route('announcements.index', $course)]);
                }
            });
        });

        return back()->with('status', 'Announcement published to current enrolled learners.');
    }

    private function notify(User $user, string $type, array $message): void
    {
        if (NotificationPreference::where('user_id', $user->id)->value($type) !== false) {
            $user->notify(new EngagementNotification($message));
        }
    }

    public function notifications(Request $request): View
    {
        $this->active($request);

        return view('engagement.notifications', ['notifications' => $request->user()->notifications()->paginate(20), 'preferences' => NotificationPreference::firstOrNew(['user_id' => $request->user()->id], ['announcements' => true, 'support' => true, 'learning' => true])]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $this->active($request);
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return back();
    }

    public function preferences(Request $request): RedirectResponse
    {
        $this->active($request);
        $data = $request->validate(['announcements' => ['required', 'boolean'], 'support' => ['required', 'boolean'], 'learning' => ['sometimes', 'boolean']]);
        NotificationPreference::updateOrCreate(['user_id' => $request->user()->id], $data);

        return back()->with('status', 'Notification preferences saved.');
    }

    public function tickets(Request $request): View
    {
        $this->active($request);

        return view('engagement.tickets', ['tickets' => SupportTicket::where('user_id', $request->user()->id)->latest()->paginate(20), 'admin' => false]);
    }

    public function adminTickets(): View
    {
        return view('engagement.tickets', ['tickets' => SupportTicket::with('user')->latest()->paginate(20), 'admin' => true]);
    }

    public function ticketCreate(Request $request): RedirectResponse
    {
        $this->active($request);
        $data = $request->validate(['subject' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:10000']]);
        $ticket = SupportTicket::create($data + ['user_id' => $request->user()->id]);

        return redirect()->route('support.show', $ticket);
    }

    private function ticketAccess(Request $request, SupportTicket $ticket): void
    {
        $this->active($request);
        abort_unless($ticket->user_id === $request->user()->id || Gate::allows('administer-academy'), 404);
    }

    public function ticket(Request $request, SupportTicket $ticket): View
    {
        $this->ticketAccess($request, $ticket);

        return view('engagement.ticket', ['ticket' => $ticket, 'replies' => $ticket->replies()->with('user')->oldest()->paginate(30)]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->ticketAccess($request, $ticket);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        DB::transaction(function () use ($request, $ticket, $data) {
            $locked = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'open', 422, 'Reopen the ticket before replying.');
            $locked->replies()->create($data + ['user_id' => $request->user()->id]);
            $locked->touch();
            if ($locked->user_id !== $request->user()->id) {
                $this->notify($locked->user, 'support', ['title' => 'Support response: '.$locked->subject, 'body' => $data['body'], 'url' => route('support.show', $locked)]);
            }
        });

        return back()->with('status', 'Reply added.');
    }

    public function ticketStatus(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->ticketAccess($request, $ticket);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'closed'])]]);
        DB::transaction(function () use ($ticket, $data) {
            SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail()->update($data);
        });

        return back()->with('status', 'Ticket status updated.');
    }
}
