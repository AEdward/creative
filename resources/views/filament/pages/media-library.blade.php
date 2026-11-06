<x-filament-panels::page>
@php $courseNames = $courses->pluck('title', 'id'); $chapters = $this->chapters(); $lessons = $this->lessons(); @endphp
<div class="cla-dash">
    <section class="cla-card">
        <header><h2><x-filament::icon icon="heroicon-o-arrow-up-tray" />{{ __('Upload a video') }}</h2></header>
        <p class="cla-muted">{{ __('Choose where the video goes, then pick the MP4 file. It uploads straight to private Cloudflare R2 storage and is attached to the lesson when it finishes.') }}</p>

        <div class="cla-form cla-steps">
            <div>
                <label for="ml-course">{{ __('1. Course') }}</label>
                <select id="ml-course" wire:model.live="courseId">
                    <option value="">{{ __('Choose a course…') }}</option>
                    @foreach($courses as $option)<option value="{{ $option->id }}">{{ $option->title }}</option>@endforeach
                </select>
            </div>

            @if($course)
            <div>
                <label for="ml-chapter">{{ __('2. Chapter') }}</label>
                <select id="ml-chapter" wire:model.live="moduleId">
                    <option value="">{{ $chapters->isEmpty() ? __('No chapters yet — add one below') : __('Choose a chapter…') }}</option>
                    @foreach($chapters as $option)<option value="{{ $option->id }}">{{ $option->title }}</option>@endforeach
                </select>
                <div class="cla-inline">
                    <input type="text" wire:model="newChapter" maxlength="180" placeholder="{{ __('…or add a new chapter') }}" aria-label="{{ __('New chapter title') }}">
                    <button type="button" class="cla-outline" wire:click="addChapter">{{ __('Add chapter') }}</button>
                </div>
                @error('newChapter')<p class="cla-error">{{ $message }}</p>@enderror
            </div>
            @endif

            @if($moduleId)
            <div>
                <label for="ml-lesson">{{ __('3. Lesson') }}</label>
                <select id="ml-lesson" wire:model.live="lessonId">
                    <option value="">{{ $lessons->isEmpty() ? __('No lessons yet — add one below') : __('Choose a lesson…') }}</option>
                    @foreach($lessons as $option)<option value="{{ $option->id }}">{{ $option->title }}{{ $option->video_asset_id ? ' · '.__('has a video (will be replaced)') : '' }}</option>@endforeach
                </select>
                <div class="cla-inline">
                    <input type="text" wire:model="newLesson" maxlength="180" placeholder="{{ __('…or add a new lesson') }}" aria-label="{{ __('New lesson title') }}">
                    <button type="button" class="cla-outline" wire:click="addLesson">{{ __('Add lesson') }}</button>
                </div>
                @error('newLesson')<p class="cla-error">{{ $message }}</p>@enderror
            </div>
            @endif
        </div>

        @if($course && $lessonId)
            @if($storageReady)
                {{-- wire:key re-creates the form when the target changes, so the uploader re-binds to the right course. --}}
                <form class="cla-form cla-uploader" data-video-upload="{{ route('media.store', $course->slug) }}" wire:key="upload-{{ $course->id }}-{{ $lessonId }}" wire:ignore>
                    <input type="hidden" name="lesson_id" value="{{ $lessonId }}">
                    <label for="ml-file">{{ __('4. MP4 video (up to 1 GB)') }}</label>
                    <input id="ml-file" type="file" accept="video/mp4,.mp4" required>
                    <label for="ml-duration">{{ __('Length in seconds (filled in automatically)') }}</label>
                    <input id="ml-duration" name="duration_seconds" type="number" min="1" max="86400" required>
                    <div class="cla-actions"><button class="cla-primary" type="submit">{{ __('Upload and attach') }}</button><button class="cla-outline" type="button" data-cancel-upload>{{ __('Cancel upload') }}</button></div>
                    <progress class="cla-progress" max="100" value="0" hidden data-upload-progress></progress>
                    <p class="cla-muted" role="status" aria-live="polite"></p>
                </form>
            @else
                <p class="cla-warning" role="alert"><x-filament::icon icon="heroicon-o-exclamation-triangle" />{{ __('Video storage isn’t connected yet. Add the Cloudflare R2 keys to the server’s .env, then uploads work here.') }}</p>
            @endif
        @endif
    </section>

    <section class="cla-card">
        <header><h2><x-filament::icon icon="heroicon-o-film" />{{ __('Videos') }}</h2></header>
        <div class="cla-table-wrap"><table class="cla-table">
            <thead><tr><th>{{ __('File') }}</th><th>{{ __('Course') }}</th><th>{{ __('Chapter · Lesson') }}</th><th>{{ __('Size') }}</th><th>{{ __('Status') }}</th><th>{{ __('Uploaded') }}</th></tr></thead>
            <tbody>
            @forelse($videos as $video)
                @php $attached = $lessonsByAsset[$video->id] ?? null; @endphp
                <tr><td>{{ $video->filename }}</td><td>{{ $courseNames[$video->course_id] ?? '—' }}</td><td>{{ $attached ? ($attached->module?->title.' · '.$attached->title) : __('Not attached') }}</td><td>{{ number_format($video->expected_bytes / 1048576, 1) }} MB</td>
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
