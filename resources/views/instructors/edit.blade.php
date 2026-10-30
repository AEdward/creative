<x-layout :title="__('Your instructor profile')">
<section class="feature-page"><div class="container">
<p class="eyebrow">{{ __('Your creative practice') }}</p><h1>{{ __('Your instructor profile.') }}</h1><p>{{ __('Choose what to share publicly. Your account email and phone are never displayed here.') }}</p>
@if(session('status'))<div class="notice" role="status">{{ __(session('status')) }}</div>@endif
@if($errors->any())<div class="alert" role="alert"><strong>{{ __('Please check your profile') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ __($error) }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('instructor.profile.update') }}" class="editor-panel">
@csrf @method('PATCH')
<div class="form-field"><label for="professional-title">{{ __('Professional title') }}</label><input id="professional-title" name="professional_title" maxlength="120" value="{{ is_string(old('professional_title')) ? old('professional_title') : $profile->professional_title }}"></div>
<div class="form-field"><label for="biography">{{ __('Biography') }}</label><textarea id="biography" name="biography" rows="8" maxlength="5000">{{ is_string(old('biography')) ? old('biography') : $profile->biography }}</textarea><p class="small">{{ __('Up to 5,000 characters. Text is displayed as written, without HTML formatting.') }}</p></div>
<div class="form-field"><label for="specialties">{{ __('Specialties') }}</label><textarea id="specialties" name="specialties" rows="4" maxlength="1000">{{ is_string(old('specialties')) ? old('specialties') : $profile->specialties }}</textarea><p class="small">{{ __('List one specialty per line, up to 1,000 characters.') }}</p></div>
<div class="form-field"><label><input type="checkbox" name="publicly_listed" value="1" @checked(in_array(old('publicly_listed', $profile->publicly_listed), [true, 1, '1'], true))> {{ __('I consent to publishing my name and instructor profile in the public directory.') }}</label><p class="small">{{ __('Only active, verified instructors appear. You may remove your listing at any time by clearing this choice and saving.') }}</p></div>
<div class="form-field"><label><input type="checkbox" name="show_photo" value="1" @checked(in_array(old('show_photo', $profile->show_photo), [true, 1, '1'], true))> {{ __('I also consent to showing my profile picture publicly while listed.') }}</label><p class="small">{{ __('Manage your picture in') }} <a href="{{ route('profile') }}">{{ __('account profile settings') }}</a>{{ __('. Listing your profile does not share your picture unless you select this choice.') }}</p></div>
<button class="button">{{ __('Save instructor profile') }}</button>
</form></div></section>
</x-layout>
