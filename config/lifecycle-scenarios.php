<?php

declare(strict_types=1);

return [

    'defaults' => [
        'issuer_id' => (int) env('XCHANGE_LIFECYCLE_ISSUER_ID', 1),
        'wallet_id' => (int) env('XCHANGE_LIFECYCLE_WALLET_ID', 1),
        'amount' => (float) env('XCHANGE_LIFECYCLE_AMOUNT', 25),
        'currency' => env('XCHANGE_LIFECYCLE_CURRENCY', 'PHP'),

        'system_user_mobile' => env('XCHANGE_LIFECYCLE_SYSTEM_USER_MOBILE', '09178251991'),

        'mobile' => env('XCHANGE_LIFECYCLE_MOBILE', '639171234567'),
        'bank_code' => env('XCHANGE_LIFECYCLE_BANK_CODE', 'GXCHPHM2XXX'),
        'account_number' => env('XCHANGE_LIFECYCLE_ACCOUNT_NUMBER', '09173011987'),

        'timeout' => (int) env('XCHANGE_LIFECYCLE_TIMEOUT', 180),
        'poll' => (int) env('XCHANGE_LIFECYCLE_POLL', 10),

        'system_user_email' => env('XCHANGE_LIFECYCLE_SYSTEM_USER_EMAIL', env('SYSTEM_USER_ID')),
        'test_user_email' => env('XCHANGE_LIFECYCLE_TEST_USER_EMAIL', 'lester@hurtado.ph'),
        'test_user_mobile' => env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987'),

        'system_float' => (float) env('XCHANGE_LIFECYCLE_SYSTEM_FLOAT', 1_000_000),
        'user_float' => (float) env('XCHANGE_LIFECYCLE_USER_FLOAT', 10_000),

        'user_model' => env('XCHANGE_LIFECYCLE_USER_MODEL', \App\Models\User::class),
    ],

    'seeders' => [
        'system_user' => env('XCHANGE_LIFECYCLE_SEEDER_SYSTEM_USER'),
        'test_user' => env('XCHANGE_LIFECYCLE_SEEDER_TEST_USER'),
        'system_wallet' => env('XCHANGE_LIFECYCLE_SEEDER_SYSTEM_WALLET'),
        'test_wallet' => env('XCHANGE_LIFECYCLE_SEEDER_TEST_WALLET'),
        'instruction_items' => env('XCHANGE_LIFECYCLE_SEEDER_INSTRUCTION_ITEMS'),
    ],

    'scenarios' => [

        /*
        |--------------------------------------------------------------------------
        | Legacy lifecycle scenarios
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Contract-bridge lifecycle scenarios
        |--------------------------------------------------------------------------
        */

        'secret_required' => [
            'label' => 'Secret Required',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [
                'validation' => [
                    'secret' => 'ABC123',
                ],
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [],
            'meta' => [
                'family' => 'contract',
                'tags' => ['secret'],
            ],
            'attempts' => [
                'wrong_secret_fails' => [
                    'claim' => [
                        'secret' => 'WRONG-SECRET',
                    ],
                    'expect' => [
                        'status' => 'failed',
                        'message_contains' => ['secret'],
                    ],
                ],
                'correct_secret_succeeds' => [
                    'claim' => [
                        'secret' => 'ABC123',
                    ],
                    'expect' => [
                        'status' => 'succeeded',
                    ],
                ],
            ],
            'expect' => [
                'tariffs' => ['cash'],
            ],
        ],

        'mobile_locked' => [
            'label' => 'Mobile Locked',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [
                'validation' => [
                    'mobile' => '639171234567',
                ],
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [],
            'meta' => [
                'family' => 'contract',
                'tags' => ['mobile'],
            ],
            'attempts' => [
                'wrong_mobile_fails' => [
                    'claim' => [
                        'mobile' => '639179999999',
                    ],
                    'expect' => [
                        'status' => 'failed',
                        'message_contains' => ['mobile'],
                    ],
                ],
                'correct_mobile_succeeds' => [
                    'claim' => [
                        'mobile' => '639171234567',
                    ],
                    'expect' => [
                        'status' => 'succeeded',
                    ],
                ],
            ],
            'expect' => [
                'tariffs' => ['cash'],
            ],
        ],

        'bio_inputs_required' => [
            'label' => 'Bio Inputs Required',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => ['name', 'email', 'birth_date'],
            ],
            'feedback' => [],
            'meta' => [
                'family' => 'contract',
                'tags' => ['bio', 'presence'],
            ],
            'attempts' => [
                'missing_fields_fail' => [
                    'claim' => [
                        'inputs' => [
                            'name' => 'Juan Dela Cruz',
                        ],
                    ],
                    'expect' => [
                        'status' => 'failed',
                        'message_contains' => ['email', 'birth date'],
                    ],
                ],
                'complete_fields_succeed' => [
                    'claim' => [
                        'inputs' => [
                            'name' => 'Juan Dela Cruz',
                            'email' => 'juan@example.com',
                            'birth_date' => '1990-01-01',
                        ],
                    ],
                    'expect' => [
                        'status' => 'succeeded',
                    ],
                ],
            ],
            'expect' => [
                'tariffs' => ['cash'],
            ],
        ],

        'otp_required' => [
            'label' => 'OTP Required',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => ['otp'],
            ],
            'validation' => [
                'otp' => [
                    'required' => true,
                    'on_failure' => 'block',
                ],
            ],
            'feedback' => [],
            'meta' => [
                'family' => 'contract',
                'tags' => ['otp', 'presence', 'semantic'],
            ],
            'attempts' => [
                'missing_otp_fails' => [
                    'claim' => [
                        'inputs' => [],
                    ],
                    'expect' => [
                        'status' => 'failed',
                    ],
                ],
                'unverified_otp_fails' => [
                    'claim' => [
                        'inputs' => [
                            'otp' => [
                                'otp_code' => '123456',
                                'verified' => false,
                            ],
                        ],
                    ],
                    'expect' => [
                        'status' => 'failed',
                    ],
                ],
                'verified_otp_succeeds' => [
                    'claim' => [
                        'inputs' => [
                            'otp' => [
                                'otp_code' => '123456',
                                'verified' => true,
                                'verified_at' => '2026-04-19T10:30:00+08:00',
                            ],
                        ],
                    ],
                    'expect' => [
                        'status' => 'succeeded',
                    ],
                ],
            ],
            'expect' => [
                'tariffs' => ['cash', 'otp'],
            ],
        ],

        'signature_required' => [
            'label' => 'Signature Required',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => ['signature'],
            ],
            'feedback' => [],
            'meta' => [
                'family' => 'contract',
                'tags' => ['signature', 'presence'],
            ],
            'attempts' => [
                'missing_signature_fails' => [
                    'claim' => [
                        'inputs' => [],
                    ],
                    'expect' => [
                        'status' => 'failed',
                    ],
                ],
                'provided_signature_succeeds' => [
                    'claim' => [
                        'inputs' => [
                            'signature' => 'data:image/png;base64,DEMO_SIGNATURE',
                        ],
                    ],
                    'expect' => [
                        'status' => 'succeeded',
                    ],
                ],
            ],
            'expect' => [
                'tariffs' => ['cash', 'signature'],
            ],
        ],

        'selfie_required' => [
            'label' => 'Selfie Required',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => ['selfie'],
            ],
            'feedback' => [],
            'meta' => [
                'family' => 'contract',
                'tags' => ['selfie', 'presence'],
            ],
            'attempts' => [
                'missing_selfie_fails' => [
                    'claim' => [
                        'inputs' => [],
                    ],
                    'expect' => [
                        'status' => 'failed',
                    ],
                ],
                'provided_selfie_succeeds' => [
                    'claim' => [
                        'inputs' => [
                            'selfie' => 'data:image/jpeg;base64,DEMO_SELFIE',
                        ],
                    ],
                    'expect' => [
                        'status' => 'succeeded',
                    ],
                ],
            ],
            'expect' => [
                'tariffs' => ['cash', 'selfie'],
            ],
        ],

        'location_required' => [
            'label' => 'Location Required',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => ['location'],
            ],
            'feedback' => [],
            'meta' => [
                'family' => 'contract',
                'tags' => ['location', 'presence'],
            ],
            'attempts' => [
                'missing_location_fails' => [
                    'claim' => [
                        'inputs' => [],
                    ],
                    'expect' => [
                        'status' => 'failed',
                        'message_contains' => ['location'],
                    ],
                ],
                'provided_location_succeeds' => [
                    'claim' => [
                        'inputs' => [
                            'location' => [
                                'lat' => 14.5995,
                                'lng' => 120.9842,
                            ],
                        ],
                    ],
                    'expect' => [
                        'status' => 'succeeded',
                    ],
                ],
            ],
            'expect' => [
                'tariffs' => ['cash', 'location'],
            ],
        ],

        'location_radius' => [
            'label' => 'Location Radius',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => ['location'],
            ],
            'validation' => [
                'location' => [
                    'required' => true,
                    'target_lat' => 14.5995,
                    'target_lng' => 120.9842,
                    'radius_meters' => 100,
                    'on_failure' => 'block',
                ],
            ],
            'feedback' => [],
            'meta' => [
                'family' => 'contract',
                'tags' => ['location', 'radius', 'semantic'],
            ],
            'attempts' => [
                'outside_radius_fails' => [
                    'claim' => [
                        'inputs' => [
                            'location' => [
                                'lat' => 14.6095,
                                'lng' => 120.9942,
                            ],
                        ],
                    ],
                    'expect' => [
                        'status' => 'failed',
                    ],
                ],
                'inside_radius_succeeds' => [
                    'claim' => [
                        'inputs' => [
                            'location' => [
                                'lat' => 14.5995,
                                'lng' => 120.9842,
                            ],
                        ],
                    ],
                    'expect' => [
                        'status' => 'succeeded',
                    ],
                ],
            ],
            'expect' => [
                'tariffs' => ['cash', 'location'],
            ],
        ],

        'starts_future' => [
            'label' => 'Starts Future',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'starts_at' => '2026-04-20T01:00:00+08:00',
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [],
            'meta' => [
                'family' => 'contract',
                'tags' => ['time', 'starts_at'],
            ],
            'attempts' => [
                'before_start_fails' => [
                    'claim' => [],
                    'expect' => [
                        'status' => 'failed',
                    ],
                ],
            ],
            'expect' => [
                'tariffs' => ['cash'],
            ],
        ],

        'expired_voucher' => [
            'label' => 'Expired Voucher',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'expires_at' => '2026-04-19T21:00:00+08:00',
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [],
            'meta' => [
                'family' => 'contract',
                'tags' => ['time', 'expires_at'],
            ],
            'attempts' => [
                'after_expiry_fails' => [
                    'claim' => [],
                    'expect' => [
                        'status' => 'failed',
                    ],
                ],
            ],
            'expect' => [
                'tariffs' => ['cash'],
            ],
        ],

        'kyc_required_unapproved' => [
            'label' => 'KYC Required Unapproved',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => ['kyc'],
            ],
            'feedback' => [],
            'meta' => [
                'family' => 'contract',
                'tags' => ['kyc', 'presence', 'contact'],
            ],
            'claim' => [
                'inputs' => [
                    'kyc' => [
                        'transaction_id' => 'MOCK-KYC-123',
                        'status' => 'approved',
                        'name' => 'Juan Dela Cruz',
                        'id_number' => 'ABC123456',
                        'id_type' => 'National ID',
                    ],
                ],
            ],
            'expect' => [
                'status' => 'failed',
            ],
        ],

        'kyc_required_approved' => [
            'label' => 'KYC Required Approved',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => ['kyc'],
            ],
            'feedback' => [],
            'meta' => [
                'family' => 'contract',
                'tags' => ['kyc', 'presence', 'contact'],
            ],
            'claim' => [
                'inputs' => [
                    'kyc' => [
                        'transaction_id' => 'MOCK-KYC-123',
                        'status' => 'approved',
                        'name' => 'Juan Dela Cruz',
                        'id_number' => 'ABC123456',
                        'id_type' => 'National ID',
                    ],
                ],
            ],
            'expect' => [
                'status' => 'succeeded',
            ],
        ],

        'reconciliation_review_required' => [
            'label' => 'Reconciliation Review Required',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [],
            'meta' => [
                'family' => 'reconciliation',
                'tags' => ['reconciliation', 'review', 'provider'],
            ],
            'metadata' => [
                'lifecycle' => [
                    'reconciliation_mode' => 'review_required',
                ],
            ],
            'claim' => [],
            'expect' => [
                'status' => 'succeeded',
            ],
        ],

        'reconciliation_provider_failed_recorded' => [
            'label' => 'Reconciliation Provider Failed Recorded',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [],
            'meta' => [
                'family' => 'reconciliation',
                'tags' => ['reconciliation', 'failure', 'provider'],
            ],
            'metadata' => [
                'lifecycle' => [
                    'reconciliation_mode' => 'provider_failed_recorded',
                ],
            ],
            'claim' => [],
            'expect' => [
                'status' => 'succeeded',
            ],
        ],

        'reconciliation_resolved_success' => [
            'label' => 'Reconciliation Resolved Success',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [],
            'meta' => [
                'family' => 'reconciliation',
                'tags' => ['reconciliation', 'resolve', 'success'],
            ],
            'metadata' => [
                'lifecycle' => [
                    'reconciliation_mode' => 'resolve_success',
                ],
            ],
            'claim' => [],
            'expect' => [
                'status' => 'succeeded',
            ],
        ],

        'reconciliation_failed_pending_review' => [
            'label' => 'Reconciliation Failed Pending Review',
            'amount' => 25,
            'currency' => 'PHP',
            'cash' => [],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [],
            'meta' => [
                'family' => 'reconciliation',
                'tags' => ['reconciliation', 'resolve', 'failed'],
            ],
            'metadata' => [
                'lifecycle' => [
                    'reconciliation_mode' => 'resolve_failed',
                ],
            ],
            'claim' => [],
            'expect' => [
                'status' => 'succeeded',
            ],
        ],
    ],
];
