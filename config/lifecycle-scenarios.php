<?php

declare(strict_types=1);

return [

    'defaults' => [
        'issuer_id' => 1,
        'wallet_id' => 1,
        'amount' => 17,
        'currency' => 'PHP',

        'mobile' => '639171234567',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09173011987',

        'timeout' => 180,
        'poll' => 10,

        'system_user_email' => env('SYSTEM_USER_ID', 'admin@disburse.cash'),
        'test_user_email' => env('LIFECYCLE_TEST_USER_EMAIL', 'lester@hurtado.ph'),
        'test_user_mobile' => env('LIFECYCLE_TEST_USER_MOBILE', '09173011987'),

        'system_float' => 1_000_000,
        'user_float' => 10_000,
    ],

    'scenarios' => [

        'basic_cash' => [
            'label' => 'Basic Cash',
            'amount' => 12.50,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [],
            'claim' => [],
            'expect' => [
                'tariffs' => ['cash'],
            ],
        ],

        'bio' => [
            'label' => 'Bio Information',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => ['name', 'email', 'address', 'birth_date'],
            ],
            'feedback' => [],
            'claim' => [
                'inputs' => [
                    'name' => 'Juan Dela Cruz',
                    'email' => 'juan@example.com',
                    'address' => 'Makati City',
                    'birth_date' => '1990-01-01',
                ],
            ],
            'expect' => [
                'tariffs' => ['cash'],
            ],
        ],

        'otp' => [
            'label' => 'OTP',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => ['otp'],
            ],
            'feedback' => [],
            'claim' => [
                'inputs' => [
                    'otp' => '123456',
                ],
            ],
            'expect' => [
                'tariffs' => ['cash', 'otp'],
            ],
        ],

        'signature' => [
            'label' => 'Signature',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => ['signature'],
            ],
            'feedback' => [],
            'claim' => [
                'inputs' => [
                    'signature' => 'demo-signature',
                ],
            ],
            'expect' => [
                'tariffs' => ['cash', 'signature'],
            ],
        ],

        'location' => [
            'label' => 'Location',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => ['location'],
            ],
            'feedback' => [],
            'claim' => [
                'inputs' => [
                    'location' => [
                        'lat' => 14.5995,
                        'lng' => 120.9842,
                    ],
                ],
            ],
            'expect' => [
                'tariffs' => ['cash', 'location'],
            ],
        ],

        'selfie' => [
            'label' => 'Selfie',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => ['selfie'],
            ],
            'feedback' => [],
            'claim' => [
                'inputs' => [
                    'selfie' => 'demo-selfie',
                ],
            ],
            'expect' => [
                'tariffs' => ['cash', 'selfie'],
            ],
        ],

        'webhook' => [
            'label' => 'Webhook Feedback',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [
                'webhook' => 'https://example.test/webhook',
            ],
            'claim' => [],
            'expect' => [
                'tariffs' => ['cash', 'webhook'],
            ],
        ],

        'full_stack' => [
            'label' => 'Full Stack',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => ['name', 'email', 'address', 'birth_date', 'otp', 'signature', 'location', 'selfie'],
            ],
            'feedback' => [
                'webhook' => 'https://example.test/webhook',
            ],
            'claim' => [
                'inputs' => [
                    'name' => 'Juan Dela Cruz',
                    'email' => 'juan@example.com',
                    'address' => 'Makati City',
                    'birth_date' => '1990-01-01',
                    'otp' => '123456',
                    'signature' => 'demo-signature',
                    'location' => [
                        'lat' => 14.5995,
                        'lng' => 120.9842,
                    ],
                    'selfie' => 'demo-selfie',
                ],
            ],
            'expect' => [
                'tariffs' => ['cash', 'otp', 'signature', 'location', 'selfie', 'webhook'],
            ],
        ],

        'divisible_open' => [
            'label' => 'Divisible Open',
            'amount' => 300,
            'currency' => 'PHP',
            'cash' => [
                'divisible' => true,
                'withdrawable' => true,
                'slice_mode' => 'open',
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [],
            'claim' => [
                'amount' => 100,
            ],
            'expect' => [
                'tariffs' => ['cash'],
            ],
        ],

        'divisible_fixed' => [
            'label' => 'Divisible Fixed',
            'amount' => 300,
            'currency' => 'PHP',
            'cash' => [
                'divisible' => true,
                'withdrawable' => true,
                'slice_mode' => 'fixed',
                'max_slices' => 3,
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [],
            'claim' => [],
            'expect' => [
                'tariffs' => ['cash'],
            ],
        ],
    ],
];
