<x-layout :title="__('Completion certificate')">
<style>@media print { @page { size: A4 landscape; margin: 0; } body * { visibility: hidden !important; } .cert-sheet, .cert-sheet * { visibility: visible !important; } .cert-sheet { position: fixed; inset: 0; margin: 0; box-shadow: none; } }</style>
<section class="section"><div class="container">
    <x-certificate-sheet :template="\App\Support\CertificateTemplate::get()" :learner="$certificate->learner_name" :course="$certificate->course_title" :issued="$certificate->issued_at->toFormattedDateString()" :reference="$certificate->reference" :revoked="(bool) $certificate->revoked_at" />
    <div class="certificate-actions">
        <button class="button" type="button" onclick="window.print()">{{ __('Print / save as PDF') }}</button>
        <a class="inline-link" href="{{ route('certificates.verify', $certificate->reference) }}">{{ __('Verify this certificate') }}</a>
        <p class="small">{{ __('Use your browser’s Print command and choose “Save as PDF” to download this certificate.') }}</p>
    </div>
    @if(auth()->user()->role === 'admin' && ! $certificate->revoked_at)
        <form method="post" action="{{ route('certificates.revoke', $certificate) }}" class="editor-panel">@csrf<label>{{ __('Revocation reason') }}<textarea required name="reason" maxlength="2000"></textarea></label><button class="button" type="submit">{{ __('Revoke certificate') }}</button></form>
    @endif
</div></section>
</x-layout>
