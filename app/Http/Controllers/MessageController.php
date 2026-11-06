<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Course;
use App\Models\User;
use App\Notifications\EngagementNotification;
use App\Support\AcademySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Learner ↔ instructor and learner ↔ support conversations (separate from system notifications). */
class MessageController extends Controller
{
    public function index(Request $request): View
    {
        return $this->page($request, null);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        abort_unless($conversation->canBeSeenBy($request->user()), 404);
        $conversation->forceFill([$conversation->isStudent($request->user()) ? 'student_read_at' : 'staff_read_at' => now()])->saveQuietly();

        return $this->page($request, $conversation);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            // Teachers can only be messaged while the instructor workspace is open; otherwise the support team answers.
            'type' => ['required', Rule::in(AcademySettings::get('instructor_portal_enabled') ? ['instructor', 'support'] : ['support'])],
            'course_id' => ['required_if:type,instructor', 'nullable', 'integer'],
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $course = null;
        if ($data['type'] === 'instructor') {
            // Only teachers of courses the learner is actively enrolled in can be messaged.
            $course = Course::published()->whereKey($data['course_id'])
                ->whereHas('enrollments', fn ($q) => $q->active()->where('user_id', $user->id))->first();
            abort_unless($course && $course->instructor_id !== $user->id, 422, 'You can message the instructor of a course you are enrolled in.');
        }

        $conversation = DB::transaction(function () use ($user, $data, $course) {
            $conversation = Conversation::create([
                'student_id' => $user->id, 'type' => $data['type'], 'course_id' => $course?->id, 'instructor_id' => $course?->instructor_id,
                'subject' => $data['subject'], 'last_message_at' => now(), 'student_read_at' => now(),
            ]);
            $conversation->messages()->create(['sender_id' => $user->id, 'body' => $data['body']]);

            return $conversation;
        });
        $this->notifyOtherSide($conversation, $user);

        return redirect()->route('messages.show', $conversation)->with('status', 'Message sent.');
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();
        abort_unless($conversation->canBeSeenBy($user), 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        DB::transaction(function () use ($conversation, $user, $data) {
            $conversation->messages()->create(['sender_id' => $user->id, 'body' => $data['body']]);
            $conversation->forceFill(['last_message_at' => now(), $conversation->isStudent($user) ? 'student_read_at' : 'staff_read_at' => now()])->save();
        });
        $this->notifyOtherSide($conversation, $user);

        return redirect()->route('messages.show', $conversation)->withFragment('latest');
    }

    private function page(Request $request, ?Conversation $active): View
    {
        $user = $request->user();
        $conversations = Conversation::visibleTo($user)->with(['student', 'instructor', 'course'])->latest('last_message_at')->paginate(30);
        $courses = ! AcademySettings::get('instructor_portal_enabled') ? collect() : Course::published()->whereHas('enrollments', fn ($q) => $q->active()->where('user_id', $user->id))
            ->where('instructor_id', '!=', $user->id)->with('instructor')->orderBy('title')->get();

        return view('messages.index', [
            'teacherMessaging' => (bool) AcademySettings::get('instructor_portal_enabled'),
            'conversations' => $conversations,
            'active' => $active?->load(['student', 'instructor', 'course']),
            'thread' => $active?->messages()->with('sender')->oldest('id')->get(),
            'courses' => $courses,
            'startType' => in_array($request->query('to'), ['instructor', 'support'], true) ? $request->query('to') : null,
            'startCourse' => (int) $request->query('course'),
        ]);
    }

    private function notifyOtherSide(Conversation $conversation, User $sender): void
    {
        if ($conversation->isStudent($sender)) {
            $recipients = $conversation->type === 'support'
                ? User::staffFor('support')
                : User::whereKey($conversation->instructor_id)->get();
        } else {
            $recipients = User::whereKey($conversation->student_id)->get();
        }
        $recipients = $recipients->reject(fn (User $u) => $u->is($sender));
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new EngagementNotification([
                'title' => __('New message from :name', ['name' => $sender->name]),
                'body' => $conversation->subject,
                'url' => route('messages.show', $conversation, false),
            ]));
        }
    }
}
