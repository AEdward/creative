<?php

namespace App\Support;

/**
 * Where the admin panel lives. Either its own subdomain (ADMIN_DOMAIN, e.g. creativeadmin2026.creativelabet.com,
 * panel at its root) or a private path on the main site (ADMIN_PATH). Build every admin link through here.
 */
class Admin
{
    public static function domain(): ?string
    {
        $domain = trim((string) config('academy.admin_domain', ''), " /\t");

        return $domain === '' ? null : strtolower(preg_replace('#^https?://#i', '', $domain));
    }

    /** Panel path: empty on its own subdomain, otherwise ADMIN_PATH (default "admin"). */
    public static function path(): string
    {
        if (self::domain()) {
            return '';
        }
        $path = trim((string) config('academy.admin_path', 'admin'), '/');

        return $path === '' ? 'admin' : $path;
    }

    public static function url(string $page = ''): string
    {
        $page = ltrim($page, '/');
        if ($domain = self::domain()) {
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

            return $scheme.'://'.$domain.($page !== '' ? '/'.$page : '');
        }

        return url(self::path().($page !== '' ? '/'.$page : ''));
    }

    public static function isAdminHost(string $host): bool
    {
        return self::domain() !== null && strcasecmp($host, self::domain()) === 0;
    }
}
