<?php

return [
    // No gateway or checkout is enabled by installing the application.
    // Instructor workspace when no admin has chosen yet (Admin -> Site settings decides after that).
    'instructor_portal_default' => (bool) env('INSTRUCTOR_PORTAL_DEFAULT', false),
    // Staff must use two-step sign-in (authenticator app). Only the automated tests switch this off.
    'require_two_factor' => (bool) env('REQUIRE_TWO_FACTOR', true),
    'payments_enabled' => (bool) env('PAYMENTS_ENABLED', false),
    // Full path to mysqldump when it isn't on the PATH (e.g. C:/xampp/mysql/bin/mysqldump.exe).
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),
];
