@props(['title', 'description', 'image' => null, 'type' => 'website', 'noindex' => false, 'jsonLd' => []])
@php
    $settings = \App\Support\AcademySettings::all();
    $shareImage = $image ?: route('share.site');
    $fullTitle = $title.' · Creative Lab Academy';
@endphp
<meta name="description" content="{{ \Illuminate\Support\Str::limit($description, 160, '…') }}">
@if($noindex)<meta name="robots" content="noindex, nofollow">@else<meta name="robots" content="index, follow, max-image-preview:large">@endif
<link rel="canonical" href="{{ url()->current() }}">
<meta property="og:site_name" content="Creative Lab Academy">
<meta property="og:locale" content="{{ app()->getLocale() === 'am' ? 'am_ET' : 'en_US' }}">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ \Illuminate\Support\Str::limit($description, 200, '…') }}">
<meta property="og:type" content="{{ $type }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ $shareImage }}">
<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $title }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $fullTitle }}">
<meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit($description, 200, '…') }}">
<meta name="twitter:image" content="{{ $shareImage }}">
@if(filled($settings['google_site_verification'] ?? ''))<meta name="google-site-verification" content="{{ $settings['google_site_verification'] }}">@endif
@if(filled($settings['bing_site_verification'] ?? ''))<meta name="msvalidate.01" content="{{ $settings['bing_site_verification'] }}">@endif
{!! \App\Support\Seo::script($jsonLd) !!}
