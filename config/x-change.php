<?php

return [
    'redeem' => [
        'reference' => [
            'label' => env('REDEEM_REFERENCE_LABEL', 'Reference'),
            'value' => env('REDEEM_REFERENCE_VALUE', '')
        ],
        'success' => [
            'redirect_timeout' => env('RIDER_REDIRECT_TIMEOUT', 5000)
        ],
        'auto_feedback' => (bool) env('REDEEM_AUTO_FEEDBACK', true)
    ],
];
