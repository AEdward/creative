@props(['template', 'learner', 'course', 'issued', 'reference', 'revoked' => false])
<article class="cert-sheet" style="--cert-accent: {{ preg_match('/^#[0-9a-fA-F]{6}$/', $template['accent']) ? $template['accent'] : '#7138F5' }}">
    <div class="cert-border">
        <header class="cert-brand">
            @if(file_exists(public_path('images/brand/logo-mark-176.webp')))<img src="{{ asset('images/brand/logo-mark-176.webp') }}" alt="" width="56" height="56">@endif
            <span>Creative Lab Academy</span>
        </header>
        @if($revoked)<p class="cert-revoked" role="alert">{{ __('This certificate has been revoked.') }}</p>@endif
        <h1>{{ $template['heading'] }}</h1>
        <p class="cert-small">{{ $template['presented'] }}</p>
        <p class="cert-name">{{ $learner }}</p>
        <p class="cert-small">{{ $template['body'] }}</p>
        <p class="cert-course">{{ $course }}</p>
        <div class="cert-foot">
            <div><span class="cert-line">{{ $issued }}</span><small>{{ __('Date issued') }}</small></div>
            <div><span class="cert-line cert-sign">{{ $template['signer_name'] }}</span><small>{{ $template['signer_title'] }}</small></div>
        </div>
        <p class="cert-ref">{{ $template['footer'] }} · {{ __('Verify at') }} {{ $reference ? route('certificates.verify', $reference) : url('/certificates/verify/…') }}</p>
    </div>
</article>
