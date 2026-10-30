<aside class="auth-aside">
    <img class="auth-aside-photo" src="{{ asset('images/auth/login.webp') }}" alt="" width="1200" height="800" loading="lazy">
    <div class="auth-aside-body">
        <x-brand class="auth-aside-brand" />
        <h2>{{ __('Turn Your Creativity Into') }} <span>{{ __('Real Skills') }}</span></h2>
        <p>{{ __('Join a community of learners and master design, video editing, 3D, and more with practical, project-based courses.') }}</p>
        <ul class="auth-features">
            <li><span aria-hidden="true"><x-heroicon-s-play /></span><div><strong>{{ __('Expert-led courses') }}</strong>{{ __('Learn from real creators') }}</div></li>
            <li><span aria-hidden="true"><x-heroicon-o-computer-desktop /></span><div><strong>{{ __('Project-based learning') }}</strong>{{ __('Build a portfolio') }}</div></li>
            <li><span aria-hidden="true"><x-heroicon-s-user-group /></span><div><strong>{{ __('Learn at your own pace') }}</strong>{{ __('Anytime, anywhere') }}</div></li>
            <li><span aria-hidden="true"><x-heroicon-s-star /></span><div><strong>{{ __('Get certified') }}</strong>{{ __('Showcase your skills') }}</div></li>
        </ul>
    </div>
</aside>
