<?php

return [
    // No gateway or checkout is enabled by installing the application.
    'payments_enabled' => (bool) env('PAYMENTS_ENABLED', false),
    // Full path to mysqldump when it isn't on the PATH (e.g. C:/xampp/mysql/bin/mysqldump.exe).
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),
];
