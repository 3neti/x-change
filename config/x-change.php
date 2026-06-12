<?php

declare(strict_types=1);

use App\Models\User;
use LBHurtado\Instruction\Models\InstructionItem;
use LBHurtado\PaymentGateway\Adapters\NetbankPayoutProvider;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Contracts\IdempotencyStoreContract;
use LBHurtado\XChange\Contracts\IssuerOnboardingContract;
use LBHurtado\XChange\Contracts\IssuerResolverContract;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\PricingServiceContract;
use LBHurtado\XChange\Contracts\SystemWalletResolverContract;
use LBHurtado\XChange\Contracts\TerminologyServiceContract;
use LBHurtado\XChange\Contracts\UserResolverContract;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Contracts\VoucherEntryRouteResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\ApprovalHandlers\ManualApprovalRequirementHandler;
use LBHurtado\XChange\Services\ApprovalHandlers\OtpApprovalRequirementHandler;
use LBHurtado\XChange\Services\Base64PngVoucherPaymentQrRenderer;
use LBHurtado\XChange\Services\CacheIdempotencyStore;
use LBHurtado\XChange\Services\ContextUserResolver;
use LBHurtado\XChange\Services\DefaultClaimExecutionFactory;
use LBHurtado\XChange\Services\DefaultDisbursementReconciliationService;
use LBHurtado\XChange\Services\DefaultDisbursementReconciliationStore;
use LBHurtado\XChange\Services\DefaultDisbursementStatusFetcherService;
use LBHurtado\XChange\Services\DefaultDisbursementStatusResolverService;
use LBHurtado\XChange\Services\DefaultIssuerOnboardingService;
use LBHurtado\XChange\Services\DefaultRedemptionCompletionContextService;
use LBHurtado\XChange\Services\DefaultRedemptionContextResolverService;
use LBHurtado\XChange\Services\DefaultRedemptionExecutionService;
use LBHurtado\XChange\Services\DefaultRedemptionFlowPreparationService;
use LBHurtado\XChange\Services\DefaultRedemptionProcessorService;
use LBHurtado\XChange\Services\DefaultRedemptionValidationService;
use LBHurtado\XChange\Services\DefaultVoucherPaymentQrRenderer;
use LBHurtado\XChange\Services\DefaultWalletProvisioningService;
use LBHurtado\XChange\Services\DefaultWithdrawalExecutionService;
use LBHurtado\XChange\Services\DefaultWithdrawalProcessorService;
use LBHurtado\XChange\Services\DefaultWithdrawalValidationService;
use LBHurtado\XChange\Services\DisburseFlowStarterService;
use LBHurtado\XChange\Services\NullClaimOtpChallengeService;
use LBHurtado\XChange\Services\NullClaimOtpVerificationService;
use LBHurtado\XChange\Services\NullRedemptionCompletionStore;
use LBHurtado\XChange\Services\PayCodeIssuanceService;
use LBHurtado\XChange\Services\PaynamicsWithdrawalOtpApprovalService;
use LBHurtado\XChange\Services\PricingService;
use LBHurtado\XChange\Services\SessionCompletionStore;
use LBHurtado\XChange\Services\SystemWalletProxy;
use LBHurtado\XChange\Services\TerminologyService;
use LBHurtado\XChange\Services\VoucherAccessService;
use LBHurtado\XChange\Services\VoucherEntryRouteService;
use LBHurtado\XChange\Services\WalletAccessService;
use LBHurtado\XChange\Services\WithdrawalLifecycleService;
use LBHurtado\XChange\Services\WithdrawalOtpApprovalBackedClaimOtpChallengeService;
use LBHurtado\XChange\Services\WithdrawalOtpApprovalBackedClaimOtpVerificationService;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\AssertWithdrawalEligibilityStep;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\AuthorizeWithdrawalClaimantStep;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\AuthorizeWithdrawalOtpStep;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\AuthorizeWithdrawalPolicyStep;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\BlockSettlementVoucherWithdrawalStep;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\BuildWithdrawalPayoutRequestStep;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\BuildWithdrawalResultStep;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\ExecuteWithdrawalDisbursementStep;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\GuardWithdrawalRailStep;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\ResolveWithdrawalAmountStep;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\ResolveWithdrawalBankAccountStep;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\ResolveWithdrawalClaimantStep;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\WithdrawalWalletSettlementStep;
use LBHurtado\XChange\Support\Logging\CacheAuditLogger;
use LBHurtado\XChange\Support\Resolvers\DefaultIssuerResolver;
use LBHurtado\XChange\Support\Resolvers\NullSystemWalletResolver;

// use LBHurtado\XChange\Support\Resolvers\NullUserResolver;

return [

    'product' => [
        'name' => env('XCHANGE_PRODUCT_NAME', 'X-Change'),
        'code' => env('XCHANGE_PRODUCT_CODE', 'x-change'),
        'default_currency' => env('XCHANGE_DEFAULT_CURRENCY', 'PHP'),
        'default_country' => env('XCHANGE_DEFAULT_COUNTRY', 'PH'),
    ],

    'branding' => [
        'name' => env('XCHANGE_BRAND_NAME', env('XCHANGE_PRODUCT_NAME', 'X-Change')),
        'logo_light' => env('XCHANGE_LOGO_LIGHT', '/vendor/x-change/images/logo-orange.png'),
        'logo_dark' => env('XCHANGE_LOGO_DARK', '/vendor/x-change/images/logo-silver.png'),
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
        'user_resolver' => ContextUserResolver::class,
        'voucher_access' => VoucherAccessService::class,
        'entry_route' => VoucherEntryRouteService::class,
        'disburse_flow' => DisburseFlowStarterService::class,
        'completion_store' => SessionCompletionStore::class,
        'terminology' => TerminologyService::class,
        'api_response' => ApiResponseFactory::class,
        'pricing' => PricingService::class,
        'issuance' => PayCodeIssuanceService::class,
        'wallet_access' => WalletAccessService::class,
        'idempotency_store' => CacheIdempotencyStore::class,
        'issuer_onboarding' => DefaultIssuerOnboardingService::class,
        'wallet_provisioning' => DefaultWalletProvisioningService::class,
        'issuer_resolver' => DefaultIssuerResolver::class,
        'redemption_flow_preparation' => DefaultRedemptionFlowPreparationService::class,
        'redemption_completion_context' => DefaultRedemptionCompletionContextService::class,
        'redemption_completion_store' => NullRedemptionCompletionStore::class,
        'claim_execution_factory' => DefaultClaimExecutionFactory::class,
        'redemption_context_resolver' => DefaultRedemptionContextResolverService::class,
        'redemption_validation' => DefaultRedemptionValidationService::class,
        'redemption_processor' => DefaultRedemptionProcessorService::class,
        'redemption_execution' => DefaultRedemptionExecutionService::class,
        'withdrawal_validation' => DefaultWithdrawalValidationService::class,
        'withdrawal_processor' => DefaultWithdrawalProcessorService::class,
        'withdrawal_execution' => DefaultWithdrawalExecutionService::class,
        'disbursement_reconciliation_store' => DefaultDisbursementReconciliationStore::class,
        'disbursement_status_resolver' => DefaultDisbursementStatusResolverService::class,
        'disbursement_status_fetcher' => DefaultDisbursementStatusFetcherService::class,
        'disbursement_reconciliation' => DefaultDisbursementReconciliationService::class,
    ],

    'service_contracts' => [
        UserResolverContract::class => 'user_resolver',
        VoucherAccessContract::class => 'voucher_access',
        VoucherEntryRouteResolverContract::class => 'entry_route',
        TerminologyServiceContract::class => 'terminology',
        PricingServiceContract::class => 'pricing',
        PayCodeIssuanceContract::class => 'issuance',
        WalletAccessContract::class => 'wallet_access',
        IdempotencyStoreContract::class => 'idempotency_store',
        IssuerOnboardingContract::class => 'issuer_onboarding',
        WalletProvisioningContract::class => 'wallet_provisioning',
        IssuerResolverContract::class => 'issuer_resolver',
    ],

    'integrations' => [
        'system_wallet_resolver' => NullSystemWalletResolver::class,
        'audit_logger' => CacheAuditLogger::class,
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
        'issuer_model' => env('XCHANGE_ONBOARDING_DEFAULT_ISSUER_MODEL', User::class),
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
        'provider' => NetbankPayoutProvider::class,
        'wallet_proxy' => SystemWalletProxy::class,
        'system_user_id' => env('XCHANGE_SYSTEM_USER_ID'),
        'system_user_column' => env('XCHANGE_SYSTEM_USER_COLUMN', 'id'),
        'system_wallet_slug' => env(
            'XCHANGE_SYSTEM_WALLET_SLUG',
            env('XCHANGE_ONBOARDING_DEFAULT_WALLET_SLUG', 'platform')
        ),
    ],
    'lifecycle' => [
        ...require __DIR__.'/lifecycle-scenarios.php',
        'withdrawals' => [
            'service' => WithdrawalLifecycleService::class,
        ],
        'scenario_groups' => [
            'pre-deployment' => [
                'label' => 'Pre-Deployment Checks',
                'description' => 'Runs critical lifecycle scenarios before deployment.',
                'categories' => ['smoke', 'contract', 'settlement', 'reconciliation'],
                'tags' => [],
                'scenarios' => [],
            ],

            'post-deployment' => [
                'label' => 'Post-Deployment Checks',
                'description' => 'Runs lightweight checks after deployment.',
                'categories' => ['smoke'],
                'tags' => [],
                'scenarios' => [],
            ],

            'partner-certification' => [
                'label' => 'Partner Certification',
                'description' => 'Runs lifecycle scenarios needed to certify partner readiness.',
                'categories' => ['smoke', 'contract', 'provider', 'settlement', 'reconciliation'],
                'tags' => [],
                'scenarios' => [],
            ],

            'demo' => [
                'label' => 'Demo Automation',
                'description' => 'Runs scenarios useful for predictable demos.',
                'categories' => [],
                'tags' => ['demo'],
                'scenarios' => [
                    // optionally explicit scenario keys
                ],
            ],
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | Pricing Configuration - Market-Rational Pricing Model
    |--------------------------------------------------------------------------
    |
    | This pricing schedule is designed to be competitive, cost-based, and
    | value-driven. All prices are in centavos (100 = ₱1.00).
    |
    | PRICING PHILOSOPHY:
    | ===================
    |
    | 1. COST RECOVERY (Break-even or minimal margin)
    |    - Transaction Fee: ₱15.00 (NetBank InstaPay cost)
    |    - KYC Verification: ₱18.00 (HyperVerge ₱15 + processing ₱3)
    |    - OTP: ₱2.00 (SMS ₱1 + processing ₱1)
    |    - Email/SMS Notifications: ₱1.20-₱1.50 (delivery + storage)
    |
    | 2. MINIMAL PRICING (Single digits for competitive advantage)
    |    - Standard text inputs: ₱0.30-₱0.50
    |    - Simple validations: ₱0.50-₱0.80
    |    - Storage-light features: ₱0.50-₱1.00
    |
    | 3. STORAGE-BASED PRICING
    |    - Location: ₱1.00 (geocoding + coordinates storage)
    |    - Signature: ₱1.50 (medium image storage)
    |    - Selfie: ₱3.00 (large image storage + processing)
    |
    | 4. PREMIUM FEATURES (Value-based - Marketing ROI)
    |    - Rider Message: ₱2.00 (basic messaging)
    |    - Rider Splash: ₱20.00 (advertising real estate - 10x multiplier)
    |    - Rider URL: ₱50.00 (digital marketing conversion tool - 25x multiplier)
    |
    | 5. ENTERPRISE FEATURES (Accessible pricing)
    |    - Payable Voucher: ₱5.00 (multi-payment capability)
    |    - Settlement Voucher: ₱8.00 (complex enterprise workflows)
    |
    | COMPETITIVE POSITIONING:
    | =======================
    |
    | Base Transaction: ₱15.00 (cost pass-through, profit from features)
    | Basic Voucher (no extras): ₱15.00
    | Marketing Voucher (email + name + rider URL): ₱66.80
    | KYC Voucher (KYC + selfie + location + signature): ₱38.50
    | Full-Featured: ~₱120.00
    |
    | COST BREAKDOWN BY CATEGORY:
    | ===========================
    |
    | Base Charges:          ₱15.00 - ₱8.00
    | Feedback Channels:     ₱0.50 - ₱1.50
    | Input Fields (Text):   ₱0.30 - ₱0.50
    | Input Fields (Media):  ₱1.00 - ₱3.00
    | Input Fields (KYC):    ₱18.00
    | Input Fields (OTP):    ₱2.00
    | Validation Rules:      ₱0.50 - ₱2.00
    | Rider Features:        ₱2.00 - ₱50.00
    |
    | RATIONALE:
    | ==========
    |
    | 1. Transaction Fee = NetBank cost (no markup) to stay competitive
    | 2. Text inputs minimal (₱0.30-₱0.50) - just database storage
    | 3. Media inputs priced by storage size (location < signature < selfie)
    | 4. Third-party APIs cost-plus (KYC ₱15+₱3, OTP ₱1+₱1)
    | 5. Rider URL premium (₱50) justified by marketing conversion value
    | 6. Everything kept single-digit except cost-recovery and premium features
    |
    */

    'pricelist' => [
        /*
        |--------------------------------------------------------------------------
        | BASE CHARGES - Cost Recovery
        |--------------------------------------------------------------------------
        |
        | Transaction Fee: Pass-through of NetBank InstaPay cost (₱15.00)
        | Enterprise Vouchers: Accessible pricing for business features
        |
        */
        'cash.amount' => [
            'price' => 1500, // ₱15.00 (NetBank InstaPay fee - break-even)
            'label' => 'Transaction Fee',
            'description' => 'InstaPay fund transfer cost (NetBank)',
            'category' => 'base',
        ],
        'voucher_type.payable' => [
            'price' => 500, // ₱5.00 (multi-payment capability)
            'label' => 'Payable Voucher',
            'description' => 'Multi-payment voucher accepting payments until target amount reached',
            'category' => 'base',
        ],
        'voucher_type.settlement' => [
            'price' => 800, // ₱8.00 (complex enterprise workflows)
            'label' => 'Settlement Voucher',
            'description' => 'Enterprise settlement instrument for complex multi-payment scenarios',
            'category' => 'base',
        ],

        /*
        |--------------------------------------------------------------------------
        | HIGH-COST FEATURES - Third-Party API Costs
        |--------------------------------------------------------------------------
        |
        | KYC: HyperVerge API (₱15) + processing/storage (₱3)
        | OTP: SMS gateway (₱1) + system processing (₱1)
        |
        */
        'inputs.fields.kyc' => [
            'price' => 1800, // ₱18.00 (HyperVerge ₱15 + processing ₱3)
            'label' => 'KYC Verification',
            'description' => 'Identity verification via HyperVerge (ID + selfie biometric)',
            'category' => 'input_fields',
        ],
        'inputs.fields.otp' => [
            'price' => 200, // ₱2.00 (SMS ₱1 + processing ₱1)
            'label' => 'OTP Verification',
            'description' => 'One-time password via SMS',
            'category' => 'input_fields',
        ],

        /*
        |--------------------------------------------------------------------------
        | FEEDBACK CHANNELS - Notification Costs
        |--------------------------------------------------------------------------
        |
        | Email: Service cost + storage for mail/attachments (₱1.50)
        | SMS: Delivery cost + margin (₱1.20)
        | Webhook: Minimal HTTP request + logging (₱0.50)
        |
        */
        'feedback.email' => [
            'price' => 150, // ₱1.50 (email service + storage for attachments)
            'label' => 'Email Notification',
            'description' => 'Email notification on redemption with attachments',
            'category' => 'feedback',
        ],
        'feedback.mobile' => [
            'price' => 120, // ₱1.20 (SMS delivery ₱1.00 + margin ₱0.20)
            'label' => 'SMS Notification',
            'description' => 'SMS notification on redemption',
            'category' => 'feedback',
        ],
        'feedback.webhook' => [
            'price' => 50, // ₱0.50 (HTTP request + logging)
            'label' => 'Webhook Notification',
            'description' => 'Real-time webhook notification to your endpoint',
            'category' => 'feedback',
        ],

        /*
        |--------------------------------------------------------------------------
        | STORAGE-INTENSIVE INPUT FIELDS
        |--------------------------------------------------------------------------
        |
        | Selfie: Large image storage + processing (₱3.00)
        | Signature: Medium image storage (₱1.50)
        | Location: Geocoding API + coordinates storage (₱1.00)
        |
        */
        'inputs.fields.selfie' => [
            'price' => 300, // ₱3.00 (large image storage + processing)
            'label' => 'Selfie Photo',
            'description' => 'Camera capture for selfie verification',
            'category' => 'input_fields',
        ],
        'inputs.fields.signature' => [
            'price' => 150, // ₱1.50 (medium image storage)
            'label' => 'Digital Signature',
            'description' => 'Digital signature capture',
            'category' => 'input_fields',
        ],
        'inputs.fields.location' => [
            'price' => 100, // ₱1.00 (geocoding API + storage)
            'label' => 'GPS Location',
            'description' => 'GPS coordinates capture with reverse geocoding',
            'category' => 'input_fields',
        ],

        /*
        |--------------------------------------------------------------------------
        | STANDARD INPUT FIELDS - Text/Data Only
        |--------------------------------------------------------------------------
        |
        | Minimal cost - just database storage for text data (₱0.30 - ₱0.50)
        | Email/Mobile collection slightly higher due to validation overhead
        |
        */
        'inputs.fields.email' => [
            'price' => 50, // ₱0.50 (text storage + validation)
            'label' => 'Email Address',
            'description' => 'Collect email address from redeemer',
            'category' => 'input_fields',
        ],
        'inputs.fields.mobile' => [
            'price' => 50, // ₱0.50 (text storage + validation)
            'label' => 'Mobile Number',
            'description' => 'Collect mobile number from redeemer',
            'category' => 'input_fields',
        ],
        'inputs.fields.name' => [
            'price' => 30, // ₱0.30 (text storage only)
            'label' => 'Full Name',
            'description' => 'Collect full name from redeemer',
            'category' => 'input_fields',
        ],
        'inputs.fields.address' => [
            'price' => 50, // ₱0.50 (text storage)
            'label' => 'Full Address',
            'description' => 'Collect complete address from redeemer',
            'category' => 'input_fields',
        ],
        'inputs.fields.birth_date' => [
            'price' => 30, // ₱0.30 (date storage)
            'label' => 'Birth Date',
            'description' => 'Collect birth date for age verification',
            'category' => 'input_fields',
        ],
        'inputs.fields.gross_monthly_income' => [
            'price' => 30, // ₱0.30 (numeric storage)
            'label' => 'Monthly Income',
            'description' => 'Collect gross monthly income data',
            'category' => 'input_fields',
        ],
        'inputs.fields.reference_code' => [
            'price' => 30, // ₱0.30 (text storage)
            'label' => 'Reference Code',
            'description' => 'Collect custom reference code',
            'category' => 'input_fields',
        ],

        /*
        |--------------------------------------------------------------------------
        | VALIDATION RULES - Security & Compliance
        |--------------------------------------------------------------------------
        |
        | Secret Code: Simple validation logic (₱0.50)
        | Mobile Restriction: Phone number validation (₱0.50)
        | Time Validation: Complex scheduling logic (₱0.80)
        | Location Validation: GPS licensing + geo-fencing (₱1.20)
        | Vendor Alias: Enterprise B2B feature (₱2.00)
        |
        */
        'cash.validation.secret' => [
            'price' => 50, // ₱0.50 (validation logic only)
            'label' => 'Secret Code',
            'description' => 'Require secret code for redemption security',
            'category' => 'validation',
        ],
        'cash.validation.mobile' => [
            'price' => 50, // ₱0.50 (phone validation)
            'label' => 'Mobile Restriction',
            'description' => 'Restrict redemption to specific mobile number',
            'category' => 'validation',
        ],
        'validation.time' => [
            'price' => 80, // ₱0.80 (complex scheduling logic)
            'label' => 'Time Window Validation',
            'description' => 'Restrict redemption to specific time windows and duration limits',
            'category' => 'validation',
        ],
        'validation.location' => [
            'price' => 120, // ₱1.20 (GPS licensing + geo-fencing computation)
            'label' => 'Location Validation',
            'description' => 'Geo-fencing with coordinates and radius restrictions',
            'category' => 'validation',
        ],
        'cash.validation.payable' => [
            'price' => 200, // ₱2.00 (enterprise B2B feature)
            'label' => 'Vendor Alias (B2B)',
            'description' => 'Restrict redemption to specific merchant vendor alias',
            'category' => 'validation',
        ],

        /*
        |--------------------------------------------------------------------------
        | PREMIUM: RIDER FEATURES - Value-Based Marketing Tools
        |--------------------------------------------------------------------------
        |
        | These are PREMIUM features with high marketing/conversion value:
        |
        | Rider Message (₱2.00):
        |   - Basic post-redemption messaging
        |   - Custom instructions or thank-you notes
        |   - Modest value, accessible pricing
        |
        | Rider Splash (₱20.00):
        |   - ADVERTISING REAL ESTATE - 10x multiplier
        |   - Full-screen branded splash page
        |   - Logo, images, custom branding
        |   - High visibility, perfect for brand awareness
        |
        | Rider URL (₱50.00):
        |   - DIGITAL MARKETING CONVERSION TOOL - 25x multiplier
        |   - Redirect to landing page, signup form, app download
        |   - Lead generation and customer onboarding
        |   - Highest ROI for marketers
        |   - Conversion tracking capability
        |
        | PRICING RATIONALE:
        | Rider features enable monetization of the "attention moment" right
        | after successful redemption. Users are engaged and ready to take
        | action - perfect for marketing, onboarding, and conversions.
        |
        */
        'rider.message' => [
            'price' => 200, // ₱2.00 (basic messaging)
            'label' => 'Rider Message',
            'description' => 'Custom message shown after successful redemption',
            'category' => 'rider',
        ],
        'rider.splash' => [
            'price' => 2000, // ₱20.00 (advertising real estate - 10x value)
            'label' => 'Rider Splash Screen',
            'description' => 'Full-screen branded splash page with logo and custom content (advertising space)',
            'category' => 'rider',
        ],
        'rider.url' => [
            'price' => 5000, // ₱50.00 (digital marketing tool - 25x value)
            'label' => 'Rider Redirect URL',
            'description' => 'Redirect to landing page for onboarding, lead generation, or app download (conversion tool)',
            'category' => 'rider',
        ],

        /*
        |--------------------------------------------------------------------------
        | DEPRECATED FEATURES
        |--------------------------------------------------------------------------
        |
        | Legacy validation fields replaced by modern implementations.
        | Kept for backward compatibility but priced at ₱0.00.
        |
        */
        'cash.validation.location' => [
            'price' => 0, // DEPRECATED
            'label' => 'Location String (Legacy)',
            'description' => '[DEPRECATED] Use validation.location with lat/lng coordinates instead',
            'category' => 'validation',
            'deprecated' => true,
            'deprecated_reason' => 'Use validation.location with coordinates and radius_meters for accurate geo-fencing',
        ],
        'cash.validation.radius' => [
            'price' => 0, // DEPRECATED
            'label' => 'Radius String (Legacy)',
            'description' => '[DEPRECATED] Use validation.location.radius_meters instead',
            'category' => 'validation',
            'deprecated' => true,
            'deprecated_reason' => 'Use validation.location.radius_meters for precise radius validation',
        ],
    ],

    'revenue' => [
        'instruction_item_model' => env(
            'XCHANGE_REVENUE_INSTRUCTION_ITEM_MODEL',
            InstructionItem::class,
            //            \App\Models\InstructionItem::class
        ),

        'destination' => [
            'model' => env(
                'XCHANGE_REVENUE_DESTINATION_MODEL',
                User::class
            ),
            'identifier' => env('XCHANGE_REVENUE_DESTINATION_IDENTIFIER'),
            'identifier_column' => env('XCHANGE_REVENUE_DESTINATION_IDENTIFIER_COLUMN', 'email'),
        ],
    ],

    'withdrawal' => [
        'open_slice_min_interval_seconds' => 10,
        'pipeline' => [
            'steps' => [
                ResolveWithdrawalClaimantStep::class,
                AssertWithdrawalEligibilityStep::class,
                AuthorizeWithdrawalClaimantStep::class,
                ResolveWithdrawalAmountStep::class,

                BlockSettlementVoucherWithdrawalStep::class,

                AuthorizeWithdrawalOtpStep::class,
                AuthorizeWithdrawalPolicyStep::class,

                ResolveWithdrawalBankAccountStep::class,
                BuildWithdrawalPayoutRequestStep::class,
                GuardWithdrawalRailStep::class,
                ExecuteWithdrawalDisbursementStep::class,
                WithdrawalWalletSettlementStep::class,
                BuildWithdrawalResultStep::class,
            ],

        ],
        'otp' => [
            'driver' => env('XCHANGE_WITHDRAWAL_OTP_DRIVER', 'null'),
            'required' => env('XCHANGE_WITHDRAWAL_OTP_REQUIRED', false),
            'label' => env('OTP_LABEL', config('app.name', 'x-change')),

            'txtcmdr' => [
                'base_url' => env('TXTCMDR_API_URL', 'http://txtcmdr.test'),
                'api_token' => env('TXTCMDR_API_TOKEN'),
                'sender_id' => env('TXTCMDR_DEFAULT_SENDER_ID', 'cashless'),
                'timeout' => env('TXTCMDR_TIMEOUT', 30),
                'verify_ssl' => env('TXTCMDR_VERIFY_SSL', true),
                'test_mobile' => env('TXTCMDR_TEST_MOBILE'),
            ],
            'paynamics' => [
                /*
                 * Reserved for the concrete emi-paynamics adapter wiring.
                 *
                 * For now the service is selected by:
                 * XCHANGE_WITHDRAWAL_OTP_DRIVER=paynamics
                 */
                'service' => PaynamicsWithdrawalOtpApprovalService::class,
            ],
        ],
    ],
    'vendors' => [
        'registry' => env('XCHANGE_VENDOR_REGISTRY', 'config'),

        'aliases' => [
            'MERALCO' => [
                'id' => 'vendor.meralco',
                'name' => 'Manila Electric Company',
                'aliases' => [
                    'meralco',
                    'MERALCO ONLINE',
                    'MANILA ELECTRIC COMPANY',
                ],
                'meta' => [
                    'category' => 'utility',
                ],
            ],
        ],
    ],
    'approval_workflow' => [
        'handlers' => [
            'approval' => ManualApprovalRequirementHandler::class,
            'otp' => OtpApprovalRequirementHandler::class,
        ],
    ],
    'voucher_flow_types' => [
        'default' => 'disbursable',

        'canonical' => [
            'disbursable' => [
                'label' => 'Cash Out Voucher',
                'direction' => 'outward',
                'can_disburse' => true,
                'can_collect' => false,
                'can_settle' => false,
                'supports_open_slices' => true,
                'supports_delegated_spend' => true,
                'requires_envelope' => false,
                'pay_code_route' => 'disburse',
                'qr_type' => 'claim',
            ],

            'collectible' => [
                'label' => 'Pay In Voucher',
                'direction' => 'inward',
                'can_disburse' => false,
                'can_collect' => true,
                'can_settle' => false,
                'supports_open_slices' => false,
                'supports_delegated_spend' => false,
                'requires_envelope' => false,
                'pay_code_route' => 'pay',
                'qr_type' => 'payment',
            ],

            'settlement' => [
                'label' => 'Settlement Voucher',
                'direction' => 'bilateral',
                'can_disburse' => true,
                'can_collect' => true,
                'can_settle' => true,
                'supports_open_slices' => true,
                'supports_delegated_spend' => true,
                'requires_envelope' => true,
                'pay_code_route' => 'settle',
                'qr_type' => 'hybrid',
            ],
        ],

        'aliases' => [
            'redeemable' => 'disbursable',
            'payable' => 'collectible',
        ],
    ],

    'claim_approval' => [
        'ttl_minutes' => env('X_CHANGE_CLAIM_APPROVAL_TTL_MINUTES', 15),
        'otp' => [
            'driver' => env('X_CHANGE_CLAIM_APPROVAL_OTP_DRIVER', 'null'),
            'drivers' => [
                'null' => [
                    'challenge' => NullClaimOtpChallengeService::class,
                    'verify' => NullClaimOtpVerificationService::class,

                ],
                'withdrawal_otp' => [
                    'challenge' => WithdrawalOtpApprovalBackedClaimOtpChallengeService::class,
                    'verify' => WithdrawalOtpApprovalBackedClaimOtpVerificationService::class,
                ],
            ],
        ],
    ],

    'payment_qr' => [
        'renderer' => env('XCHANGE_PAYMENT_QR_RENDERER', 'json'),

        'renderers' => [
            'json' => DefaultVoucherPaymentQrRenderer::class,
            'png_base64' => Base64PngVoucherPaymentQrRenderer::class,
        ],
        'png' => [
            'size' => env('XCHANGE_PAYMENT_QR_PNG_SIZE', 300),
            'margin' => env('XCHANGE_PAYMENT_QR_PNG_MARGIN', 10),
        ],
    ],

    'payment' => [
        'default_provider' => env('X_CHANGE_PAYMENT_PROVIDER', 'manual'),

        'providers' => [
            // ...
        ],

        'webhook_parsers' => [
            // 'netbank' => \App\Payments\NetbankVoucherPaymentWebhookParser::class,
            // 'gcash' => \App\Payments\GcashVoucherPaymentWebhookParser::class,
        ],
    ],

    'settlement' => [
        'default_driver' => env('XCHANGE_SETTLEMENT_DRIVER', 'philhealth-bst'),
        'default_gate' => env('XCHANGE_SETTLEMENT_GATE', 'settleable'),
        'drivers_path' => config_path('envelope-drivers'),
    ],

    'rider' => [
        'outcomes' => [
            'treat_pending_with_local_disbursement_as_success' => env(
                'X_CHANGE_RIDER_PENDING_WITH_LOCAL_DISBURSEMENT_IS_SUCCESS',
                true
            ),
        ],
    ],
];
