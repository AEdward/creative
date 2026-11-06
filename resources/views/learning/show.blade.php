<x-layout :title="$lesson->title">
@php
    // Step-by-step course: a step is open when everything before it is finished. Guests/previews see no locks.
    $locked = fn ($item) => $enrollment && ! in_array($item->id, $unlockedIds, true);
    $stepIcon = ['text' => 'heroicon-o-document-text', 'video' => 'heroicon-o-play-circle', 'quiz' => 'heroicon-o-question-mark-circle', 'assignment' => 'heroicon-o-arrow-up-tray'];
    $nextOpen = $next && $enrollment && $stepDone;
    $lastPage = $pages && $page === count($pages);
@endphp
<section class="learning-header"><div class="container"><a class="inline-link" href="{{ route('courses.show', $course->slug) }}">{{ __('← Back to') }} {{ $course->title }}</a></div></section>
<section class="section lesson-section"><div class="container lesson-grid">
<article class="lesson-content">
    <p class="eyebrow">{{ $lesson->is_preview && ! $enrollment ? __('Free preview') : __('Your learning space') }}</p>
    <h1>{{ $lesson->title }}</h1>
    @if(session('status'))<div class="notice" role="status">{{ __(session('status')) }}</div>@endif
    @error('lesson')<div class="notice notice-error" role="alert">{{ __($message) }}</div>@enderror

    {{-- TEXT: pages are read in order; the last page finishes the step. --}}
    @if($lesson->type === 'text')
        @if(count($pages) > 1)<p class="step-pages">{{ __('Page :page of :total', ['page' => $page, 'total' => count($pages)]) }}</p>@endif
        <div class="lesson-text preserve-lines">{{ $pages[$page - 1] ?? '' }}</div>
        @if($enrollment)
            <div class="step-pager">
                @if($page > 1)<a class="dash-outline" href="{{ route('learning.show', [$course->slug, $lesson->id, 'page' => $page - 1]) }}">← {{ __('Previous page') }}</a>@endif
                @if(! $lastPage)
                    <a class="button" href="{{ route('learning.show', [$course->slug, $lesson->id, 'page' => $page + 1]) }}">{{ __('Next page') }} →</a>
                @elseif(! $stepDone)
                    <form method="POST" action="{{ route('learning.complete', [$course->slug, $lesson->id]) }}">@csrf<button class="button">{{ __('Finish & continue') }} ✓</button></form>
                @endif
            </div>
        @endif

    {{-- VIDEO: no skipping ahead past what you've watched; the end of the video finishes the step. --}}
    @elseif($lesson->type === 'video')
        @if($videoPlayable)
            <div data-private-video data-playback="{{ route('media.playback', [$course->slug, $lesson->id]) }}" data-resume="{{ $progress?->position_seconds ?? 0 }}" data-max-watched="{{ $stepDone ? 999999 : ($progress?->max_position_seconds ?? 0) }}" @if($enrollment) data-progress="{{ route('learning.progress', [$course->slug, $lesson->id]) }}" @endif>
                <div class="secure-player" data-secure-player><video controls playsinline preload="metadata" aria-label="{{ $lesson->title }}" class="lesson-video" controlslist="nodownload noremoteplayback noplaybackrate" disablepictureinpicture disableremoteplayback>@foreach($captions as $caption)<track kind="captions" src="{{ route('resources.download', $caption->id) }}" srclang="{{ $caption->language ?? 'en' }}" label="{{ $caption->title }}">@endforeach</video><span class="video-watermark" aria-hidden="true">{{ auth()->check() ? auth()->user()->name.' · '.auth()->user()->email : __('Creative Lab Academy preview') }}</span><span class="video-watermark video-watermark-faint" aria-hidden="true">{{ auth()->check() ? auth()->user()->email : 'creativelab' }} · {{ now()->format('Y-m-d H:i') }}</span></div>
                <p class="small" data-video-status role="status">{{ __('Preparing private playback…') }}</p><button type="button" class="filter-apply" data-reload-video>{{ __('Refresh playback') }}</button>
                @if($enrollment && ! $stepDone)<p class="small">{{ __('Watch the whole video to unlock the next step. You can go back and rewatch any part, but not skip ahead.') }}</p>@endif
            </div>
        @else
            <div class="empty-state"><h2>{{ __('Video is not available yet.') }}</h2><p>{{ __('Please return later while the instructor prepares this lesson.') }}</p></div>
        @endif

    {{-- QUIZ: marked automatically; reaching the pass mark unlocks the next step. Unlimited retries. --}}
    @elseif($lesson->type === 'quiz' && $quiz)
        @php $best = $attempts->max('score'); $passedQuiz = $attempts->contains('passed', true); @endphp
        <p>{{ $quiz->instructions }}</p>
        <p class="step-meta">{{ __('Pass mark: :percent%', ['percent' => $quiz->pass_percent]) }} · {{ count($quiz->questions ?? []) }} {{ __('questions') }}@if($attempts->isNotEmpty()) · {{ __('Best score: :score%', ['score' => $best]) }}@endif</p>
        @if($attempts->isNotEmpty())<p class="notice {{ $attempts->first()->passed ? '' : 'notice-error' }}" role="status">{{ $attempts->first()->passed ? __('Passed with :score%.', ['score' => $attempts->first()->score]) : __('Last attempt: :score%. You need :pass% — try again.', ['score' => $attempts->first()->score, 'pass' => $quiz->pass_percent]) }}</p>@endif
        @if($enrollment && ! $passedQuiz)
            <form method="POST" action="{{ route('assessments.attempt', [$course, $quiz]) }}" class="step-quiz">@csrf
                @foreach($quiz->questions ?? [] as $index => $question)
                    <fieldset><legend>{{ $index + 1 }}. {{ $question['prompt'] ?? $question['question'] ?? '' }}</legend>
                        @foreach($question['options'] as $optionIndex => $option)<label class="checkbox-label"><input type="radio" name="answers[{{ $index }}]" value="{{ $optionIndex }}" required> {{ $option }}</label>@endforeach
                    </fieldset>
                @endforeach
                <button class="button">{{ $attempts->isEmpty() ? __('Submit answers') : __('Try again') }}</button>
            </form>
        @endif

    {{-- ASSIGNMENT: submit work; the admin chose whether submitting is enough or the grade is needed. --}}
    @elseif($lesson->type === 'assignment' && $assignment)
        <div class="lesson-text preserve-lines">{{ $assignment->instructions }}</div>
        <p class="step-meta">{{ $assignment->advance_on === 'submit' ? __('Submitting your work unlocks the next step.') : __('The next step unlocks once your work is reviewed and accepted.') }}</p>
        @if($submission)<p class="notice {{ $submission->status === 'revision-needed' ? 'notice-error' : '' }}" role="status">{{ ['pending' => __('Submitted — waiting for review.'), 'accepted' => __('Accepted.'), 'revision-needed' => __('Changes requested. Upload a new version below.')][$submission->status] ?? $submission->status }}@if($submission->feedback) — {{ $submission->feedback }}@endif</p>@endif
        @if($enrollment && $submission?->status !== 'accepted')
            <form method="POST" action="{{ route('assignments.submit', [$course, $assignment]) }}" enctype="multipart/form-data" class="step-quiz">@csrf
                <div class="form-field"><label for="step-file">{{ __('Your work (PDF, image or ZIP, up to 10 MB)') }}</label><input id="step-file" type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.zip">@error('file')<p class="field-error" role="alert">{{ __($message) }}</p>@enderror</div>
                <button class="button" data-busy-label="{{ __('Submitting…') }}">{{ $submission ? __('Upload new version') : __('Submit work') }}</button>
            </form>
        @endif
    @endif

    @if($downloads->isNotEmpty())<section class="private-notes"><h2>{{ __('Lesson downloads') }}</h2><ul>@foreach($downloads as $resource)<li><a class="inline-link" href="{{ route('resources.download', $resource->id) }}">{{ $resource->title }} · {{ strtoupper($resource->extension) }}</a></li>@endforeach</ul></section>@endif

    <div class="lesson-actions">
        @if($enrollment && $stepDone)<span class="completed-badge">✓ {{ __('Step complete') }}</span>
        @elseif(! $enrollment)<a class="button" href="{{ route('courses.show', $course->slug) }}">{{ __('Explore this course') }} <x-heroicon-o-arrow-up-right class="direction-icon" aria-hidden="true" /></a>@endif
    </div>

    @if($enrollment)
        <section class="private-notes"><h2>{{ __('Your private notes') }}</h2><p>{{ __('Capture an idea or bookmark this lesson for later. Only you can see these notes.') }}</p><form method="POST" action="{{ route('learning.notes', [$course->slug, $lesson->id]) }}">@csrf @method('PUT')<div class="form-field"><label for="lesson-notes">{{ __('Notes') }}</label><textarea id="lesson-notes" name="body" rows="5" maxlength="10000">{{ is_string(old('body')) ? old('body') : ($note?->body ?? '') }}</textarea></div><div class="form-extra"><label class="checkbox-label"><input type="checkbox" name="bookmarked" value="1" @checked($note?->bookmarked)>{{ __('Bookmark this lesson') }}</label></div>@error('body')<p class="field-error" role="alert">{{ __($message) }}</p>@enderror<button class="filter-apply">{{ __('Save private notes') }}</button></form></section>
    @endif

    <nav class="lesson-navigation" aria-label="{{ __('Lesson navigation') }}">
        @if($previous)<a href="{{ route('learning.show', [$course->slug, $previous->id]) }}">← {{ $previous->title }}</a>@else<span></span>@endif
        @if($next)
            @if(! $enrollment || $nextOpen)<a href="{{ route('learning.show', [$course->slug, $next->id]) }}">{{ $next->title }} →</a>
            @else<span class="step-next-locked" aria-disabled="true"><x-heroicon-o-lock-closed aria-hidden="true" /> {{ __('Next: :title', ['title' => $next->title]) }}</span>@endif
        @endif
    </nav>
</article>

<aside class="lesson-sidebar"><p class="eyebrow">{{ __('Your learning path') }}</p><h2>{{ __('Course curriculum') }}</h2>
    @foreach($course->modules as $module)@if($module->lessons->isNotEmpty())
        <div class="sidebar-module"><h3>{{ $module->title }}</h3><ol>
            @foreach($module->lessons as $item)
                @php $isDone = in_array($item->id, $completedIds); $isLocked = $locked($item); @endphp
                <li>
                    @if($isLocked)
                        <span class="step-locked"><span aria-hidden="true"><x-heroicon-o-lock-closed /></span><span class="sr-only">{{ __('Locked:') }}</span><span>{{ $item->title }}</span></span>
                    @else
                        <a href="{{ route('learning.show', [$course->slug, $item->id]) }}" @if($item->id === $lesson->id) aria-current="page" @endif><span aria-hidden="true">{{ $isDone ? '✓' : '▷' }}</span><span class="sr-only">{{ $isDone ? __('Completed:') : __('Not completed:') }}</span><span>{{ $item->title }}@if($item->is_preview && ! $enrollment)<small>{{ __('Free preview') }}</small>@endif</span></a>
                    @endif
                </li>
            @endforeach
        </ol></div>
    @endif @endforeach
</aside>
</div></section>
</x-layout>
