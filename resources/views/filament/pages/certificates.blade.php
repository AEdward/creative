<x-filament-panels::page>
<div class="cla-dash">
    <div class="cla-row cla-row-cert">
        <section class="cla-card">
            <header><h2><x-filament::icon icon="heroicon-o-pencil-square" />{{ __('Edit certificate') }}</h2></header>
            <form wire:submit="save" class="cla-form">
                @foreach(['heading' => 'Heading', 'presented' => 'Line above the name', 'body' => 'Line above the course', 'signer_name' => 'Signature name', 'signer_title' => 'Signature title', 'footer' => 'Footer note'] as $field => $label)
                    <label for="ct-{{ $field }}">{{ __($label) }}</label>
                    <input id="ct-{{ $field }}" type="text" wire:model.live.debounce.300ms="template.{{ $field }}" maxlength="200">
                    @error('template.'.$field)<p class="cla-error">{{ $message }}</p>@enderror
                @endforeach
                <label for="ct-accent">{{ __('Accent colour') }}</label>
                <div class="cla-color"><input id="ct-accent" type="color" wire:model.live="template.accent"><code>{{ $template['accent'] }}</code></div>
                @error('template.accent')<p class="cla-error">{{ $message }}</p>@enderror
                <div class="cla-actions"><button type="submit" class="cla-primary">{{ __('Save design') }}</button><button type="button" wire:click="resetToDefaults" class="cla-outline">{{ __('Reset to defaults') }}</button></div>
                <p class="cla-muted">{{ __('Changes apply to every certificate, including ones already issued. Learner name, course and date always come from the issued record.') }}</p>
            </form>
        </section>
        <section class="cla-card cla-cert-preview">
            <header><h2><x-filament::icon icon="heroicon-o-eye" />{{ __('Live preview') }}</h2></header>
            <x-certificate-sheet :template="$template" :learner="__('Learner Name')" :course="__('Course Title')" :issued="now()->toFormattedDateString()" :reference="null" />
        </section>
    </div>
    <section class="cla-card">
        <header><h2><x-filament::icon icon="heroicon-o-trophy" />{{ __('Issued certificates') }}</h2></header>
        <div class="cla-table-wrap"><table class="cla-table">
            <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Course') }}</th><th>{{ __('Issued') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($issued as $certificate)
                <tr><td>{{ $certificate->learner_name }}</td><td>{{ $certificate->course_title }}</td><td>{{ $certificate->issued_at->translatedFormat('M j, Y') }}</td>
                    <td><span class="cla-badge {{ $certificate->revoked_at ? 'bad' : 'ok' }}">{{ $certificate->revoked_at ? __('Revoked') : __('Valid') }}</span></td>
                    <td><a href="{{ route('certificates.print', $certificate) }}" target="_blank">{{ __('Open / revoke') }}</a></td></tr>
            @empty
                <tr><td colspan="5" class="cla-empty">{{ __('No certificates have been issued yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $issued->links() }}
    </section>
</div>
</x-filament-panels::page>
