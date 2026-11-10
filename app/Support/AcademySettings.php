<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Once;

/** Admin-editable business settings (contact, payments, VAT, storage, legal pages), stored in site_settings. */
class AcademySettings
{
    public const DEFAULTS = [
        'contact_email' => '',
        'contact_phone' => '',
        'contact_address' => '',
        'bank_checkout_enabled' => true,
        // Owner decision 2026-11-07: admins build every course; the instructor workspace stays closed unless switched on.
        'instructor_portal_enabled' => false,
        'vat_enabled' => false,
        'vat_percent' => 15,
        // Search engine ownership checks (the content value of the meta tag Google / Bing give you).
        'google_site_verification' => '',
        'bing_site_verification' => '',
        'storage_limit_gb' => 10,
        'storage_alert_percent' => 80,
        // Maintenance: visitors see a 'back soon' page; staff can still sign in and use everything.
        'maintenance_enabled' => false,
        'maintenance_message' => '',
        'privacy' => '',
        'terms' => '',
        'refund-policy' => '',
        'cookies' => '',
    ];

    public const LEGAL_PAGES = ['privacy', 'terms', 'refund-policy', 'cookies'];

    /** Read once per request (several checkout and layout lookups share it); save() clears it. */
    public static function all(): array
    {
        return once(function (): array {
            $saved = SiteSetting::where('key', 'academy_settings')->first()?->value ?? [];
            // Live default is "off"; the test suite opens it (phpunit.xml) because most legacy tests act as instructors.
            $defaults = ['instructor_portal_enabled' => (bool) config('academy.instructor_portal_default', false)] + self::DEFAULTS;

            return array_merge($defaults, array_intersect_key(is_array($saved) ? $saved : [], self::DEFAULTS));
        });
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    public static function save(array $values): void
    {
        SiteSetting::updateOrCreate(['key' => 'academy_settings'], ['value' => array_intersect_key(array_merge(self::all(), $values), self::DEFAULTS)]);
        Once::flush();
    }

    /** VAT in minor units on a VAT-inclusive price; zero when VAT is switched off. */
    public static function vatIncluded(int $totalMinor): int
    {
        $settings = self::all();
        if (! $settings['vat_enabled'] || $settings['vat_percent'] <= 0) {
            return 0;
        }

        return (int) round($totalMinor * $settings['vat_percent'] / (100 + $settings['vat_percent']));
    }
}
