<x-filament-panels::page>
@php
    $peak = max(1, collect($days)->max(fn ($d) => max($d['enrollments'], $d['active'])));
    $n = max(1, count($days) - 1);
    $line = fn (string $key) => collect(array_values($days))->map(fn ($d, $i) => number_format(10 + 780 * $i / $n, 1, '.', '').','.number_format(190 - 170 * $d[$key] / $peak, 1, '.', ''))->implode(' ');
@endphp
<div class="cla-dash">
    <section class="cla-card cla-range">
        <div class="cla-range-fields">
            <label>{{ __('From') }} <input type="date" wire:model.live="from"></label>
            <label>{{ __('To') }} <input type="date" wire:model.live="to"></label>
            @error('to')<p class="cla-error">{{ $message }}</p>@enderror
        </div>
        <div class="cla-range-presets">@foreach([7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '12 months'] as $d => $label)<button type="button" class="cla-outline" wire:click="preset({{ $d }})">{{ __($label) }}</button>@endforeach</div>
    </section>

    <div class="cla-stats cla-stats-6">
        @foreach($metrics as [$label, $value, $icon])
            <div class="cla-card cla-stat"><x-filament::icon :icon="$icon" class="cla-stat-icon" /><div><p class="cla-muted">{{ __($label) }}</p><strong>{{ is_int($value) ? number_format($value) : $value }}</strong></div></div>
        @endforeach
    </div>

    <section class="cla-card">
        <header><h2><x-filament::icon icon="heroicon-o-chart-bar" />{{ __('Daily activity') }}</h2><span class="cla-legend"><i class="a"></i>{{ __('New enrollments') }} <i class="b"></i>{{ __('Active learners') }}</span></header>
        <svg viewBox="0 0 800 200" class="cla-chart" role="img" aria-label="{{ __('Daily enrollments and active learners') }}" preserveAspectRatio="none">
            @foreach([20, 105, 190] as $y)<line x1="10" x2="790" y1="{{ $y }}" y2="{{ $y }}" class="grid" />@endforeach
            <polyline points="{{ $line('active') }}" class="b" />
            <polyline points="{{ $line('enrollments') }}" class="a" />
        </svg>
        <div class="cla-chart-axis"><span>{{ array_key_first($days) }}</span><span>{{ __('peak :n / day', ['n' => $peak]) }}</span><span>{{ array_key_last($days) }}</span></div>
    </section>

    <section class="cla-card">
        <header><h2><x-filament::icon icon="heroicon-o-book-open" />{{ __('Courses') }}</h2></header>
        <div class="cla-table-wrap"><table class="cla-table">
            <thead><tr><th>{{ __('Course') }}</th><th>{{ __('Enrolled in period') }}</th><th>{{ __('Active learners') }}</th><th>{{ __('Finished') }}</th><th>{{ __('Completion rate') }}</th></tr></thead>
            <tbody>
            @forelse($courses as $course)
                @php $done = (int) ($finished[$course->id] ?? 0); $rate = $course->learners ? round(100 * $done / $course->learners) : 0; @endphp
                <tr><td>{{ $course->title }}</td><td>{{ $course->period_enrollments }}</td><td>{{ $course->learners }}</td><td>{{ $done }}</td>
                    <td><div class="cla-meter cla-meter-sm"><span style="width: {{ min(100, $rate) }}%"></span></div>{{ $rate }}%</td></tr>
            @empty
                <tr><td colspan="5" class="cla-empty">{{ __('No courses yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <p class="cla-muted">{{ __('“Finished” counts learners who earned the course certificate. Revenue counts verified payments only, before refunds; see Finance for the full picture.') }}</p>
    </section>
</div>
</x-filament-panels::page>
