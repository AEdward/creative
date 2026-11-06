<?php

return [
    // No gateway or checkout is enabled by installing the application.
    // Instructor workspace when no admin has chosen yet (Admin -> Site settings decides after that).
    'instructor_portal_default' => (bool) env('INSTRUCTOR_PORTAL_DEFAULT', false),
    // Staff must use two-step sign-in (authenticator app). Only the automated tests switch this off.
    'require_two_factor' => (bool) env('REQUIRE_TWO_FACTOR', true),
    // Private path of the admin panel (e.g. hub-k7m2x9). Keep it out of the code: set ADMIN_PATH in .env.
    'admin_path' => env('ADMIN_PATH', 'admin'),
    'payments_enabled' => (bool) env('PAYMENTS_ENABLED', false),
    // Full path to mysqldump when it isn't on the PATH (e.g. C:/xampp/mysql/bin/mysqldump.exe).
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),
];
