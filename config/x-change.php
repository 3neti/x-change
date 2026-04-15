<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Contracts\PricingServiceContract;
use LBHurtado\XChange\Contracts\SystemWalletResolverContract;
use LBHurtado\XChange\Contracts\TerminologyServiceContract;
use LBHurtado\XChange\Contracts\UserResolverContract;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Contracts\VoucherEntryRouteResolverContract;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\DisburseFlowStarterService;
use LBHurtado\XChange\Services\PayCodeIssuanceService;
use LBHurtado\XChange\Services\PricingService;
use LBHurtado\XChange\Services\SessionCompletionStore;
use LBHurtado\XChange\Services\TerminologyService;
use LBHurtado\XChange\Services\VoucherAccessService;
use LBHurtado\XChange\Services\VoucherEntryRouteService;
use LBHurtado\XChange\Support\Logging\NullAuditLogger;
use LBHurtado\XChange\Support\Resolvers\NullSystemWalletResolver;
//use LBHurtado\XChange\Support\Resolvers\NullUserResolver;

return [

    'product' => [
        'name' => env('XCHANGE_PRODUCT_NAME', 'X-Change'),
        'code' => env('XCHANGE_PRODUCT_CODE', 'x-change'),
        'default_currency' => env('XCHANGE_DEFAULT_CURRENCY', 'PHP'),
        'default_country' => env('XCHANGE_DEFAULT_COUNTRY', 'PH'),
    ],

    'terminology' => [
        'voucher' => env('XCHANGE_TERM_VOUCHER', 'Pay Code'),
        'voucher_code' => env('XCHANGE_TERM_VOUCHER_CODE', 'Pay Code'),
        'redeem' => env('XCHANGE_TERM_REDEEM', 'Claim'),
        'withdraw' => env('XCHANGE_TERM_WITHDRAW', 'Withdraw'),
        'wallet' => env('XCHANGE_TERM_WALLET', 'Wallet'),
        'account' => env('XCHANGE_TERM_ACCOUNT', 'Account'),
    ],

    'routes' => [
        'web' => env('XCHANGE_ROUTES_WEB', true),
        'api' => env('XCHANGE_ROUTES_API', true),

        'experimental' => [
            'enabled' => env('XCHANGE_ROUTES_EXPERIMENTAL_ENABLED', true),
            'prefix' => env('XCHANGE_ROUTES_EXPERIMENTAL_PREFIX', 'x'),
        ],

        'api_prefix' => env('XCHANGE_API_PREFIX', 'api/x'),
        'api_version' => env('XCHANGE_API_VERSION', 'v1'),

        'paths' => [
            'redeem' => env('XCHANGE_PATH_REDEEM', 'disburse'),
            'withdraw' => env('XCHANGE_PATH_WITHDRAW', 'withdraw'),
            'pay' => env('XCHANGE_PATH_PAY', 'pay'),
            'generate' => env('XCHANGE_PATH_GENERATE', 'pay-codes'),
            'estimate' => env('XCHANGE_PATH_ESTIMATE', 'pay-codes/estimate'),
            'wallets' => env('XCHANGE_PATH_WALLETS', 'wallets'),
            'users' => env('XCHANGE_PATH_USERS', 'users'),
        ],
    ],

    'api' => [
        'idempotency' => [
            'enabled' => env('XCHANGE_API_IDEMPOTENCY_ENABLED', true),
            'header' => env('XCHANGE_API_IDEMPOTENCY_HEADER', 'Idempotency-Key'),
            'ttl' => (int) env('XCHANGE_API_IDEMPOTENCY_TTL', 3600),
        ],

        'correlation' => [
            'enabled' => env('XCHANGE_API_CORRELATION_ENABLED', true),
            'header' => env('XCHANGE_API_CORRELATION_HEADER', 'X-Correlation-ID'),
        ],

        'response' => [
            'wrap_success' => env('XCHANGE_API_WRAP_SUCCESS', true),
            'wrap_errors' => env('XCHANGE_API_WRAP_ERRORS', true),
            'success_key' => env('XCHANGE_API_SUCCESS_KEY', 'success'),
            'data_key' => env('XCHANGE_API_DATA_KEY', 'data'),
            'meta_key' => env('XCHANGE_API_META_KEY', 'meta'),
            'message_key' => env('XCHANGE_API_MESSAGE_KEY', 'message'),
            'code_key' => env('XCHANGE_API_CODE_KEY', 'code'),
            'errors_key' => env('XCHANGE_API_ERRORS_KEY', 'errors'),
        ],
    ],

    'services' => [
        'user_resolver' => \LBHurtado\XChange\Services\ContextUserResolver::class,
        'voucher_access' => \LBHurtado\XChange\Services\VoucherAccessService::class,
        'entry_route' => \LBHurtado\XChange\Services\VoucherEntryRouteService::class,
        'disburse_flow' => \LBHurtado\XChange\Services\DisburseFlowStarterService::class,
        'completion_store' => \LBHurtado\XChange\Services\SessionCompletionStore::class,
        'terminology' => \LBHurtado\XChange\Services\TerminologyService::class,
        'api_response' => \LBHurtado\XChange\Services\ApiResponseFactory::class,
        'pricing' => \LBHurtado\XChange\Services\PricingService::class,
        'issuance' => \LBHurtado\XChange\Services\PayCodeIssuanceService::class,
        'wallet_access' => \LBHurtado\XChange\Services\WalletAccessService::class,
        'idempotency_store' => \LBHurtado\XChange\Services\CacheIdempotencyStore::class,
        'issuer_onboarding' => \LBHurtado\XChange\Services\DefaultIssuerOnboardingService::class,
        'wallet_provisioning' => \LBHurtado\XChange\Services\DefaultWalletProvisioningService::class,
        'issuer_resolver' => \LBHurtado\XChange\Support\Resolvers\DefaultIssuerResolver::class,
        'redemption_flow_preparation' => \LBHurtado\XChange\Services\DefaultRedemptionFlowPreparationService::class,
        'redemption_completion_context' => \LBHurtado\XChange\Services\DefaultRedemptionCompletionContextService::class,
        'redemption_completion_store' => \LBHurtado\XChange\Services\NullRedemptionCompletionStore::class,
        'claim_execution_factory' => \LBHurtado\XChange\Services\DefaultClaimExecutionFactory::class,
        'redemption_context_resolver' => \LBHurtado\XChange\Services\DefaultRedemptionContextResolverService::class,
        'redemption_validation' => \LBHurtado\XChange\Services\DefaultRedemptionValidationService::class,
        'redemption_processor' => \LBHurtado\XChange\Services\DefaultRedemptionProcessorService::class,
        'redemption_execution' => \LBHurtado\XChange\Services\DefaultRedemptionExecutionService::class,
        'withdrawal_validation' => \LBHurtado\XChange\Services\DefaultWithdrawalValidationService::class,
        'withdrawal_processor' => \LBHurtado\XChange\Services\DefaultWithdrawalProcessorService::class,
        'withdrawal_execution' => \LBHurtado\XChange\Services\DefaultWithdrawalExecutionService::class,
        'disbursement_reconciliation_store' => \LBHurtado\XChange\Services\DefaultDisbursementReconciliationStore::class,
        'disbursement_status_resolver' => \LBHurtado\XChange\Services\DefaultDisbursementStatusResolverService::class,
        'disbursement_status_fetcher' => \LBHurtado\XChange\Services\DefaultDisbursementStatusFetcherService::class,
        'disbursement_reconciliation' => \LBHurtado\XChange\Services\DefaultDisbursementReconciliationService::class,
    ],

    'service_contracts' => [
        \LBHurtado\XChange\Contracts\UserResolverContract::class => 'user_resolver',
        \LBHurtado\XChange\Contracts\VoucherAccessContract::class => 'voucher_access',
        \LBHurtado\XChange\Contracts\VoucherEntryRouteResolverContract::class => 'entry_route',
        \LBHurtado\XChange\Contracts\TerminologyServiceContract::class => 'terminology',
        \LBHurtado\XChange\Contracts\PricingServiceContract::class => 'pricing',
        \LBHurtado\XChange\Contracts\PayCodeIssuanceContract::class => 'issuance',
        \LBHurtado\XChange\Contracts\WalletAccessContract::class => 'wallet_access',
        \LBHurtado\XChange\Contracts\IdempotencyStoreContract::class => 'idempotency_store',
        \LBHurtado\XChange\Contracts\IssuerOnboardingContract::class => 'issuer_onboarding',
        \LBHurtado\XChange\Contracts\WalletProvisioningContract::class => 'wallet_provisioning',
        \LBHurtado\XChange\Contracts\IssuerResolverContract::class => 'issuer_resolver',
    ],

    'integrations' => [
        'system_wallet_resolver' => \LBHurtado\XChange\Support\Resolvers\NullSystemWalletResolver::class,
        'audit_logger' => \LBHurtado\XChange\Support\Logging\NullAuditLogger::class,
    ],

    'integration_contracts' => [
        SystemWalletResolverContract::class => 'system_wallet_resolver',
        AuditLoggerContract::class => 'audit_logger',
    ],

    'pricing' => [
        'currency' => env('XCHANGE_PRICING_CURRENCY', 'PHP'),

        'base_fee' => (float) env('XCHANGE_PRICING_BASE_FEE', 0.00),

        'components' => [
            'cash' => (float) env('XCHANGE_PRICE_CASH', 0.00),
            'kyc' => (float) env('XCHANGE_PRICE_KYC', 25.00),
            'otp' => (float) env('XCHANGE_PRICE_OTP', 2.00),
            'selfie' => (float) env('XCHANGE_PRICE_SELFIE', 5.00),
            'signature' => (float) env('XCHANGE_PRICE_SIGNATURE', 3.00),
            'location' => (float) env('XCHANGE_PRICE_LOCATION', 1.00),
            'webhook' => (float) env('XCHANGE_PRICE_WEBHOOK', 0.00),
            'email_feedback' => (float) env('XCHANGE_PRICE_EMAIL_FEEDBACK', 0.00),
            'sms_feedback' => (float) env('XCHANGE_PRICE_SMS_FEEDBACK', 0.00),
        ],

        'minimum_balance_enforced' => env('XCHANGE_PRICING_MINIMUM_BALANCE_ENFORCED', true),
    ],

    'onboarding' => [
//        'issuer_model' => env('XCHANGE_ONBOARDING_DEFAULT_ISSUER_MODEL', \LBHurtado\XChange\Tests\Fakes\User::class),
        'issuer_model' => env('XCHANGE_ONBOARDING_DEFAULT_ISSUER_MODEL', App\Models\User::class),
        'default_wallet_slug' => env('XCHANGE_ONBOARDING_DEFAULT_WALLET_SLUG', 'platform'),
        'default_wallet_name' => env('XCHANGE_ONBOARDING_DEFAULT_WALLET_NAME', 'Platform Wallet'),
    ],

    'redemption' => [
        'field_mappings' => [
            'full_name' => 'name',
            'date_of_birth' => 'birth_date',
            'otp_code' => 'otp',
        ],
    ],
    'payout' => [
        'provider' => \LBHurtado\PaymentGateway\Adapters\NetbankPayoutProvider::class,
        'wallet_proxy' => \LBHurtado\XChange\Services\SystemWalletProxy::class,
        'system_user_id' => env('XCHANGE_SYSTEM_USER_ID'),
        'system_user_column' => env('XCHANGE_SYSTEM_USER_COLUMN', 'id'),
        'system_wallet_slug' => env(
            'XCHANGE_SYSTEM_WALLET_SLUG',
            env('XCHANGE_ONBOARDING_DEFAULT_WALLET_SLUG', 'platform')
        ),
    ],
    'lifecycle' => [
        'enabled' => env('XCHANGE_LIFECYCLE_ENABLED', true),

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
            'basic_cash' => [
                'label' => 'Basic Cash',
                'amount' => 25,
                'currency' => 'PHP',
                'cash' => [],
                'inputs' => ['fields' => []],
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
                'inputs' => ['fields' => []],
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
                        'location' => ['lat' => 14.5995, 'lng' => 120.9842],
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
                'inputs' => ['fields' => []],
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
                'inputs' => ['fields' => []],
                'feedback' => [],
                'claim' => [],
                'expect' => [
                    'tariffs' => ['cash'],
                ],
            ],
        ],
    ],
];
