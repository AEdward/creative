<?php

return [
    // No gateway or checkout is enabled by installing the application.
    'payments_enabled' => (bool) env('PAYMENTS_ENABLED', false),
];
