@props(['label' => 'Creative Lab Academy home', 'onDark' => true])
@php $wordmark = 'images/brand/wordmark-on-'.($onDark ? 'dark' : 'light').'-60.webp'; @endphp
<a {{ $attributes->class('brand') }} href="{{ route('home') }}" aria-label="{{ $label }}">
    <img class="brand-mark" src="{{ asset('images/brand/logo-mark-176.webp') }}" alt="" width="44" height="44">
    @if(file_exists(public_path($wordmark)))<img class="brand-wordmark" src="{{ asset($wordmark) }}" alt="Creative Lab Academy" height="30">@else<span class="wordmark">Creative Lab<span>{{ __('Learn · Create · Grow') }}</span></span>@endif
</a>
