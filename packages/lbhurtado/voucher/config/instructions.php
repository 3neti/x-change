<?php

return [
    'cash' => [
        'amount' => env('INSTRUCTION_CASH_AMOUNT', 0.0),
        'currency' => env('INSTRUCTION_CASH_CURRENCY', 'PHP'),
        'validation_rules' => [
            'secret'   => env('DEFAULT_CASH_VALIDATION_RULES_SECRET'),
            'mobile'   => env('DEFAULT_CASH_VALIDATION_RULES_MOBILE'),
            'country'  => env('DEFAULT_CASH_VALIDATION_RULES_COUNTRY', 'PH'),
            'location' => env('DEFAULT_CASH_VALIDATION_RULES_LOCATION'),
            'radius'   => env('DEFAULT_CASH_VALIDATION_RULES_RADIUS'),
        ]
    ],
    'input_fields' => (function () {
        $raw = env('DEFAULT_INSTRUCTION_FIELDS');

        // if it's empty or not set, return []
        if (is_null($raw) || trim($raw) === '') {
            return [];
        }

        // otherwise explode into an array of strings
        return array_filter(explode(',', $raw), fn($item) => trim($item) !== '');})(),
    'feedback' => [
        'email'   => env('DEFAULT_FEEDBACK_EMAIL'),
        'mobile'  => env('DEFAULT_FEEDBACK_MOBILE'),
        'webhook' => env('DEFAULT_FEEDBACK_WEBHOOK'),
    ],
    'rider' => [
        'message' => env('DEFAULT_RIDER_MESSAGE'),
        'url'     => env('DEFAULT_RIDER_URL'),
    ],
    'count'  => env('DEFAULT_INSTRUCTION_COUNT', 1),
    'prefix' => env('DEFAULT_INSTRUCTION_PREFIX', ''),
    'mask'   => env('DEFAULT_INSTRUCTION_MASK', ''),
    'ttl'    => env('DEFAULT_INSTRUCTION_TTL', 12),
];
