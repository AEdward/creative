<x-layout :title="$course->title.' assessments'"><section class="section"><div class="container"><p class="eyebrow">Practice and progress</p><h1>{{ $course->title }}</h1>
@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="notice" role="alert">{{ $errors->first() }}</div>@endif
@forelse($assessments as $assessment)<article class="learning-card"><h2>{{ $assessment->title }}</h2><p>{{ $assessment->instructions }}</p><p>{{ $assessment->required ? 'Required for certificate' : 'Optional practice' }}</p>
@if($assessment->type === 'quiz')<p>Pass: {{ $assessment->pass_percent }}%. Maximum {{ $assessment->attempt_limit }} attempts.</p>
@foreach($attempts->where('assessment_id',$assessment->id) as $attempt)<p>Attempt {{ $loop->iteration }}: {{ $attempt->score }}% — {{ $attempt->passed ? 'Passed' : 'Not passed' }}</p>@endforeach
@if($attempts->where('assessment_id',$assessment->id)->count() < $assessment->attempt_limit)<form method="post" action="{{ route('assessments.attempt',[$course,$assessment]) }}">@csrf
@foreach($assessment->questions as $index => $question)<fieldset><legend>{{ $question['prompt'] }}</legend>@foreach($question['options'] as $choice => $label)<label><input required type="radio" name="answers[{{ $index }}]" value="{{ $choice }}"> {{ $label }}</label><br>@endforeach</fieldset>@endforeach
<button class="button" type="submit">Submit attempt</button></form>@endif
@else<form method="post" enctype="multipart/form-data" action="{{ route('assignments.submit',[$course,$assessment]) }}">@csrf<label>Assignment file (PDF, JPEG, PNG or ZIP; 10 MB maximum)<input required type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.zip"></label><button class="button" type="submit">Submit for review</button></form>
@foreach($submissions->where('assessment_id',$assessment->id) as $submission)<p><a href="{{ route('assignments.download',$submission) }}">{{ $submission->original_name }}</a> — {{ $submission->status }} — {{ $submission->feedback }}</p>@endforeach
@endif</article>@empty<p>Your instructor has not added assessments yet.</p>@endforelse
<form method="post" action="{{ route('certificates.issue',$course) }}">@csrf<p>Certificates must be enabled by your instructor. Complete all current published lessons and required assessments before requesting one.</p><button class="button" type="submit">Request completion certificate</button></form></div></section></x-layout>
