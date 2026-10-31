<x-filament-panels::page>
@php $courseNames = $courses->pluck('title', 'id'); @endphp
<div class="cla-dash">
    <section class="cla-card">
        <header><h2><x-filament::icon icon="heroicon-o-arrow-up-tray" />{{ __('Upload a video') }}</h2></header>
        <p class="cla-muted">{{ __('Videos upload straight to private Cloudflare R2 storage from the course builder. Pick a course, then choose the lesson the video belongs to.') }}</p>
        <form method="GET" class="cla-upload" onsubmit="event.preventDefault(); if (this.course.value) location.href = this.course.value;">
            <label class="sr-only" for="ml-course">{{ __('Course') }}</label>
            <select id="ml-course" name="course" required><option value="">{{ __('Choose a course…') }}</option>@foreach($courses as $course)<option value="{{ route('instructor.courses.edit', $course->slug) }}#video-lesson">{{ $course->title }}</option>@endforeach</select>
            <button type="submit" class="cla-primary">{{ __('Open uploader') }}</button>
        </form>
    </section>
    <section class="cla-card">
        <header><h2><x-filament::icon icon="heroicon-o-film" />{{ __('Videos') }}</h2></header>
        <div class="cla-table-wrap"><table class="cla-table">
            <thead><tr><th>{{ __('File') }}</th><th>{{ __('Course') }}</th><th>{{ __('Lesson') }}</th><th>{{ __('Size') }}</th><th>{{ __('Status') }}</th><th>{{ __('Uploaded') }}</th></tr></thead>
            <tbody>
            @forelse($videos as $video)
                <tr><td>{{ $video->filename }}</td><td>{{ $courseNames[$video->course_id] ?? '—' }}</td><td>{{ $lessons[$video->id] ?? __('Not attached') }}</td><td>{{ number_format($video->expected_bytes / 1048576, 1) }} MB</td>
                    <td><span class="cla-badge {{ $video->status === 'ready' ? 'ok' : ($video->status === 'failed' ? 'bad' : 'muted') }}">{{ __(ucfirst($video->status)) }}</span></td><td>{{ $video->created_at->translatedFormat('M j, Y') }}</td></tr>
            @empty
                <tr><td colspan="6" class="cla-empty">{{ __('No videos uploaded yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $videos->links() }}
    </section>
</div>
</x-filament-panels::page>
