@props(['label' => 'Creative Lab Academy home'])
@php $mark = file_exists(public_path('images/brand/logo-mark-176.webp')); @endphp
<a {{ $attributes->class('brand') }} href="{{ route('home') }}" aria-label="{{ $label }}">
    @if($mark)<img class="brand-mark" src="{{ asset('images/brand/logo-mark-176.webp') }}" alt="" width="44" height="44">@endif
    <span class="wordmark">Creative Lab<span>{{ __('Learn · Create · Grow') }}</span></span>
</a>
