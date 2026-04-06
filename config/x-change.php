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
        'user_resolver' => \LBHurtado\XChange\Services\AuthUserResolver::class,
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
];
