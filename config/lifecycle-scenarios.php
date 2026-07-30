<?php

declare(strict_types=1);
use App\Models\User;

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

        'user_model' => env('XCHANGE_LIFECYCLE_USER_MODEL', User::class),
    ],

    'seeders' => [
        'system_user' => env('XCHANGE_LIFECYCLE_SEEDER_SYSTEM_USER'),
        'test_user' => env('XCHANGE_LIFECYCLE_SEEDER_TEST_USER'),
        'system_wallet' => env('XCHANGE_LIFECYCLE_SEEDER_SYSTEM_WALLET'),
        'test_wallet' => env('XCHANGE_LIFECYCLE_SEEDER_TEST_WALLET'),
        'instruction_items' => env('XCHANGE_LIFECYCLE_SEEDER_INSTRUCTION_ITEMS'),
    ],

    'qrph_funding_simulation' => [
        'enabled' => (bool) env(
            'XCHANGE_LIFECYCLE_QRPH_SIMULATION_ENABLED',
            env('APP_ENV') !== 'production',
        ),
    ],

    'treasury_basic_cash' => [
        'enabled' => (bool) env(
            'XCHANGE_LIFECYCLE_TREASURY_BASIC_CASH_ENABLED',
            env('APP_ENV') !== 'production',
        ),
        'allowed_environments' => ['local', 'testing'],
        'required_tables' => [
            'provider_funding_observations',
            'x_change_funding_intents',
            'x_change_funding_settlements',
            'treasury_inventories',
            'treasury_inventory_operations',
            'treasury_positions',
            'treasury_position_operations',
            'x_change_commercial_sales',
            'x_change_commercial_allocations',
            'vouchers',
        ],
    ],

    'treasury_live_basic_cash' => [
        'enabled' => (bool) env(
            'XCHANGE_LIFECYCLE_TREASURY_LIVE_BASIC_CASH_ENABLED',
            env('APP_ENV') === 'local',
        ),
        'allowed_environments' => array_values(array_filter(array_map(
            'trim',
            explode(
                ',',
                (string) env(
                    'XCHANGE_LIFECYCLE_TREASURY_LIVE_BASIC_CASH_ENVIRONMENTS',
                    'local,testing',
                ),
            ),
        ))),
        'lock_seconds' => (int) env(
            'XCHANGE_LIFECYCLE_TREASURY_LIVE_BASIC_CASH_LOCK_SECONDS',
            600,
        ),
        'lock_wait_seconds' => (int) env(
            'XCHANGE_LIFECYCLE_TREASURY_LIVE_BASIC_CASH_LOCK_WAIT_SECONDS',
            5,
        ),
        'required_tables' => [
            'x_change_lifecycle_money_runs',
            'disbursement_reconciliations',
            'treasury_inventories',
            'treasury_inventory_operations',
            'treasury_positions',
            'treasury_position_operations',
            'vouchers',
        ],
    ],

    'scenarios' => [

        /*
        |--------------------------------------------------------------------------
        | Turnkey onboarding scenarios
        |--------------------------------------------------------------------------
        */

        'turnkey_mobile_boot' => [
            'label' => 'Turnkey Mobile Boot',
            'description' => 'Verifies the mobile-first host install surface without issuing a voucher.',
            'category' => 'smoke',
            'tags' => ['turnkey', 'onboarding', 'mobile'],
            'mode' => 'turnkey_onboarding',
            'mobile' => env('XCHANGE_LIFECYCLE_TURNKEY_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'turnkey' => [
                'provider_topology' => env('XCHANGE_PROVIDER_TOPOLOGY', 'manual'),
                'checks' => [
                    'mobile_first_auth',
                    'fortify_mobile_username',
                    'user_mobile',
                    'provider_topology',
                    'issuer_onboarding',
                ],
            ],
        ],

        'turnkey_bank_onboarding_required' => [
            'label' => 'Turnkey Bank Onboarding Required',
            'description' => 'Verifies the onboarding gateway maps redemption bank onboarding requirements.',
            'category' => 'smoke',
            'tags' => ['turnkey', 'onboarding', 'bank'],
            'mode' => 'turnkey_onboarding',
            'mobile' => env('XCHANGE_LIFECYCLE_TURNKEY_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'turnkey' => [
                'provider_topology' => env('XCHANGE_PROVIDER_TOPOLOGY', 'manual'),
                'checks' => [
                    'mobile_first_auth',
                    'user_mobile',
                    'provider_topology',
                    'bank_onboarding_required',
                ],
            ],
        ],

        'turnkey_provider_link_ready' => [
            'label' => 'Turnkey Provider Link Ready',
            'description' => 'Verifies provider account links can transition to ready without live provider calls.',
            'category' => 'smoke',
            'tags' => ['turnkey', 'onboarding', 'provider'],
            'mode' => 'turnkey_onboarding',
            'mobile' => env('XCHANGE_LIFECYCLE_TURNKEY_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'turnkey' => [
                'provider' => env('XCHANGE_PROVIDER', 'manual'),
                'provider_topology' => env('XCHANGE_PROVIDER_TOPOLOGY', 'manual'),
                'provisioning_mode' => 'ledger_wallet',
                'checks' => [
                    'provider_runtime_settings',
                    'provider_link_ready',
                ],
            ],
        ],

        'turnkey_provider_link_pending_blocks' => [
            'label' => 'Turnkey Provider Link Pending Blocks',
            'description' => 'Verifies pending provider account links do not satisfy readiness.',
            'category' => 'smoke',
            'tags' => ['turnkey', 'onboarding', 'provider'],
            'mode' => 'turnkey_onboarding',
            'mobile' => env('XCHANGE_LIFECYCLE_TURNKEY_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'turnkey' => [
                'provider' => env('XCHANGE_PROVIDER', 'manual'),
                'provider_topology' => env('XCHANGE_PROVIDER_TOPOLOGY', 'manual'),
                'provisioning_mode' => 'ledger_wallet',
                'checks' => [
                    'provider_link_pending_blocks',
                ],
            ],
        ],

        'turnkey_netbank_bank_account_ready' => [
            'label' => 'Turnkey NetBank Bank Account Ready',
            'description' => 'Verifies NetBank pooled-ledger bank-account readiness through fake provisioning.',
            'category' => 'smoke',
            'tags' => ['turnkey', 'onboarding', 'netbank'],
            'mode' => 'turnkey_onboarding',
            'mobile' => env('XCHANGE_LIFECYCLE_TURNKEY_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'turnkey' => [
                'provider' => 'netbank',
                'provider_topology' => 'netbank',
                'checks' => [
                    'provider_runtime_settings',
                    'netbank_bank_account_ready',
                ],
            ],
        ],

        'turnkey_netbank_ledger_wallet_ready' => [
            'label' => 'Turnkey NetBank Ledger Wallet Ready',
            'description' => 'Verifies NetBank pooled-ledger wallet readiness through local wallet provisioning.',
            'category' => 'smoke',
            'tags' => ['turnkey', 'onboarding', 'netbank'],
            'mode' => 'turnkey_onboarding',
            'mobile' => env('XCHANGE_LIFECYCLE_TURNKEY_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'turnkey' => [
                'provider' => 'netbank',
                'provider_topology' => 'netbank',
                'checks' => [
                    'provider_runtime_settings',
                    'netbank_ledger_wallet_ready',
                ],
            ],
        ],

        'turnkey_paynamics_wallet_fake_provisioned' => [
            'label' => 'Turnkey Paynamics Wallet Fake Provisioned',
            'description' => 'Verifies Paynamics customer-wallet provisioning response mapping without live provider calls.',
            'category' => 'smoke',
            'tags' => ['turnkey', 'onboarding', 'paynamics'],
            'mode' => 'turnkey_onboarding',
            'mobile' => env('XCHANGE_LIFECYCLE_TURNKEY_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'turnkey' => [
                'provider' => 'paynamics',
                'provider_topology' => 'paynamics',
                'checks' => [
                    'provider_runtime_settings',
                    'paynamics_wallet_fake_provisioned',
                ],
            ],
        ],

        'turnkey_paynamics_bank_account_fake_linked' => [
            'label' => 'Turnkey Paynamics Bank Account Fake Linked',
            'description' => 'Verifies Paynamics fake bank-account response mapping without live provider calls.',
            'category' => 'smoke',
            'tags' => ['turnkey', 'onboarding', 'paynamics'],
            'mode' => 'turnkey_onboarding',
            'mobile' => env('XCHANGE_LIFECYCLE_TURNKEY_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'turnkey' => [
                'provider' => 'paynamics',
                'provider_topology' => 'paynamics',
                'bank_code' => env('XCHANGE_LIFECYCLE_BANK_CODE', 'GXCHPHM2XXX'),
                'bank_name' => env('XCHANGE_LIFECYCLE_BANK_NAME', 'GCash'),
                'account_number' => env('XCHANGE_LIFECYCLE_ACCOUNT_NUMBER', '09173011987'),
                'checks' => [
                    'provider_runtime_settings',
                    'paynamics_bank_account_fake_linked',
                ],
            ],
        ],

        'turnkey_paynamics_wallet_link_ready' => [
            'label' => 'Turnkey Paynamics Wallet Link Ready',
            'description' => 'Verifies Paynamics wallet provisioning produces a ready x-change provider account link.',
            'category' => 'smoke',
            'tags' => ['turnkey', 'onboarding', 'paynamics'],
            'mode' => 'turnkey_onboarding',
            'mobile' => env('XCHANGE_LIFECYCLE_TURNKEY_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'turnkey' => [
                'provider' => 'paynamics',
                'provider_topology' => 'paynamics',
                'checks' => [
                    'provider_runtime_settings',
                    'paynamics_wallet_link_ready',
                ],
            ],
        ],

        'turnkey_issuer_blocks_missing_provider_wallet' => [
            'label' => 'Turnkey Issuer Blocks Missing Provider Wallet',
            'description' => 'Verifies Paynamics issuer readiness blocks when no provider customer wallet link is ready.',
            'category' => 'smoke',
            'tags' => ['turnkey', 'onboarding', 'provider', 'guard'],
            'mode' => 'turnkey_onboarding',
            'mobile' => env('XCHANGE_LIFECYCLE_TURNKEY_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'turnkey' => [
                'provider' => 'paynamics',
                'checks' => [
                    'issuer_missing_provider_wallet_blocks',
                ],
            ],
        ],

        'turnkey_issuer_allows_ready_provider_wallet' => [
            'label' => 'Turnkey Issuer Allows Ready Provider Wallet',
            'description' => 'Verifies Paynamics issuer readiness passes when provider customer wallet link is ready.',
            'category' => 'smoke',
            'tags' => ['turnkey', 'onboarding', 'provider', 'guard'],
            'mode' => 'turnkey_onboarding',
            'mobile' => env('XCHANGE_LIFECYCLE_TURNKEY_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'turnkey' => [
                'provider' => 'paynamics',
                'checks' => [
                    'issuer_ready_provider_wallet_allows',
                ],
            ],
        ],

        'turnkey_claim_blocks_missing_bank_account' => [
            'label' => 'Turnkey Claim Blocks Missing Bank Account',
            'description' => 'Verifies claim readiness blocks when bank-account readiness is required but missing.',
            'category' => 'smoke',
            'tags' => ['turnkey', 'onboarding', 'provider', 'guard'],
            'mode' => 'turnkey_onboarding',
            'mobile' => env('XCHANGE_LIFECYCLE_TURNKEY_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'turnkey' => [
                'provider' => 'netbank',
                'checks' => [
                    'claim_missing_bank_account_blocks',
                ],
            ],
        ],

        'turnkey_claim_resumes_after_provider_account_ready' => [
            'label' => 'Turnkey Claim Resumes After Provider Account Ready',
            'description' => 'Verifies claim readiness passes when required bank-account link is ready.',
            'category' => 'smoke',
            'tags' => ['turnkey', 'onboarding', 'provider', 'guard'],
            'mode' => 'turnkey_onboarding',
            'mobile' => env('XCHANGE_LIFECYCLE_TURNKEY_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'turnkey' => [
                'provider' => 'netbank',
                'checks' => [
                    'claim_ready_provider_account_allows',
                ],
            ],
        ],

        'turnkey_basic_cash_mobile' => [
            'label' => 'Turnkey Basic Cash Mobile',
            'description' => 'Issues and claims a basic cash Pay Code through the mobile-first lifecycle path.',
            'category' => 'smoke',
            'tags' => ['turnkey', 'mobile', 'cash'],
            'provider' => 'manual',
            'amount' => 12.50,
            'currency' => 'PHP',
            'mobile' => env('XCHANGE_LIFECYCLE_TURNKEY_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
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

        'account_management_funding_destinations_demo' => [
            'label' => 'Account Management Funding Destinations',
            'description' => 'Demonstrates shared and dedicated funding destination controls in a rollback-only simulation.',
            'category' => 'demo',
            'tags' => ['demo', 'account-management', 'funding', 'rollback-only'],
            'mode' => 'account_management',
            'api_executable' => false,
        ],

        'qrph_funding_existing_mobile_demo' => [
            'label' => 'QR Ph Funding Existing Mobile',
            'description' => 'Exercises a signed QR Ph funding simulation for an existing verified mobile and rolls every change back.',
            'category' => 'demo',
            'tags' => ['demo', 'funding', 'qrph', 'mobile', 'rollback-only'],
            'mode' => 'qrph_funding_simulation',
            'mobile' => env('XCHANGE_LIFECYCLE_QRPH_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'amount_minor' => 2_500,
            'api_executable' => false,
        ],

        'qrph_funding_unknown_mobile_onboarding_demo' => [
            'label' => 'QR Ph Funding Unknown Mobile Onboarding',
            'description' => 'Stops an unknown payer before payment, completes verified mobile onboarding, then exercises the signed funding pipeline with full rollback.',
            'category' => 'demo',
            'tags' => ['demo', 'funding', 'qrph', 'mobile', 'onboarding', 'rollback-only'],
            'mode' => 'qrph_unknown_mobile_onboarding',
            'mobile' => env('XCHANGE_LIFECYCLE_QRPH_UNKNOWN_MOBILE', '09175550123'),
            'amount_minor' => 2_500,
            'api_executable' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Live provider verification scenarios
        |--------------------------------------------------------------------------
        |
        | These scenarios are disabled unless the operator passes --live-provider
        | and enables x-change.provider_runtime.lifecycle.allow_live_provider_scenarios.
        |
        */

        'provider_paynamics_wallet_live_provision' => [
            'label' => 'Paynamics Wallet Live Provision',
            'description' => 'Verifies live Paynamics customer-wallet provisioning through the provider gateway.',
            'category' => 'provider',
            'tags' => ['provider', 'live-provider', 'paynamics'],
            'mode' => 'live_provider_verification',
            'mobile' => env('XCHANGE_LIFECYCLE_PROVIDER_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'live_provider' => [
                'payload' => [
                    'provider' => 'paynamics',
                    'mode' => 'wallet_create',
                    'purpose' => 'IssuePayCode',
                    'notification_url' => env('XCHANGE_PAYNAMICS_PROVISIONING_NOTIFICATION_URL'),
                ],
            ],
        ],

        'provider_paynamics_bank_account_live_link' => [
            'label' => 'Paynamics Bank Account Live Link',
            'description' => 'Verifies live Paynamics wallet creation and bank-account linking through the provider gateway.',
            'category' => 'provider',
            'tags' => ['provider', 'live-provider', 'paynamics'],
            'mode' => 'live_provider_verification',
            'mobile' => env('XCHANGE_LIFECYCLE_PROVIDER_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'live_provider' => [
                'payload' => [
                    'provider' => 'paynamics',
                    'mode' => 'bank_account_link',
                    'purpose' => 'BankOnboardingRequired',
                    'bank_code' => env('XCHANGE_LIFECYCLE_BANK_CODE', 'GXCHPHM2XXX'),
                    'bank_name' => env('XCHANGE_LIFECYCLE_BANK_NAME', 'GCash'),
                    'account_number' => env('XCHANGE_LIFECYCLE_ACCOUNT_NUMBER', '09173011987'),
                    'notification_url' => env('XCHANGE_PAYNAMICS_PROVISIONING_NOTIFICATION_URL'),
                ],
            ],
        ],

        'provider_netbank_source_account_live_readiness' => [
            'label' => 'NetBank Source Account Live Readiness',
            'description' => 'Verifies NetBank source-account readiness through the provider gateway.',
            'category' => 'provider',
            'tags' => ['provider', 'live-provider', 'netbank'],
            'mode' => 'live_provider_verification',
            'mobile' => env('XCHANGE_LIFECYCLE_PROVIDER_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
            'live_provider' => [
                'payload' => [
                    'provider' => 'netbank',
                    'mode' => 'bank_account_link',
                    'purpose' => 'BankOnboardingRequired',
                    'bank_code' => env('XCHANGE_LIFECYCLE_BANK_CODE', 'GXCHPHM2XXX'),
                    'account_number_masked' => env('XCHANGE_LIFECYCLE_ACCOUNT_NUMBER_MASKED', '*******1987'),
                ],
            ],
        ],

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

        'onboarding_voucher' => [
            'label' => 'Onboarding Voucher',
            'description' => 'Issues and claims one onboarding Pay Code through the explicit workflow descriptor and account-provisioning execution driver.',
            'category' => 'onboarding',
            'tags' => ['onboarding', 'voucher', 'claim-workflow', 'execution-engine'],
            'mode' => 'onboarding_voucher',
            'lifecycle' => [
                'issuer_email' => env(
                    'XCHANGE_LIFECYCLE_ONBOARDING_VOUCHER_ISSUER_EMAIL',
                    'onboarding-voucher-issuer@example.test',
                ),
                'issuer_mobile' => env(
                    'XCHANGE_LIFECYCLE_ONBOARDING_VOUCHER_ISSUER_MOBILE',
                    '09179990000',
                ),
                'funding_boundary' => 'isolated_compatibility_wallet',
            ],
            'amount' => 1,
            'currency' => 'PHP',
            'mobile' => env('XCHANGE_LIFECYCLE_ONBOARDING_VOUCHER_MOBILE', '09179990001'),
            'onboarding' => [
                'enabled' => true,
                'name' => env('XCHANGE_LIFECYCLE_ONBOARDING_VOUCHER_NAME', 'Onboarding Voucher Recipient'),
                'email' => env('XCHANGE_LIFECYCLE_ONBOARDING_VOUCHER_EMAIL', 'onboarding-voucher-09179990001@example.test'),
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [],
            'claim' => [],
            'expect' => [
                'tariffs' => ['cash', 'onboarding.enabled'],
            ],
        ],

        'treasury_basic_cash' => [
            'label' => 'Treasury Basic Cash',
            'description' => 'Funds an Account from verified provider evidence, issues the canonical basic_cash Pay Code, and demonstrates the resulting liability and issuance capacity under one rollback boundary.',
            'category' => 'smoke',
            'tags' => ['treasury', 'funding', 'basic_cash', 'rollback-only'],
            'mode' => 'treasury_basic_cash',
            'treasury' => [
                'base_scenario' => 'basic_cash',
                'provider' => 'netbank',
                'connection' => 'netbank-primary',
                'funding_amount_minor' => 10_000,
                'legal_entity_reference' => 'legal-entity:x-change:lifecycle',
            ],
        ],

        'treasury_live_basic_cash' => [
            'label' => 'Treasury Live Basic Cash',
            'description' => 'Synchronizes authoritative provider liquidity, exposes system and Account positions, issues one open-slice Pay Code, and claims it in three live provider transfers under a replay-safe run reference.',
            'category' => 'live-provider',
            'tags' => ['treasury', 'accounting', 'basic_cash', 'open-slice', 'live-provider', 'netbank'],
            'mode' => 'treasury_live_basic_cash',
            'sequential' => [
                'wait_between_claims_seconds' => 10,
            ],
            'metadata' => [
                'flow_type' => 'disbursable',
            ],
            'amount' => 150,
            'currency' => 'PHP',
            'cash' => [
                'amount' => 150,
                'currency' => 'PHP',
                'settlement_rail' => 'INSTAPAY',
                'fee_strategy' => 'absorb',
                'slice_mode' => 'open',
                'max_slices' => 3,
                'min_withdrawal' => 25,
                'validation' => [
                    'country' => 'PH',
                ],
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [],
            'execution' => [
                'schema' => 'voucher.execution.v1',
                'driver' => 'x_change_live_cash',
                'metadata' => [
                    'x_change_live_cash' => [
                        'claim_owner' => 'x-change',
                        'provider' => 'netbank',
                        'settlement_rail' => 'INSTAPAY',
                    ],
                ],
            ],
            'execution_runtime' => [
                'live_provider' => true,
                'confirm_live_transfer' => true,
                'operation' => [
                    'operation' => 'claim_transfer',
                    'claim' => [
                        'mobile' => env('XCHANGE_LIFECYCLE_TURNKEY_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
                        'recipient_country' => 'PH',
                        'bank_account' => [
                            'bank_code' => env('XCHANGE_LIFECYCLE_BANK_CODE', 'GXCHPHM2XXX'),
                            'account_number' => env('XCHANGE_LIFECYCLE_ACCOUNT_NUMBER', '09173011987'),
                        ],
                        'inputs' => [],
                    ],
                    'poll' => [
                        'timeout' => 180,
                        'poll' => 10,
                        'accept_pending' => false,
                    ],
                ],
            ],
            'claims' => [
                'claim_1_withdraw' => [
                    'wait_before_seconds' => 0,
                    'claim' => [
                        'amount' => 75,
                    ],
                    'expect' => [
                        'status' => 'succeeded',
                        'claim_type' => 'withdraw',
                    ],
                ],
                'claim_2_withdraw' => [
                    'wait_before_seconds' => 10,
                    'claim' => [
                        'amount' => 50,
                    ],
                    'expect' => [
                        'status' => 'succeeded',
                        'claim_type' => 'withdraw',
                    ],
                ],
                'claim_3_withdraw' => [
                    'wait_before_seconds' => 10,
                    'claim' => [
                        'amount' => 25,
                    ],
                    'expect' => [
                        'status' => 'succeeded',
                        'claim_type' => 'withdraw',
                    ],
                ],
            ],
            'treasury' => [
                'provider' => 'netbank',
                'connection' => 'netbank-primary',
                'report_connections' => [
                    'netbank-primary',
                    'paynamics-primary',
                ],
            ],
            'expect' => [
                'tariffs' => ['cash'],
            ],
        ],

        'money_semantics_voucher_liability_demo' => [
            'label' => 'Money Semantics Voucher Liability Demo',
            'description' => 'Demonstrates debit-at-issuance wallet behavior and read-only outstanding Pay Code liability snapshots.',
            'category' => 'smoke',
            'tags' => ['demo', 'money-semantics', 'liability', 'read-only'],
            'amount' => 25,
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

        'divisible_open_two_slices' => [
            'label' => 'Divisible Open Two Slices',
            'metadata' => [
                'flow_type' => 'disbursable',
            ],
            'amount' => 300,
            'currency' => 'PHP',
            'cash' => [
                'amount' => 300,
                'currency' => 'PHP',
                'validation' => [
                    'country' => 'PH',
                ],
                'settlement_rail' => 'INSTAPAY',
                'fee_strategy' => 'absorb',
                'slice_mode' => 'open',
                'max_slices' => 3,
                'min_withdrawal' => 50,
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [
                'mobile' => null,
                'email' => null,
                'webhook' => null,
            ],
            'rider' => [
                'message' => null,
                'url' => null,
                'redirect_timeout' => null,
                'splash' => null,
                'splash_timeout' => null,
                'og_source' => null,
            ],
            'claims' => [
                'claim_1_withdraw' => [
                    'claim' => [
                        'amount' => 100,
                    ],
                    'expect' => [
                        'status' => 'succeeded',
                        'claim_type' => 'withdraw',
                    ],
                ],
                'claim_2_withdraw' => [
                    'wait_before_seconds' => 10,
                    'claim' => [
                        'amount' => 50,
                    ],
                    'expect' => [
                        'status' => 'succeeded',
                        'claim_type' => 'withdraw',
                    ],
                ],
            ],
        ],

        'divisible_open_three_slices_enforced_interval' => [
            'label' => 'Divisible Open Three Slices (Enforced Interval)',

            'sequential' => [
                'wait_between_claims_seconds' => 10,
            ],

            'metadata' => [
                'flow_type' => 'disbursable',
            ],

            'amount' => 150,
            'currency' => 'PHP',

            'cash' => [
                'amount' => 150,
                'currency' => 'PHP',
                'validation' => [
                    'country' => 'PH',
                ],
                'settlement_rail' => 'INSTAPAY',
                'fee_strategy' => 'absorb',
                'slice_mode' => 'open',
                'max_slices' => 3,
                'min_withdrawal' => 25,
            ],

            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
            'mobile' => '639171234567',

            'claims' => [
                'claim_1_withdraw' => [
                    'wait_before_seconds' => 0,
                    'claim' => [
                        'amount' => 75,
                    ],
                    'expect' => [
                        'status' => 'succeeded',
                        'claim_type' => 'withdraw',
                    ],
                ],

                'claim_2_withdraw' => [
                    'wait_before_seconds' => 10,
                    'claim' => [
                        'amount' => 50,
                    ],
                    'expect' => [
                        'status' => 'succeeded',
                        'claim_type' => 'withdraw',
                    ],
                ],

                'claim_3_withdraw' => [
                    'wait_before_seconds' => 10,
                    'claim' => [
                        'amount' => 25,
                    ],
                    'expect' => [
                        'status' => 'succeeded',
                        'claim_type' => 'withdraw',
                    ],
                ],
            ],
        ],

        'collectible_basic_payment' => [
            'label' => 'Collectible Basic Payment QR',

            'metadata' => [
                'flow_type' => 'collectible',
            ],

            'amount' => 100,
            'currency' => 'PHP',

            'issuer' => [
                'email' => 'system@example.test',
                'mobile' => '639178251991',
                'wallet_balance' => 1_000_000,
            ],

            'cash' => [
                'settlement_rail' => 'INSTAPAY',
                'validation' => [
                    'secret' => null,
                    'mobile' => null,
                    'payable' => null,
                    'country' => 'PH',
                    'location' => null,
                    'radius' => null,
                ],
            ],

            'inputs' => [
                'fields' => [],
            ],

            'feedback' => [
                'email' => 'example@example.com',
                'mobile' => '09171234567',
                'webhook' => 'https://example.com/webhook',
            ],

            'rider' => [
                'message' => null,
                'url' => null,
                'redirect_timeout' => null,
                'splash' => null,
                'splash_timeout' => null,
                'og_source' => null,
            ],

            'count' => 1,
            'prefix' => 'PAY',
            'mask' => '****',
            'ttl' => null,

            /**
             * 🔥 THIS IS THE FIX
             */
            'claims' => [
                [
                    'name' => 'default',

                    'claim_mobile' => '639171234567',

                    'claim_payload' => [
                        'mobile' => '639171234567',
                        'recipient_country' => 'PH',
                        'bank_account' => [
                            'bank_code' => 'GXCHPHM2XXX',
                            'account_number' => '09173011987',
                        ],
                        'inputs' => [],
                    ],

                    /**
                     * ✅ EXPECT FAILURE (NOT SUCCESS)
                     */
                    'expect' => [
                        'status' => 'failed',
                        'message_contains' => [
                            'cannot execute outward claims',
                        ],
                    ],
                ],
            ],
        ],

        'settlement_philhealth_bst' => [
            'label' => 'Settlement — PhilHealth BST',
            'flow_type' => 'settlement',
            'mode' => 'settlement_envelope_evaluation',

            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],

            'attempts' => [
                [
                    'name' => 'blocked_missing_amount_verification',
                    'settlement' => [
                        'payload' => [
                            'patient_name' => 'Juan Dela Cruz',
                            'patient_mobile' => '09171234567',
                        ],
                        'checklist' => [
                            'amount_verified' => false,
                        ],
                    ],
                    'expect' => [
                        'status' => 'blocked',
                        'missing' => ['amount_verified'],
                    ],
                ],
                [
                    'name' => 'ready_after_amount_verification',
                    'settlement' => [
                        'payload' => [
                            'patient_name' => 'Juan Dela Cruz',
                            'patient_mobile' => '09171234567',
                        ],
                        'checklist' => [
                            'amount_verified' => true,
                        ],
                    ],
                    'expect' => [
                        'status' => 'ready',
                        'satisfied' => ['payload_present', 'amount_verified'],
                    ],
                ],
            ],
        ],

        'settlement_philhealth_bst_three_party' => [
            'label' => 'Settlement — PhilHealth BST Three-Party Flow',
            'mode' => 'settlement_three_party_flow',

            'amount' => 20000,
            'currency' => 'PHP',
            'claim_mobile' => '639171234567',

            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
                'settlement_role_model' => 'three_party',
                'settlement_issuer_role' => 'hospital',
                'settlement_attestor_role' => 'patient',
                'settlement_payer_role' => 'philhealth',
                'settlement_recipient_role' => 'hospital',
            ],

            'hospital' => [
                'name' => 'Demo General Hospital',
                'gross_bill' => 30000,
                'patient_payable' => 10000,
                'philhealth_cover' => 20000,
            ],

            'patient' => [
                'name' => 'Juan Dela Cruz',
                'mobile' => '09171234567',
                'birth_date' => '1985-01-15',
            ],

            'payer' => [
                'name' => 'PhilHealth',
                'provider' => 'manual',
                'provider_reference' => 'PHILHEALTH-BST-CLAIM-001',
            ],

            'phases' => [
                'issue' => [
                    'expect' => [
                        'issuer_role' => 'hospital',
                        'amount' => 20000,
                    ],
                ],

                'attest' => [
                    'payload' => [
                        'mobile' => '639171234567',
                        'inputs' => [
                            'name' => 'Juan Dela Cruz',
                            'birth_date' => '1985-01-15',
                            'signature' => 'demo-signature',
                        ],
                        'settlement_attestation' => true,
                    ],
                    'expect' => [
                        'role' => 'patient',
                        'claim_type' => 'redeem',
                        'disbursement' => false,
                    ],
                ],

                'evaluate_before_completion' => [
                    'settlement' => [
                        'payload' => [
                            'patient_name' => 'Juan Dela Cruz',
                            'patient_mobile' => '09171234567',
                            'diagnosis' => 'Demo diagnosis',
                            'procedure' => 'Demo procedure',
                            'gross_bill' => 30000,
                            'patient_payable' => 10000,
                            'philhealth_cover' => 20000,
                        ],
                        'checklist' => [
                            'amount_verified' => false,
                        ],
                    ],
                    'expect' => [
                        'ready' => false,
                        'missing' => ['amount_verified'],
                    ],
                ],

                'complete_envelope' => [
                    'settlement' => [
                        'payload' => [
                            'patient_name' => 'Juan Dela Cruz',
                            'patient_mobile' => '09171234567',
                            'diagnosis' => 'Demo diagnosis',
                            'procedure' => 'Demo procedure',
                            'gross_bill' => 30000,
                            'patient_payable' => 10000,
                            'philhealth_cover' => 20000,
                        ],
                        'documents' => [
                            'loa' => 'demo://documents/loa.pdf',
                            'mdr' => 'demo://documents/mdr.pdf',
                        ],
                        'checklist' => [
                            'amount_verified' => true,
                        ],
                    ],
                    'expect' => [
                        'ready' => true,
                        'satisfied' => ['payload_present', 'amount_verified'],
                    ],
                ],

                'settle' => [
                    'payment' => [
                        'provider' => 'manual',
                        'provider_reference' => 'PHILHEALTH-BST-CLAIM-001',
                        'amount' => 20000,
                        'currency' => 'PHP',
                        'status' => 'succeeded',
                    ],
                    'expect' => [
                        'payer_role' => 'philhealth',
                        'recipient_role' => 'hospital',
                        'status' => 'collected',
                    ],
                ],
            ],
        ],

        'execution_settlement_envelope_contract_demo' => [
            'label' => 'Execution Engine Settlement Envelope Contract Demo',
            'description' => 'Issues a voucher with canonical settlement_envelope execution metadata and executes it through the voucher-owned engine using the x-change gateway adapter.',
            'category' => 'execution-engine',
            'tags' => ['execution-engine', 'settlement-envelope', 'contract'],
            'mode' => 'execution_engine_contract_demo',
            'amount' => 100,
            'currency' => 'PHP',
            'cash' => [
                'settlement_rail' => 'INSTAPAY',
                'fee_strategy' => 'absorb',
                'validation' => [
                    'country' => 'PH',
                ],
            ],
            'inputs' => [
                'fields' => ['mobile'],
            ],
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
            'execution' => [
                'schema' => 'voucher.execution.v1',
                'driver' => 'settlement_envelope',
                'metadata' => [
                    'settlement_envelope' => [
                        'reference' => 'ENV-LIFECYCLE-001',
                        'driver' => 'philhealth-bst',
                        'readiness_gate' => 'settleable',
                        'child_generation' => 'from_envelope',
                        'auto_redeem_children' => false,
                        'fallback_to_claim' => true,
                        'payload' => [
                            'patient_name' => 'Juan Dela Cruz',
                            'patient_mobile' => '09171234567',
                        ],
                        'checklist' => [
                            'amount_verified' => true,
                        ],
                    ],
                ],
            ],
            'execution_runtime' => [
                'operation' => [
                    'operation' => 'execute',
                ],
            ],
        ],

        'execution_stored_value_contract_demo' => [
            'label' => 'Execution Engine Stored Value Contract Demo',
            'description' => 'Issues a voucher with canonical stored_value execution metadata and exercises activate/spend through the voucher-owned engine using the x-change gateway adapter.',
            'category' => 'execution-engine',
            'tags' => ['execution-engine', 'stored-value', 'contract'],
            'mode' => 'execution_engine_contract_demo',
            'amount' => 100,
            'currency' => 'PHP',
            'cash' => [
                'settlement_rail' => 'INSTAPAY',
                'fee_strategy' => 'absorb',
                'validation' => [
                    'country' => 'PH',
                ],
            ],
            'inputs' => [
                'fields' => ['mobile'],
            ],
            'execution' => [
                'schema' => 'voucher.execution.v1',
                'driver' => 'stored_value',
                'metadata' => [
                    'stored_value' => [
                        'reference' => 'SV-LIFECYCLE-001',
                        'initial_balance' => 10000,
                        'max_balance' => 10000,
                        'replenishable' => true,
                        'otp_required_above' => 5000,
                    ],
                ],
            ],
            'execution_runtime' => [
                'sequence' => [
                    [
                        'operation' => 'activate',
                    ],
                    [
                        'operation' => 'spend',
                        'amount' => 2500,
                        'merchant_reference' => 'TRAIN-001',
                    ],
                ],
            ],
        ],

        'execution_engine_basic_cash_live_transfer' => [
            'label' => 'Execution Engine Basic Cash Live Transfer',
            'description' => 'Issues a basic cash Pay Code with an explicit x_change_live_cash execution instruction and executes it through the voucher-owned engine into the existing x-change live payout path.',
            'category' => 'execution-engine',
            'tags' => ['execution-engine', 'cash', 'live-provider', 'netbank', 'gcash'],
            'mode' => 'execution_engine_contract_demo',
            'amount' => 12.50,
            'currency' => 'PHP',
            'cash' => [
                'settlement_rail' => 'INSTAPAY',
                'fee_strategy' => 'absorb',
                'validation' => [
                    'country' => 'PH',
                ],
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [],
            'execution' => [
                'schema' => 'voucher.execution.v1',
                'driver' => 'x_change_live_cash',
                'metadata' => [
                    'x_change_live_cash' => [
                        'claim_owner' => 'x-change',
                        'provider' => 'netbank',
                        'settlement_rail' => 'INSTAPAY',
                    ],
                ],
            ],
            'execution_runtime' => [
                'live_provider' => true,
                'confirm_live_transfer' => true,
                'operation' => [
                    'operation' => 'claim_transfer',
                    'claim' => [
                        'mobile' => env('XCHANGE_LIFECYCLE_TURNKEY_MOBILE', env('XCHANGE_LIFECYCLE_TEST_USER_MOBILE', '09173011987')),
                        'recipient_country' => 'PH',
                        'bank_account' => [
                            'bank_code' => env('XCHANGE_LIFECYCLE_BANK_CODE', 'GXCHPHM2XXX'),
                            'account_number' => env('XCHANGE_LIFECYCLE_ACCOUNT_NUMBER', '09173011987'),
                        ],
                        'inputs' => [],
                    ],
                    'poll' => [
                        'timeout' => 180,
                        'poll' => 10,
                        'accept_pending' => false,
                    ],
                ],
            ],
            'expect' => [
                'tariffs' => ['cash'],
            ],
        ],
    ],
];
