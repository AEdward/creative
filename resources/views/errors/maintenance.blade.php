<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('We’ll be back soon') }} · Creative Lab Academy</title>
    <link rel="icon" type="image/png" href="{{ asset('images/brand/favicon-32.png') }}">
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;font-family:Poppins,system-ui,-apple-system,"Segoe UI",sans-serif;background:radial-gradient(circle at 20% 10%,#2a1f5c 0,#0d1024 55%);color:#e9eaf3;text-align:center}
        main{max-width:520px}
        img{width:64px;height:64px;margin-bottom:20px}
        h1{font-size:clamp(1.8rem,5vw,2.6rem);margin:0 0 12px;color:#fff}
        p{line-height:1.7;margin:0 0 14px;color:#c4c8dc}
        .small{font-size:.9rem;color:#8d93ad}
        a{color:#b9a3ff}
        .bar{width:120px;height:4px;margin:26px auto 0;border-radius:4px;background:linear-gradient(90deg,#2563eb,#7c3aed);opacity:.8}
    </style>
</head>
<body>
<main>
    <img src="{{ asset('images/brand/logo-mark-176.webp') }}" alt="Creative Lab Academy">
    <h1>{{ __('We’ll be back soon') }}</h1>
    <p>{{ filled($message ?? '') ? $message : __('We’re making improvements to Creative Lab Academy and will be back shortly. Your courses and progress are safe.') }}</p>
    @php $email = \App\Support\AcademySettings::get('contact_email'); @endphp
    @if(filled($email))<p class="small">{{ __('Need help now?') }} <a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
    <div class="bar" aria-hidden="true"></div>
</main>
</body>
</html>
