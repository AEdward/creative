<?php

namespace App\Support;

/**
 * The admin panel lives at a private, hard-to-guess path set in .env (ADMIN_PATH), so it isn't
 * advertised at /admin. Build every admin link through here.
 */
class Admin
{
    public static function path(): string
    {
        $path = trim((string) config('academy.admin_path', 'admin'), '/');

        return $path === '' ? 'admin' : $path;
    }

    public static function url(string $page = ''): string
    {
        return url(self::path().($page !== '' ? '/'.ltrim($page, '/') : ''));
    }
}
