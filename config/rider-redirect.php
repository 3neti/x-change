<?php

return [
    'enabled' => env('X_CHANGE_RIDER_REDIRECT_ENABLED', false),
    // Host => shared secret, configured by the host application. Never publish actual keys.
    'receipt_keys' => [],
    'receipt_clock_skew_seconds' => 300,
];
