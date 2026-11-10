<x-filament-panels::page>
@php $model = $this->model(); @endphp
<div class="cla-studio">
    @if($model)
        <nav class="cla-tabs" aria-label="{{ __('Course studio') }}">
            @foreach(\App\Filament\Pages\CourseStudio::TABS as $key => $label)
                <a href="{{ \App\Filament\Pages\CourseStudio::getUrl(['course' => $model->slug, 'tab' => $key]) }}" @if($tab === $key) aria-current="page" @endif>{{ __($label) }}</a>
            @endforeach
            <a class="cla-tabs-view" href="{{ route('courses.show', $model->slug) }}" target="_blank" rel="noopener">{{ __('View course page') }} ↗</a>
        </nav>
    @endif

    @if($tab === 'build')
        @livewire(\App\Livewire\Instructor\CourseEditor::class, ['course' => $model, 'embedded' => true], key('editor-'.($model?->id ?? 'new')))
    @else
        {!! $this->tabContent() !!}
    @endif
</div>
</x-filament-panels::page>
