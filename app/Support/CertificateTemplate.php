<?php

namespace App\Support;

use App\Models\SiteSetting;

/** Admin-editable wording and styling for printed certificates, stored in site_settings. */
class CertificateTemplate
{
    public const DEFAULTS = [
        'heading' => 'Certificate of Completion',
        'presented' => 'This certificate is proudly presented to',
        'body' => 'for successfully completing the course',
        'signer_name' => 'Creative Lab Academy',
        'signer_title' => 'Academy Director',
        'footer' => 'A non-accredited record of course completion.',
        'accent' => '#7138F5',
    ];

    public static function get(): array
    {
        $saved = SiteSetting::where('key', 'certificate_template')->first()?->value ?? [];

        return array_merge(self::DEFAULTS, array_intersect_key(is_array($saved) ? $saved : [], self::DEFAULTS));
    }

    public static function save(array $values): void
    {
        SiteSetting::updateOrCreate(['key' => 'certificate_template'], ['value' => array_intersect_key($values, self::DEFAULTS)]);
    }
}
