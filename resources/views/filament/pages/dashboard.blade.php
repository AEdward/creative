<x-filament-panels::page>
@php
    $fmtBytes = function (float $bytes): string {
        foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
            if ($bytes < 1024 || $unit === 'TB') return ($unit === 'B' ? (int) $bytes : number_format($bytes, 1)).' '.$unit;
            $bytes /= 1024;
        }
    };
    // Line chart geometry (viewBox 0 0 400 180, plot area x 30..390, y 10..150).
    $gMax = max(5, (int) ceil($growth->max('value') / 5) * 5);
    $gPoints = $growth->values()->map(fn ($p, $i) => [30 + $i * (360 / max(1, $growth->count() - 1)), 150 - ($p['value'] / $gMax) * 140]);
    $gLine = $gPoints->map(fn ($p) => round($p[0], 1).','.round($p[1], 1))->implode(' ');
    $bMax = max(5, (int) ceil(max(1, $byCategory->max('value')) / 5) * 5);
    $usedPct = $storage['quota'] > 0 ? min(100, round($storage['used'] / $storage['quota'] * 100)) : 0;
@endphp
<div class="cla-dash">
    <div class="cla-dash-head">
        <div><h1>{{ __('Dashboard') }}</h1><p>{{ __('Welcome back! Here’s what’s happening with your academy.') }}</p></div>
        <span class="cla-dash-range"><x-filament::icon icon="heroicon-o-calendar" />{{ now()->startOfMonth()->translatedFormat('M j') }} – {{ now()->translatedFormat('M j, Y') }}</span>
    </div>

    <div class="cla-stats">
        @foreach($stats as [$label, $value, $change, $icon, $tone])
            <div class="cla-stat cla-tone-{{ $tone }}">
                <span class="cla-stat-icon"><x-filament::icon :icon="$icon" /></span>
                <div><p>{{ __($label) }}</p><strong>{{ $value }}</strong>
                    @if($change)<small class="{{ $change['up'] ? 'up' : 'down' }}">{{ $change['up'] ? '↑' : '↓' }} {{ __($change['text']) }}</small>@else<small class="flat">{{ __('No change from last month') }}</small>@endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="cla-row cla-row-3">
        <section class="cla-card">
            <header><h2><x-filament::icon icon="heroicon-o-chart-bar" />{{ __('Students Growth') }}</h2><span class="cla-pill">{{ __('Last 6 months') }}</span></header>
            <svg viewBox="0 0 400 180" class="cla-chart" role="img" aria-label="{{ __('Registered students per month') }}: {{ $growth->map(fn ($p) => $p['label'].' '.$p['value'])->implode(', ') }}">
                <defs><linearGradient id="claArea" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#7138F5" stop-opacity=".28"/><stop offset="1" stop-color="#7138F5" stop-opacity="0"/></linearGradient></defs>
                @foreach(range(0, 5) as $t)<line x1="30" x2="390" y1="{{ 150 - $t * 28 }}" y2="{{ 150 - $t * 28 }}" class="grid"/><text x="22" y="{{ 154 - $t * 28 }}" class="axis" text-anchor="end">{{ (int) ($gMax / 5 * $t) }}</text>@endforeach
                <polygon points="30,150 {{ $gLine }} 390,150" fill="url(#claArea)"/>
                <polyline points="{{ $gLine }}" class="line"/>
                @foreach($gPoints as $i => $p)<circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r="{{ $loop->last ? 5 : 3.5 }}" class="dot"/><text x="{{ $p[0] }}" y="172" class="axis" text-anchor="middle">{{ $growth[$i]['label'] }}</text>@endforeach
            </svg>
        </section>

        <section class="cla-card">
            <header><h2><x-filament::icon icon="heroicon-o-clipboard-document-check" />{{ __('Enrollments by Course') }}</h2><span class="cla-pill">{{ __('Last 30 days') }}</span></header>
            @php $n = max(1, $byCategory->count()); $slot = 360 / $n; @endphp
            <svg viewBox="0 0 400 190" class="cla-chart" role="img" aria-label="{{ __('Enrollments in the last 30 days') }}: {{ $byCategory->map(fn ($b) => $b['label'].' '.$b['value'])->implode(', ') }}">
                @foreach(range(0, 4) as $t)<line x1="30" x2="390" y1="{{ 140 - $t * 32.5 }}" y2="{{ 140 - $t * 32.5 }}" class="grid"/><text x="22" y="{{ 144 - $t * 32.5 }}" class="axis" text-anchor="end">{{ (int) ($bMax / 4 * $t) }}</text>@endforeach
                @foreach($byCategory->values() as $i => $b)
                    @php $h = $b['value'] / $bMax * 130; $x = 30 + $i * $slot + $slot * .2; $w = $slot * .6; @endphp
                    <rect x="{{ $x }}" y="{{ 140 - $h }}" width="{{ $w }}" height="{{ max($h, 1) }}" rx="4" class="bar" style="opacity:{{ 1 - $i * .12 }}"/>
                    <text x="{{ $x + $w / 2 }}" y="{{ 134 - $h }}" text-anchor="middle" class="val">{{ $b['value'] }}</text>
                    @foreach(explode(' ', $b['label'], 2) as $k => $word)<text x="{{ $x + $w / 2 }}" y="{{ 158 + $k * 12 }}" text-anchor="middle" class="axis">{{ $word }}</text>@endforeach
                @endforeach
            </svg>
        </section>

        <section class="cla-card">
            <header><h2><x-filament::icon icon="heroicon-o-cloud" />{{ __('Storage Usage') }}</h2></header>
            <p class="cla-storage"><strong>{{ $fmtBytes($storage['used']) }}</strong> {{ __('of :quota used', ['quota' => $fmtBytes($storage['quota'])]) }}</p>
            <div class="cla-meter"><span style="width:{{ $usedPct }}%"></span></div><p class="cla-meter-pct">{{ $usedPct }}%</p>
            <ul class="cla-legend">
                <li><i style="background:#7138F5"></i>{{ __('Videos') }}<b>{{ $fmtBytes($storage['videos']) }}</b></li>
                <li><i style="background:#B9A3FA"></i>{{ __('Lesson files') }}<b>{{ $fmtBytes($storage['files']) }}</b></li>
            </ul>
            <a class="cla-outline" href="{{ \App\Filament\Pages\MediaLibrary::getUrl() }}"><x-filament::icon icon="heroicon-o-folder" />{{ __('Manage Media Library') }}</a>
        </section>
    </div>

    <div class="cla-row cla-row-2">
        <section class="cla-card">
            <header><h2><x-filament::icon icon="heroicon-o-book-open" />{{ __('Recent Courses') }}</h2><a href="{{ \App\Filament\Resources\Courses\CourseResource::getUrl() }}">{{ __('View All') }}</a></header>
            <div class="cla-table-wrap"><table class="cla-table">
                <thead><tr><th>{{ __('Course') }}</th><th>{{ __('Instructor') }}</th><th>{{ __('Lessons') }}</th><th>{{ __('Students') }}</th><th>{{ __('Status') }}</th><th>{{ __('Created') }}</th></tr></thead>
                <tbody>
                @forelse($recentCourses as $course)
                    @php $thumb = 'images/categories/'.$course->category?->slug.'.webp'; @endphp
                    <tr>
                        <td><span class="cla-course">@if($course->thumbnail_key)<img src="{{ route('courses.thumbnail', $course->slug) }}" alt="">@elseif(file_exists(public_path($thumb)))<img src="{{ asset($thumb) }}" alt="">@else<span class="cla-ph"></span>@endif<a href="{{ route('instructor.courses.edit', $course->slug) }}">{{ $course->title }}</a></span></td>
                        <td><span class="cla-person"><span class="cla-ini">{{ mb_substr($course->instructor?->name ?? '?', 0, 1) }}</span>{{ $course->instructor?->name }}</span></td>
                        <td>{{ $course->lessons_count }}</td><td>{{ $course->enrollments_count }}</td>
                        <td><span class="cla-badge {{ $course->status === 'published' ? 'ok' : 'muted' }}">{{ __(ucfirst($course->status)) }}</span></td>
                        <td>{{ $course->created_at->translatedFormat('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="cla-empty">{{ __('No courses yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>

        <div class="cla-stack">
            <section class="cla-card">
                <header><h2><x-filament::icon icon="heroicon-o-users" />{{ __('Recent Students') }}</h2><a href="{{ \App\Filament\Resources\Users\UserResource::getUrl() }}">{{ __('View All') }}</a></header>
                <ul class="cla-list">
                    @forelse($recentStudents as $student)
                        <li><span class="cla-ini">{{ mb_substr($student->name, 0, 1) }}</span><span class="cla-who"><b>{{ $student->name }}</b><small>{{ $student->email }}</small></span><small>{{ $student->created_at->translatedFormat('M j, Y') }}</small><span class="cla-badge {{ $student->status === 'active' ? 'ok' : 'bad' }}">{{ __(ucfirst($student->status)) }}</span></li>
                    @empty
                        <li class="cla-empty">{{ __('No students yet.') }}</li>
                    @endforelse
                </ul>
            </section>
            <section class="cla-card">
                <header><h2><x-filament::icon icon="heroicon-o-bolt" />{{ __('Recent Activity') }}</h2></header>
                <ul class="cla-list">
                    @forelse($activity as $item)
                        <li><span class="cla-act cla-tone-{{ $item['tone'] }}"><x-filament::icon :icon="$item['icon']" /></span><span class="cla-who"><b>{{ __($item['title']) }}</b><small>{{ $item['text'] }}</small></span><small>{{ $item['at']->diffForHumans() }}</small></li>
                    @empty
                        <li class="cla-empty">{{ __('Nothing has happened yet.') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</div>
</x-filament-panels::page>
