<x-layout :title="$course->title.' reviews'"><section class="section"><div class="container feature-page"><h1>{{ $course->title }} reviews</h1>
@include('engagement.status')
@if($average !== null)<p>Average rating: {{ number_format($average,1) }} / 5 — approved learner reviews only.</p>@else<p>{{ __('No approved reviews yet.') }}</p>@endif
@foreach($reviews as $review)<article class="learning-card"><h2>{{ $review->rating }} / 5</h2><p>{{ $review->body }}</p><p>{{ $review->user->name }} · {{ $review->created_at->format('M j, Y') }}</p></article>@endforeach
{{ $reviews->links() }}
@if($ownReview)<p>Your review status: {{ $ownReview->status }}.</p>@elseif($eligible)<form method="post" action="{{ route('reviews.store',$course) }}">@csrf<label>{{ __('Rating') }}<select name="rating" required>@foreach(range(1,5) as $rating)<option value="{{ $rating }}">{{ $rating }} / 5</option>@endforeach</select></label><label>{{ __('Your review') }}<textarea name="body" required minlength="3" maxlength="3000">{{ is_string(old('body')) ? old('body') : '' }}</textarea></label><p>{{ __('Reviews may require administrator approval before appearing publicly.') }}</p><button class="button">{{ __('Submit review') }}</button></form>@else<p>{{ __('Verified learners with a current enrollment and a completed published lesson may leave one review.') }}</p>@endif
</div></section></x-layout>
