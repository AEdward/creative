@php $c = \App\Support\AcademySettings::all(); @endphp
<ul>
    @if(filled($c['contact_email']))<li>Email: <a href="mailto:{{ $c['contact_email'] }}">{{ $c['contact_email'] }}</a></li>@endif
    @if(filled($c['contact_phone']))<li>Phone: {{ $c['contact_phone'] }}</li>@endif
    @if(filled($c['contact_address']))<li>Address: {{ $c['contact_address'] }}</li>@endif
    <li>Online: the <a href="{{ route('contact') }}">contact page</a>, or Support in your account</li>
</ul>
