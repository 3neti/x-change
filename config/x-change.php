<?php

declare(strict_types=1);

use LBHurtado\Instruction\Models\InstructionItem;
use LBHurtado\PaymentGateway\Adapters\NetbankPayoutProvider;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Contracts\FundingAccountRecoveryContract;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Contracts\IdempotencyStoreContract;
use LBHurtado\XChange\Contracts\IssuerOnboardingContract;
use LBHurtado\XChange\Contracts\IssuerResolverContract;
use LBHurtado\XChange\Contracts\MinimumWithdrawalPolicyResolverContract;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\PricingServiceContract;
use LBHurtado\XChange\Contracts\ProviderAccountLinkRepositoryContract;
use LBHurtado\XChange\Contracts\ProviderFundingPolicyContract;
use LBHurtado\XChange\Contracts\ProviderProvisioningGatewayContract;
use LBHurtado\XChange\Contracts\ProviderProvisioningManagerContract;
use LBHurtado\XChange\Contracts\ProviderReadinessGuardContract;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\RiderStampCopyResolverContract;
use LBHurtado\XChange\Contracts\SystemAccountFundingPayCodeAuthorizationContract;
use LBHurtado\XChange\Contracts\SystemWalletResolverContract;
use LBHurtado\XChange\Contracts\TerminologyServiceContract;
use LBHurtado\XChange\Contracts\UserResolverContract;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Contracts\VoucherEntryRouteResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use LBHurtado\XChange\Contracts\XChangeOnboardingGatewayContract;
use LBHurtado\XChange\Contracts\XChangeProviderTopologyResolverContract;
use LBHurtado\XChange\Repositories\EloquentProviderAccountLinkRepository;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\ApprovalHandlers\ManualApprovalRequirementHandler;
use LBHurtado\XChange\Services\ApprovalHandlers\OtpApprovalRequirementHandler;
use LBHurtado\XChange\Services\Base64PngVoucherPaymentQrRenderer;
use LBHurtado\XChange\Services\CacheIdempotencyStore;
use LBHurtado\XChange\Services\Claim\DefaultClaimShareCardUrlResolver;
use LBHurtado\XChange\Services\Claim\DefaultRiderSplashArtworkSnapshotter;
use LBHurtado\XChange\Services\Claim\DefaultRiderStampArtifactStore;
use LBHurtado\XChange\Services\Claim\DefaultRiderStampCopyResolver;
use LBHurtado\XChange\Services\Claim\DefaultRiderStampRecipientResolver;
use LBHurtado\XChange\Services\Claim\GdRiderStampClaimShareCardRenderer;
use LBHurtado\XChange\Services\Claim\RiderStampClaimShareMetadataResolver;
use LBHurtado\XChange\Services\Claim\StoredRiderStampClaimShareCardRenderer;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityActionHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\Cockpit\SystemPrincipalCockpitTreasuryAccess;
use LBHurtado\XChange\Services\Cockpit\XActionCockpitOperatorIssuanceActivityActionHandoff;
use LBHurtado\XChange\Services\Cockpit\XFeedbackCockpitOperatorIssuanceActivityFeedbackHandoff;
use LBHurtado\XChange\Services\Cockpit\XJournalCockpitOperatorIssuanceActivityJournalHandoff;
use LBHurtado\XChange\Services\ConfigMinimumWithdrawalPolicyResolver;
use LBHurtado\XChange\Services\ConfigProviderRuntimeSettingsResolver;
use LBHurtado\XChange\Services\ConfigProviderTopologyResolver;
use LBHurtado\XChange\Services\ContextUserResolver;
use LBHurtado\XChange\Services\DefaultClaimExecutionFactory;
use LBHurtado\XChange\Services\DefaultDisbursementReconciliationService;
use LBHurtado\XChange\Services\DefaultDisbursementReconciliationStore;
use LBHurtado\XChange\Services\DefaultDisbursementStatusFetcherService;
use LBHurtado\XChange\Services\DefaultDisbursementStatusResolverService;
use LBHurtado\XChange\Services\DefaultIssuerOnboardingService;
use LBHurtado\XChange\Services\DefaultProviderProvisioningManager;
use LBHurtado\XChange\Services\DefaultProviderReadinessGuard;
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
use LBHurtado\XChange\Services\DefaultXChangeOnboardingGateway;
use LBHurtado\XChange\Services\DisburseFlowStarterService;
use LBHurtado\XChange\Services\Execution\NullExecutionResultActionHandoff;
use LBHurtado\XChange\Services\Execution\NullExecutionResultCockpitActivityHandoff;
use LBHurtado\XChange\Services\Execution\NullExecutionResultFeedbackHandoff;
use LBHurtado\XChange\Services\Execution\NullExecutionResultHandoffSummaryJournalWriter;
use LBHurtado\XChange\Services\Execution\XActionExecutionResultActionHandoff;
use LBHurtado\XChange\Services\Execution\XFeedbackExecutionResultFeedbackHandoff;
use LBHurtado\XChange\Services\Execution\XJournalExecutionResultHandoffSummaryJournalWriter;
use LBHurtado\XChange\Services\Execution\XJournalExecutionResultJournalHandoff;
use LBHurtado\XChange\Services\Funding\BavixFundingAccountCredit;
use LBHurtado\XChange\Services\Funding\ConfigSystemAccountFundingPayCodeAuthorization;
use LBHurtado\XChange\Services\Funding\DefaultFundingDestinationResolver;
use LBHurtado\XChange\Services\LedgerPooledProviderTopology;
use LBHurtado\XChange\Services\ManualProviderTopology;
use LBHurtado\XChange\Services\NullClaimOtpChallengeService;
use LBHurtado\XChange\Services\NullClaimOtpVerificationService;
use LBHurtado\XChange\Services\NullRedemptionCompletionStore;
use LBHurtado\XChange\Services\PayCodeIssuanceService;
use LBHurtado\XChange\Services\PaynamicsWithdrawalOtpApprovalService;
use LBHurtado\XChange\Services\PricingService;
use LBHurtado\XChange\Services\ProviderAwareFundingPolicy;
use LBHurtado\XChange\Services\ProviderCustomerWalletTopology;
use LBHurtado\XChange\Services\Provisioning\DelegatingProviderProvisioningGateway;
use LBHurtado\XChange\Services\Provisioning\FakeProviderProvisioningGateway;
use LBHurtado\XChange\Services\Provisioning\NetbankProviderProvisioningGateway;
use LBHurtado\XChange\Services\Provisioning\PaynamicsProviderProvisioningGateway;
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

$defaultClaimPreviewRiderSplashHtml = <<<'HTML'
<div class="text-center mx-auto" style="max-width: 1200px;">
  <div class="relative rounded-lg shadow-lg overflow-hidden bg-black">
    <img
      src="https://github.com/lbhurtado/failure-of-simultaneity/blob/main/planetary-rose.PNG?raw=true"
      alt="i carry your heart with me"
      style="display:block; width:100%; height:auto;"
    />
    <div
      class="absolute inset-0 pointer-events-none"
      style="background: linear-gradient(to right, rgba(0,0,0,.15), rgba(0,0,0,.05), rgba(0,0,0,.25));"
    ></div>
    <div class="absolute inset-0 flex items-center justify-end" style="padding-right: 0.5%;">
      <div class="text-white" style="text-align: right; transform: translateX(2%);">
        <h2 class="text-2xl sm:text-4xl font-serif font-normal tracking-wide mb-3">
          i carry your heart with me
        </h2>
        <p class="text-lg sm:text-2xl italic font-serif mb-8" style="color: rgba(255,255,255,.85);">
          (i carry it in my heart)
        </p>
        <p class="text-lg sm:text-2xl tracking-widest mb-8" style="color: rgba(255,255,255,.85);">
          🤝 &nbsp; ❤️ &nbsp; ✌️ &nbsp; 🔫 &nbsp; ✈️ &nbsp; ⭐
        </p>
        <p class="text-xs sm:text-sm tracking-widest" style="color: rgba(255,255,255,.65); margin-top: 0.75rem;">
          — e.e. cummings
        </p>
      </div>
    </div>
  </div>
</div>
HTML;

return [

    'deployment' => [
        'profile' => env('XCHANGE_DEPLOYMENT_PROFILE', 'development'),
        'profile_explicitly_configured' => env('XCHANGE_DEPLOYMENT_PROFILE') !== null,
        'custom_connections' => array_values(array_filter(array_map(
            static fn (string $reference): string => trim($reference),
            explode(',', (string) env('XCHANGE_ACTIVE_CONNECTIONS', '')),
        ))),
    ],

    'commissioning' => [
        'enabled' => (bool) env('XCHANGE_COMMISSIONING_ENABLED', true),
        'access_token' => env('XCHANGE_COMMISSIONING_ACCESS_TOKEN'),
        'retry_after_seconds' => (int) env('XCHANGE_COMMISSIONING_RETRY_AFTER', 300),
    ],

    'product' => [
        'name' => env('XCHANGE_PRODUCT_NAME', 'X-Change'),
        'code' => env('XCHANGE_PRODUCT_CODE', 'x-change'),
        'default_currency' => env('XCHANGE_DEFAULT_CURRENCY', 'PHP'),
        'default_country' => env('XCHANGE_DEFAULT_COUNTRY', 'PH'),
    ],

    'pay_code_issuance' => [
        'transaction_attempts' => (int) env(
            'XCHANGE_PAY_CODE_ISSUANCE_TRANSACTION_ATTEMPTS',
            5,
        ),
        'sqlite_busy_timeout_milliseconds' => (int) env(
            'XCHANGE_PAY_CODE_ISSUANCE_SQLITE_BUSY_TIMEOUT_MILLISECONDS',
            5000,
        ),
    ],

    'branding' => [
        'name' => env('XCHANGE_BRAND_NAME', env('XCHANGE_PRODUCT_NAME', 'X-Change')),
        'logo_light' => env('XCHANGE_LOGO_LIGHT', '/vendor/x-change/images/logo-orange.png'),
        'logo_dark' => env('XCHANGE_LOGO_DARK', '/vendor/x-change/images/logo-silver.png'),
    ],

    'cockpit' => [
        'account_mutation_middleware' => [
            'verified',
            'password.confirm:settings.security.confirm',
            'throttle:6,1',
        ],

        'account_scenario' => [
            'enabled' => (bool) env(
                'XCHANGE_COCKPIT_ACCOUNT_SCENARIO_ENABLED',
                env('APP_ENV') !== 'production',
            ),
            'middleware' => [
                'verified',
                'throttle:3,1',
            ],
        ],

        'qrph_funding_simulation' => [
            'enabled' => (bool) env(
                'XCHANGE_COCKPIT_QRPH_FUNDING_SIMULATION_ENABLED',
                env('APP_ENV') !== 'production',
            ),
            'middleware' => [
                'verified',
                'throttle:3,1',
            ],
        ],

        'header_provider_balance' => [
            'enabled' => (bool) env('XCHANGE_COCKPIT_HEADER_PROVIDER_BALANCE_ENABLED', true),
        ],

        'quick_generate' => [
            'last_instructions_ttl_seconds' => (int) env(
                'XCHANGE_COCKPIT_QUICK_GENERATE_LAST_INSTRUCTIONS_TTL_SECONDS',
                604800,
            ),
            'url_artwork' => [
                'middleware' => [
                    'throttle:12,1',
                ],
                'cache_ttl_seconds' => (int) env(
                    'XCHANGE_COCKPIT_URL_ARTWORK_CACHE_TTL_SECONDS',
                    3600,
                ),
                'connect_timeout_seconds' => (int) env(
                    'XCHANGE_COCKPIT_URL_ARTWORK_CONNECT_TIMEOUT_SECONDS',
                    3,
                ),
                'timeout_seconds' => (int) env(
                    'XCHANGE_COCKPIT_URL_ARTWORK_TIMEOUT_SECONDS',
                    6,
                ),
                'maximum_image_bytes' => (int) env(
                    'XCHANGE_COCKPIT_URL_ARTWORK_MAXIMUM_IMAGE_BYTES',
                    2097152,
                ),
                'maximum_document_bytes' => (int) env(
                    'XCHANGE_COCKPIT_URL_ARTWORK_MAXIMUM_DOCUMENT_BYTES',
                    524288,
                ),
                'providers' => [
                    'spotify' => [
                        'enabled' => (bool) env(
                            'XCHANGE_COCKPIT_SPOTIFY_ARTWORK_ENABLED',
                            true,
                        ),
                        'label' => 'Spotify',
                        'oembed_endpoint' => 'https://open.spotify.com/oembed',
                        'page_hosts' => [
                            'open.spotify.com',
                        ],
                        'path_pattern' => '#^/(track|album|playlist|episode|show|artist)/[A-Za-z0-9]+/?$#',
                        'strip_query' => true,
                        'image_hosts' => [
                            'i.scdn.co',
                            'image-cdn-ak.spotifycdn.com',
                            'mosaic.scdn.co',
                        ],
                    ],
                ],
            ],
        ],

        'operator_issuance_activity' => [
            'repository' => env('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY')
                ?: null,
            'recorder' => env('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_RECORDER')
                ?: null,
            'action_handoff' => env('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_ACTION_HANDOFF')
                ?: null,
            'action_handoff_status_projector' => env('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_ACTION_HANDOFF_STATUS_PROJECTOR')
                ?: null,
            'journal_handoff' => env('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_JOURNAL_HANDOFF')
                ?: null,
            'journal_handoff_status_projector' => env('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_JOURNAL_HANDOFF_STATUS_PROJECTOR')
                ?: null,
            'feedback_handoff' => env('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_FEEDBACK_HANDOFF')
                ?: null,
            'feedback_handoff_status_projector' => env('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_FEEDBACK_HANDOFF_STATUS_PROJECTOR')
                ?: null,
            'available_repositories' => [
                'database' => DatabaseCockpitOperatorIssuanceActivityRepository::class,
            ],
            'available_recorders' => [
                'database' => DatabaseCockpitOperatorIssuanceActivityRecorder::class,
            ],
            'available_action_handoffs' => [
                'x-action' => XActionCockpitOperatorIssuanceActivityActionHandoff::class,
            ],
            'available_action_handoff_status_projectors' => [
                'database' => DatabaseCockpitOperatorIssuanceActivityActionHandoffStatusProjector::class,
            ],
            'available_journal_handoffs' => [
                'x-journal' => XJournalCockpitOperatorIssuanceActivityJournalHandoff::class,
            ],
            'available_journal_handoff_status_projectors' => [
                'database' => DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class,
            ],
            'available_feedback_handoffs' => [
                'x-feedback' => XFeedbackCockpitOperatorIssuanceActivityFeedbackHandoff::class,
            ],
            'available_feedback_handoff_status_projectors' => [
                'database' => DatabaseCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector::class,
            ],
        ],

        'integrations' => [
            'journal' => [
                'reader' => env('XCHANGE_COCKPIT_JOURNAL_READER'),
            ],
            'action' => [
                'composer' => env('XCHANGE_COCKPIT_ACTION_COMPOSER'),
            ],
            'feedback' => [
                'console' => env('XCHANGE_COCKPIT_FEEDBACK_CONSOLE'),
            ],
        ],

        'local_campaign_fixture' => [
            'enabled' => (bool) env(
                'XCHANGE_COCKPIT_LOCAL_CAMPAIGN_FIXTURE_ENABLED',
                env('APP_ENV') !== 'production',
            ),
            'planning_key' => env('XCHANGE_COCKPIT_LOCAL_CAMPAIGN_FIXTURE_PLANNING_KEY', 'plan-local'),
            'execution_id' => env('XCHANGE_COCKPIT_LOCAL_CAMPAIGN_FIXTURE_EXECUTION_ID', 'exec-local'),
            'audience_id' => env('XCHANGE_COCKPIT_LOCAL_CAMPAIGN_FIXTURE_AUDIENCE_ID', 'audience-local'),
        ],
    ],

    'execution_result_handoffs' => [
        'journal' => env('XCHANGE_EXECUTION_RESULT_JOURNAL_HANDOFF')
            ?: null,
        'action' => env('XCHANGE_EXECUTION_RESULT_ACTION_HANDOFF')
            ?: null,
        'feedback' => env('XCHANGE_EXECUTION_RESULT_FEEDBACK_HANDOFF')
            ?: null,
        'cockpit_activity' => env('XCHANGE_EXECUTION_RESULT_COCKPIT_ACTIVITY_HANDOFF')
            ?: null,
        'summary_journal_writer' => env('XCHANGE_EXECUTION_RESULT_HANDOFF_SUMMARY_JOURNAL_WRITER')
            ?: null,

        'available_journal_handoffs' => [
            'x-journal' => XJournalExecutionResultJournalHandoff::class,
        ],
        'available_action_handoffs' => [
            'null' => NullExecutionResultActionHandoff::class,
            'x-action' => XActionExecutionResultActionHandoff::class,
        ],
        'available_feedback_handoffs' => [
            'null' => NullExecutionResultFeedbackHandoff::class,
            'x-feedback' => XFeedbackExecutionResultFeedbackHandoff::class,
        ],
        'available_cockpit_activity_handoffs' => [
            'null' => NullExecutionResultCockpitActivityHandoff::class,
        ],
        'available_summary_journal_writers' => [
            'null' => NullExecutionResultHandoffSummaryJournalWriter::class,
            'x-journal' => XJournalExecutionResultHandoffSummaryJournalWriter::class,
        ],

        'durable_evidence_source' => env(
            'XCHANGE_EXECUTION_RESULT_HANDOFF_EVIDENCE_SOURCE',
            'post_pipeline_summary_journal_event',
        ),
        'durable_evidence_event_type' => env(
            'XCHANGE_EXECUTION_RESULT_HANDOFF_EVIDENCE_EVENT_TYPE',
            'execution.handoff.summary.recorded',
        ),
    ],

    'campaigns' => [
        'pay_code_issuance' => [
            'connection' => env(
                'XCHANGE_CAMPAIGNS_PAY_CODE_TREASURY_CONNECTION',
                'netbank-primary',
            ),
            'ttl_days' => (int) env(
                'XCHANGE_CAMPAIGNS_PAY_CODE_TTL_DAYS',
                7,
            ),
        ],
        'netbank_dispatch' => [
            'enabled' => (bool) env('XCHANGE_CAMPAIGNS_NETBANK_DISPATCH_ENABLED', false),
        ],
        'delivery' => [
            'batch_size' => (int) env('XCHANGE_CAMPAIGNS_DELIVERY_BATCH_SIZE', 100),
            'sms' => [
                'enabled' => (bool) env('XCHANGE_CAMPAIGNS_SMS_DELIVERY_ENABLED', false),
            ],
            'email' => [
                'enabled' => (bool) env('XCHANGE_CAMPAIGNS_EMAIL_DELIVERY_ENABLED', false),
            ],
        ],
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
        'cockpit_treasury_access' => SystemPrincipalCockpitTreasuryAccess::class,
        'funding_account_credit' => BavixFundingAccountCredit::class,
        'system_account_funding_pay_code_authorization' => ConfigSystemAccountFundingPayCodeAuthorization::class,
        'funding_destination_resolver' => DefaultFundingDestinationResolver::class,
        'idempotency_store' => CacheIdempotencyStore::class,
        'issuer_onboarding' => DefaultIssuerOnboardingService::class,
        'onboarding_gateway' => DefaultXChangeOnboardingGateway::class,
        'provider_topology_resolver' => ConfigProviderTopologyResolver::class,
        'provider_runtime_settings' => ConfigProviderRuntimeSettingsResolver::class,
        'provider_account_links' => EloquentProviderAccountLinkRepository::class,
        'provider_provisioning_gateway' => DelegatingProviderProvisioningGateway::class,
        'provider_provisioning_manager' => DefaultProviderProvisioningManager::class,
        'provider_readiness_guard' => DefaultProviderReadinessGuard::class,
        'provider_funding_policy' => ProviderAwareFundingPolicy::class,
        'minimum_withdrawal_policy' => ConfigMinimumWithdrawalPolicyResolver::class,
        'wallet_provisioning' => DefaultWalletProvisioningService::class,
        'issuer_resolver' => DefaultIssuerResolver::class,
        'redemption_flow_preparation' => DefaultRedemptionFlowPreparationService::class,
        'redemption_completion_context' => DefaultRedemptionCompletionContextService::class,
        'redemption_completion_store' => NullRedemptionCompletionStore::class,
        'claim_execution_factory' => DefaultClaimExecutionFactory::class,
        'claim_share_metadata' => RiderStampClaimShareMetadataResolver::class,
        'claim_share_card_url_resolver' => DefaultClaimShareCardUrlResolver::class,
        'claim_share_card_renderer' => StoredRiderStampClaimShareCardRenderer::class,
        'rider_stamp_copy' => DefaultRiderStampCopyResolver::class,
        'rider_stamp_recipient' => DefaultRiderStampRecipientResolver::class,
        'rider_splash_artwork_snapshotter' => DefaultRiderSplashArtworkSnapshotter::class,
        'rider_stamp_artifact_store' => DefaultRiderStampArtifactStore::class,
        'rider_stamp_claim_card_composer' => GdRiderStampClaimShareCardRenderer::class,
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
        CockpitTreasuryAccessContract::class => 'cockpit_treasury_access',
        FundingAccountCreditContract::class => 'funding_account_credit',
        FundingAccountRecoveryContract::class => 'funding_account_credit',
        SystemAccountFundingPayCodeAuthorizationContract::class => 'system_account_funding_pay_code_authorization',
        FundingDestinationResolverContract::class => 'funding_destination_resolver',
        IdempotencyStoreContract::class => 'idempotency_store',
        IssuerOnboardingContract::class => 'issuer_onboarding',
        XChangeOnboardingGatewayContract::class => 'onboarding_gateway',
        XChangeProviderTopologyResolverContract::class => 'provider_topology_resolver',
        ProviderRuntimeSettingsResolverContract::class => 'provider_runtime_settings',
        ProviderAccountLinkRepositoryContract::class => 'provider_account_links',
        ProviderProvisioningGatewayContract::class => 'provider_provisioning_gateway',
        ProviderProvisioningManagerContract::class => 'provider_provisioning_manager',
        ProviderReadinessGuardContract::class => 'provider_readiness_guard',
        ProviderFundingPolicyContract::class => 'provider_funding_policy',
        MinimumWithdrawalPolicyResolverContract::class => 'minimum_withdrawal_policy',
        WalletProvisioningContract::class => 'wallet_provisioning',
        IssuerResolverContract::class => 'issuer_resolver',
        RiderStampCopyResolverContract::class => 'rider_stamp_copy',
    ],

    'minimum_withdrawal' => [
        'default' => (float) env('XCHANGE_MINIMUM_WITHDRAWAL_DEFAULT', 25.00),

        'providers' => [
            'netbank' => [
                'PHP' => (float) env('XCHANGE_MINIMUM_WITHDRAWAL_NETBANK_PHP', 5.00),
            ],
            'paynamics' => [
                'PHP' => (float) env('XCHANGE_MINIMUM_WITHDRAWAL_PAYNAMICS_PHP', 50.00),
            ],
        ],

        'rails' => [],
    ],

    'treasury' => [
        'legal_entity_reference' => env('XCHANGE_TREASURY_LEGAL_ENTITY_REFERENCE'),
        'principal_reference' => env('XCHANGE_TREASURY_SYSTEM_PRINCIPAL_REFERENCE', 'principal:system'),
        'system_mandate_reference' => env('XCHANGE_TREASURY_SYSTEM_MANDATE_REFERENCE', 'mandate:system:treasury'),
        'legal_profile' => env('XCHANGE_TREASURY_LEGAL_PROFILE', 'treasury-settlement-ph-v1'),
        'legal_profile_version' => env('XCHANGE_TREASURY_LEGAL_PROFILE_VERSION'),
        'vocabulary' => [
            'internal_balance' => [
                'label' => 'Client Funds',
                'description' => 'Recognized client funds attributed to this account across its provider positions.',
            ],
            'outstanding_pay_codes' => [
                'label' => 'Outstanding Pay Codes',
                'description' => 'Recognized client funds currently reserved by active Pay Code obligations.',
            ],
            'issuance_capacity' => [
                'label' => 'Issuance Capacity',
                'description' => 'The amount currently available for new Pay Codes after provider liquidity and outstanding obligations are considered.',
            ],
            'provider_liquidity' => [
                'label' => 'Provider Liquidity',
                'description' => 'The latest authoritative or cached provider-side liquidity observation.',
            ],
            'account_funding' => [
                'label' => 'Account Funding',
                'description' => 'Provider-verified value recognized for an account without implying a stored-value product.',
            ],
            'reusable_funding_address' => [
                'label' => 'Reusable Funding Address',
                'description' => 'A persistent provider destination used to attribute verified incoming funds to an account.',
            ],
            'funding_qr_ph' => [
                'label' => 'QR Ph',
                'description' => 'A provider-verified QR Ph funding method attributed to an account.',
            ],
            'funding_bank_transfer' => [
                'label' => 'Bank Transfer',
                'description' => 'A provider-verified transfer made to configured banking instructions.',
            ],
            'funding_pay_code' => [
                'label' => 'Pay Code',
                'description' => 'A credential that applies an existing Account Funding settlement instruction.',
            ],
            'funding_reviewed_value' => [
                'label' => 'Reviewed Value',
                'description' => 'A request for independently reviewed backing that does not itself recognize Client Funds.',
            ],
            'funding_activity' => [
                'label' => 'Funding Activity',
                'description' => 'A normalized lifecycle view of funding instructions, observations, reviews, and recognition.',
            ],
            'provider_account' => [
                'label' => 'Provider Account',
                'description' => 'An external bank or EMI resource connected to an account or to the system Treasury.',
            ],
            'exact_funding_intent' => [
                'label' => 'Exact Funding Intent',
                'description' => 'A time-bound instruction for an exact provider-verified funding amount.',
            ],
        ],
        'reconciliation_lock_seconds' => (int) env(
            'XCHANGE_TREASURY_RECONCILIATION_LOCK_SECONDS',
            60,
        ),
        'reconciliation_lock_wait_seconds' => (int) env(
            'XCHANGE_TREASURY_RECONCILIATION_LOCK_WAIT_SECONDS',
            5,
        ),
        'opening_capitalization' => [
            'default_policy' => env(
                'XCHANGE_TREASURY_OPENING_POLICY',
                'unattributed',
            ),
            'allow_production' => (bool) env(
                'XCHANGE_TREASURY_OPENING_CAPITALIZATION_ALLOW_PRODUCTION',
                false,
            ),
            'allowed_connections' => array_values(array_filter(array_map(
                static fn (string $reference): string => trim($reference),
                explode(',', (string) env(
                    'XCHANGE_TREASURY_OPENING_CAPITALIZATION_ALLOWED_CONNECTIONS',
                    '',
                )),
            ))),
            'connection_policies' => [
                'netbank-primary' => env(
                    'XCHANGE_TREASURY_NETBANK_OPENING_POLICY',
                    'unattributed',
                ),
                'paynamics-primary' => env(
                    'XCHANGE_TREASURY_PAYNAMICS_OPENING_POLICY',
                    'unattributed',
                ),
            ],
            'lock_seconds' => (int) env(
                'XCHANGE_TREASURY_OPENING_CAPITALIZATION_LOCK_SECONDS',
                60,
            ),
            'lock_wait_seconds' => (int) env(
                'XCHANGE_TREASURY_OPENING_CAPITALIZATION_LOCK_WAIT_SECONDS',
                5,
            ),
        ],
        'migration_lock_seconds' => (int) env(
            'XCHANGE_TREASURY_MIGRATION_LOCK_SECONDS',
            60,
        ),
        'migration_lock_wait_seconds' => (int) env(
            'XCHANGE_TREASURY_MIGRATION_LOCK_WAIT_SECONDS',
            5,
        ),
        'simulator' => [
            'enabled' => (bool) env(
                'XCHANGE_TREASURY_SIMULATOR_ENABLED',
                env('APP_ENV') !== 'production',
            ),
            'allowed_environments' => ['local', 'testing'],
        ],
        'connections' => [
            'netbank-primary' => [
                'provider' => 'netbank',
                'mode' => env(
                    'XCHANGE_TREASURY_NETBANK_MODE',
                    (bool) env('XCHANGE_FUNDING_NETBANK_ENABLED', false)
                        ? 'required'
                        : 'disabled',
                ),
                'currency' => 'PHP',
                'decimal_places' => 2,
                'inventory_reference' => 'inventory:netbank:vca-cash',
                'settlement_resource_reference' => 'resource:netbank:corporate-vca',
                'settlement_resource_type' => 'cash_at_bank',
                'custody_mode' => 'provider_projection',
                'required_capabilities' => [
                    'readiness_probe',
                    'balance_read',
                    'funding_evidence_read',
                    'funding_instruction_issue',
                ],
            ],
            'paynamics-primary' => [
                'provider' => 'paynamics_constellation',
                'mode' => env(
                    'XCHANGE_TREASURY_PAYNAMICS_MODE',
                    (bool) env('XCHANGE_FUNDING_PAYNAMICS_ENABLED', false)
                        ? 'required'
                        : 'disabled',
                ),
                'currency' => 'PHP',
                'decimal_places' => 2,
                'inventory_reference' => 'inventory:paynamics:wallet-float',
                'settlement_resource_reference' => 'resource:paynamics:corporate-wallet',
                'settlement_resource_type' => 'emi_wallet_float',
                'custody_mode' => 'provider_projection',
                'required_capabilities' => [
                    'readiness_probe',
                    'balance_read',
                    'funding_evidence_read',
                    'funding_instruction_issue',
                ],
            ],
        ],
    ],

    'funding' => [
        'provider_balance_max_age_seconds' => (int) env('XCHANGE_PROVIDER_BALANCE_MAX_AGE_SECONDS', 300),
        'api_middleware' => ['auth'],
        'system_pay_codes' => [
            'enabled' => (bool) env(
                'XCHANGE_SYSTEM_ACCOUNT_FUNDING_PAY_CODES_ENABLED',
                env('APP_ENV') !== 'production',
            ),
            'allow_production' => (bool) env(
                'XCHANGE_SYSTEM_ACCOUNT_FUNDING_PAY_CODES_ALLOW_PRODUCTION',
                false,
            ),
            'bearer_enabled' => (bool) env(
                'XCHANGE_SYSTEM_ACCOUNT_FUNDING_PAY_CODES_BEARER_ENABLED',
                false,
            ),
            'maximum_amount_minor' => (int) env(
                'XCHANGE_SYSTEM_ACCOUNT_FUNDING_PAY_CODES_MAXIMUM_AMOUNT_MINOR',
                5_000_000,
            ),
            'default_ttl_seconds' => (int) env(
                'XCHANGE_SYSTEM_ACCOUNT_FUNDING_PAY_CODES_TTL_SECONDS',
                604800,
            ),
            'interactive_default_amount' => env(
                'XCHANGE_SYSTEM_ACCOUNT_FUNDING_PAY_CODES_INTERACTIVE_DEFAULT_AMOUNT',
                '100.00',
            ),
            'allowed_connections' => array_values(array_filter(array_map(
                static fn (string $reference): string => trim($reference),
                explode(',', (string) env(
                    'XCHANGE_SYSTEM_ACCOUNT_FUNDING_PAY_CODES_ALLOWED_CONNECTIONS',
                    '',
                )),
            ))),
        ],
        'requests' => [
            'bank_transfer' => [
                'enabled' => env(
                    'XCHANGE_FUNDING_BANK_TRANSFER_ENABLED',
                    true,
                ),
                'provider' => env(
                    'XCHANGE_FUNDING_BANK_TRANSFER_PROVIDER',
                    'netbank',
                ),
                'connection_reference' => env(
                    'XCHANGE_FUNDING_BANK_TRANSFER_CONNECTION',
                    'netbank-primary',
                ),
                'verification_mode' => env(
                    'XCHANGE_FUNDING_BANK_TRANSFER_VERIFICATION_MODE',
                    'provider_verified_auto',
                ),
                'provider_history_enabled' => env(
                    'XCHANGE_FUNDING_BANK_TRANSFER_PROVIDER_HISTORY_ENABLED',
                    true,
                ),
                'minimum_requested_amount_minor' => max(
                    1,
                    (int) env(
                        'XCHANGE_FUNDING_BANK_TRANSFER_MINIMUM_REQUESTED_AMOUNT_MINOR',
                        10000,
                    ),
                ),
                'automatic_credit_window_minutes' => max(
                    1,
                    (int) env(
                        'XCHANGE_FUNDING_BANK_TRANSFER_AUTO_CREDIT_WINDOW_MINUTES',
                        10,
                    ),
                ),
                'clock_skew_seconds' => max(
                    0,
                    (int) env(
                        'XCHANGE_FUNDING_BANK_TRANSFER_CLOCK_SKEW_SECONDS',
                        120,
                    ),
                ),
                'reserved_amounts' => [
                    'enabled' => (bool) env(
                        'XCHANGE_FUNDING_BANK_TRANSFER_RESERVED_AMOUNTS_ENABLED',
                        true,
                    ),
                    'minimum_adjustment_minor' => max(
                        0,
                        (int) env(
                            'XCHANGE_FUNDING_BANK_TRANSFER_RESERVED_AMOUNT_MINIMUM_ADJUSTMENT_MINOR',
                            317,
                        ),
                    ),
                    'maximum_adjustment_minor' => max(
                        0,
                        (int) env(
                            'XCHANGE_FUNDING_BANK_TRANSFER_RESERVED_AMOUNT_MAXIMUM_ADJUSTMENT_MINOR',
                            537,
                        ),
                    ),
                    'ttl_seconds' => max(
                        60,
                        (int) env(
                            'XCHANGE_FUNDING_BANK_TRANSFER_RESERVED_AMOUNT_TTL_SECONDS',
                            600,
                        ),
                    ),
                    'reuse_delay_seconds' => max(
                        0,
                        (int) env(
                            'XCHANGE_FUNDING_BANK_TRANSFER_RESERVED_AMOUNT_REUSE_DELAY_SECONDS',
                            3600,
                        ),
                    ),
                    'maximum_allocation_attempts' => max(
                        1,
                        (int) env(
                            'XCHANGE_FUNDING_BANK_TRANSFER_RESERVED_AMOUNT_MAXIMUM_ALLOCATION_ATTEMPTS',
                            221,
                        ),
                    ),
                    'lock_seconds' => 10,
                    'lock_wait_seconds' => 5,
                ],
                'check_middleware' => [
                    'throttle:6,1',
                ],
            ],
            'reviewer_ids' => array_values(array_filter(array_map(
                static fn (string $id): string => trim($id),
                explode(',', (string) env('XCHANGE_FUNDING_REQUEST_REVIEWER_IDS', '')),
            ))),
            'maker_ids' => array_values(array_filter(array_map(
                static fn (string $id): string => trim($id),
                explode(',', (string) env(
                    'XCHANGE_FUNDING_REQUEST_MAKER_IDS',
                    env('XCHANGE_FUNDING_REQUEST_REVIEWER_IDS', ''),
                )),
            ))),
            'checker_ids' => array_values(array_filter(array_map(
                static fn (string $id): string => trim($id),
                explode(',', (string) env(
                    'XCHANGE_FUNDING_REQUEST_CHECKER_IDS',
                    env('XCHANGE_FUNDING_REQUEST_REVIEWER_IDS', ''),
                )),
            ))),
            'code_ttl_seconds' => (int) env(
                'XCHANGE_REVIEWED_FUNDING_PAY_CODE_TTL_SECONDS',
                env('XCHANGE_ACCOUNT_FUNDING_CODE_TTL_SECONDS', 604800),
            ),
            'create_middleware' => ['throttle:6,1'],
            'review_middleware' => ['throttle:30,1'],
            'approval_middleware' => ['throttle:12,1'],
            'claim_middleware' => ['throttle:12,1'],
            'attachments_enabled' => (bool) env(
                'XCHANGE_FUNDING_REQUEST_ATTACHMENTS_ENABLED',
                true,
            ),
            'evidence_disk' => env(
                'XCHANGE_FUNDING_REQUEST_EVIDENCE_DISK',
                'local',
            ),
            'envelope_driver' => env(
                'XCHANGE_FUNDING_REQUEST_ENVELOPE_DRIVER',
                'account-funding-review',
            ),
        ],
        'pay_code_claims' => [
            'inspection_ttl_seconds' => (int) env(
                'XCHANGE_PAY_CODE_FUNDING_INSPECTION_TTL_SECONDS',
                600,
            ),
            'inspect_middleware' => ['throttle:12,1'],
            'claim_middleware' => ['throttle:12,1'],
        ],
        'intent_ttl_seconds' => (int) env('XCHANGE_FUNDING_INTENT_TTL_SECONDS', 1800),
        'instruction_lock_seconds' => (int) env('XCHANGE_FUNDING_INSTRUCTION_LOCK_SECONDS', 30),
        'instruction_lock_wait_seconds' => (int) env('XCHANGE_FUNDING_INSTRUCTION_LOCK_WAIT_SECONDS', 5),
        'instruction_access_middleware' => ['throttle:30,1'],
        'manual_check_middleware' => ['throttle:6,1'],
        'liquidity_refresh' => [
            'middleware' => ['throttle:2,1'],
            'lock_seconds' => (int) env(
                'XCHANGE_FUNDING_LIQUIDITY_REFRESH_LOCK_SECONDS',
                30,
            ),
        ],
        'standing_addresses' => [
            'enabled' => (bool) env(
                'XCHANGE_STANDING_FUNDING_ADDRESSES_ENABLED',
                env('APP_ENV') !== 'production',
            ),
            'default_recognition_mode' => env(
                'XCHANGE_STANDING_FUNDING_RECOGNITION_MODE',
                'observe_only',
            ),
            'enforce_configured_recognition_mode' => (bool) env(
                'XCHANGE_STANDING_FUNDING_ENFORCE_RECOGNITION_MODE',
                false,
            ),
            'creditable_provider_statuses' => array_values(array_filter(array_map(
                static fn (string $status): string => strtolower(trim($status)),
                explode(',', (string) env(
                    'XCHANGE_STANDING_FUNDING_CREDITABLE_STATUSES',
                    'settled',
                )),
            ))),
            'instruction_middleware' => ['throttle:12,1'],
            'middleware' => ['throttle:3,1'],
            'lock_seconds' => (int) env('XCHANGE_STANDING_FUNDING_LOCK_SECONDS', 120),
            'lock_wait_seconds' => (int) env('XCHANGE_STANDING_FUNDING_LOCK_WAIT_SECONDS', 5),
            'qr_artifact_version' => env(
                'XCHANGE_STANDING_FUNDING_QR_ARTIFACT_VERSION',
                'standing-funding-qr-v1',
            ),
            'qr_generation_lock_seconds' => (int) env(
                'XCHANGE_STANDING_FUNDING_QR_LOCK_SECONDS',
                30,
            ),
            'qr_generation_wait_seconds' => (int) env(
                'XCHANGE_STANDING_FUNDING_QR_LOCK_WAIT_SECONDS',
                5,
            ),
            'scheduled_sync_enabled' => (bool) env(
                'XCHANGE_STANDING_FUNDING_SCHEDULED_SYNC_ENABLED',
                true,
            ),
            'scheduled_batch_size' => (int) env(
                'XCHANGE_STANDING_FUNDING_SCHEDULED_BATCH_SIZE',
                100,
            ),
            'webhook_batch_size' => (int) env(
                'XCHANGE_STANDING_FUNDING_WEBHOOK_BATCH_SIZE',
                100,
            ),
            'limits' => [
                'minimum_amount_minor' => (int) env(
                    'XCHANGE_STANDING_FUNDING_MINIMUM_AMOUNT_MINOR',
                    100,
                ),
                'maximum_amount_minor' => (int) env(
                    'XCHANGE_STANDING_FUNDING_MAXIMUM_AMOUNT_MINOR',
                    5_000_000,
                ),
                'daily_limit_minor' => (int) env(
                    'XCHANGE_STANDING_FUNDING_DAILY_LIMIT_MINOR',
                    10_000_000,
                ),
            ],
        ],
        'ui_refresh_interval_milliseconds' => (int) env('XCHANGE_FUNDING_UI_REFRESH_INTERVAL_MILLISECONDS', 5000),
        'broadcast_enabled' => (bool) env('XCHANGE_FUNDING_BROADCAST_ENABLED', false),
        'broadcast_reference_hash_key' => env(
            'XCHANGE_FUNDING_BROADCAST_REFERENCE_HASH_KEY',
            env('APP_KEY'),
        ),
        'reference_hash_key' => env('XCHANGE_FUNDING_REFERENCE_HASH_KEY', env('APP_KEY')),
        'payer_identity_hash_key' => env('XCHANGE_FUNDING_PAYER_IDENTITY_HASH_KEY', env('APP_KEY')),
        'webhook_max_body_bytes' => (int) env('XCHANGE_FUNDING_WEBHOOK_MAX_BODY_BYTES', 262_144),
        'webhook_middleware' => ['throttle:120,1'],
        'verification_lock_seconds' => (int) env('XCHANGE_FUNDING_VERIFICATION_LOCK_SECONDS', 120),
        'verification_lock_wait_seconds' => (int) env('XCHANGE_FUNDING_VERIFICATION_LOCK_WAIT_SECONDS', 5),
        'verification_candidate_limit' => (int) env('XCHANGE_FUNDING_VERIFICATION_CANDIDATE_LIMIT', 100),
        'verification_provider_rate_limit_per_minute' => (int) env(
            'XCHANGE_FUNDING_VERIFICATION_PROVIDER_RATE_LIMIT_PER_MINUTE',
            30,
        ),
        'scheduled_verification_enabled' => (bool) env(
            'XCHANGE_FUNDING_SCHEDULED_VERIFICATION_ENABLED',
            true,
        ),
        'scheduled_verification_batch_size' => (int) env(
            'XCHANGE_FUNDING_SCHEDULED_VERIFICATION_BATCH_SIZE',
            100,
        ),
        'settlement_grace_seconds' => (int) env(
            'XCHANGE_FUNDING_SETTLEMENT_GRACE_SECONDS',
            300,
        ),
        'simulator' => [
            'enabled' => (bool) env(
                'XCHANGE_QRPH_SIMULATOR_ENABLED',
                env('APP_ENV') !== 'production',
            ),
            'allowed_environments' => ['local', 'testing'],
            'signing_key' => env('XCHANGE_QRPH_SIMULATOR_SIGNING_KEY', env('APP_KEY')),
            'mobile_hash_key' => env('XCHANGE_QRPH_SIMULATOR_MOBILE_HASH_KEY', env('APP_KEY')),
        ],
        'providers' => [
            'netbank' => [
                'enabled' => (bool) env('XCHANGE_FUNDING_NETBANK_ENABLED', false),
                'signature_header' => null,
                'treasury' => [
                    'inventory_reference' => 'inventory:netbank:vca-cash',
                    'settlement_resource_reference' => 'resource:netbank:corporate-vca',
                    'resource_type' => 'cash_at_bank',
                ],
            ],
            'paynamics_constellation' => [
                'enabled' => (bool) env('XCHANGE_FUNDING_PAYNAMICS_ENABLED', false),
                'signature_header' => env('XCHANGE_FUNDING_PAYNAMICS_SIGNATURE_HEADER', 'Signature'),
                'treasury' => [
                    'inventory_reference' => 'inventory:paynamics:wallet-float',
                    'settlement_resource_reference' => 'resource:paynamics:corporate-wallet',
                    'resource_type' => 'emi_wallet_float',
                ],
            ],
            'qrph_simulator' => [
                'enabled' => (bool) env(
                    'XCHANGE_QRPH_SIMULATOR_ENABLED',
                    env('APP_ENV') !== 'production',
                ),
                'signature_header' => 'X-XChange-Simulator-Signature',
                'treasury' => [
                    'inventory_reference' => 'inventory:qrph-simulator:local-clearing',
                    'settlement_resource_reference' => 'resource:qrph-simulator:local-clearing',
                    'resource_type' => 'simulated_cash_at_bank',
                ],
            ],
        ],
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

    'commercial' => [
        'enabled' => (bool) env('XCHANGE_COMMERCIAL_WATERFALL_ENABLED', true),
        'pay_code' => [
            'catalog' => 'pay_code',
            'waterfall' => [
                'reference' => 'pay-code-commercial-waterfall',
                'version' => 1,
                'currency' => 'PHP',
                'rules' => [
                    [
                        'reference' => 'provider-transfer-cost',
                        'sequence' => 10,
                        'line_type' => 'deduction',
                        'category' => 'provider_cost',
                        'recipient_reference' => 'provider:settlement-rail',
                        'fixed_amount_minor' => 1_000,
                    ],
                    [
                        'reference' => 'product-revenue',
                        'sequence' => 20,
                        'line_type' => 'allocation',
                        'category' => 'product_revenue',
                        'recipient_reference' => 'product:pay-code',
                        'fixed_amount_minor' => 300,
                    ],
                    [
                        'reference' => 'partner-commission',
                        'sequence' => 30,
                        'line_type' => 'allocation',
                        'category' => 'partner_commission',
                        'recipient_reference' => 'partner:direct',
                        'fixed_amount_minor' => 100,
                    ],
                    [
                        'reference' => 'commercial-residual',
                        'sequence' => 40,
                        'line_type' => 'residual',
                        'category' => 'commercial_revenue',
                        'recipient_reference' => 'operator:x-change',
                        'fixed_amount_minor' => null,
                    ],
                ],
            ],
            'account_funding_waterfall' => [
                'reference' => 'pay-code-account-funding-waterfall',
                'version' => 1,
                'currency' => 'PHP',
                'rules' => [
                    [
                        'reference' => 'product-revenue',
                        'sequence' => 20,
                        'line_type' => 'allocation',
                        'category' => 'product_revenue',
                        'recipient_reference' => 'product:pay-code',
                        'fixed_amount_minor' => 300,
                    ],
                    [
                        'reference' => 'partner-commission',
                        'sequence' => 30,
                        'line_type' => 'allocation',
                        'category' => 'partner_commission',
                        'recipient_reference' => 'partner:direct',
                        'fixed_amount_minor' => 100,
                    ],
                    [
                        'reference' => 'commercial-residual',
                        'sequence' => 40,
                        'line_type' => 'residual',
                        'category' => 'commercial_revenue',
                        'recipient_reference' => 'operator:x-change',
                        'fixed_amount_minor' => null,
                    ],
                ],
            ],
            'destination_purposes' => [
                'provider-transfer-cost' => 'provider_cost_payable',
                'product-revenue' => 'product_revenue',
                'partner-commission' => 'partner_commission_payable',
                'commercial-residual' => 'commercial_revenue',
            ],
        ],
    ],

    'onboarding' => [
        //        'issuer_model' => env('XCHANGE_ONBOARDING_DEFAULT_ISSUER_MODEL', \LBHurtado\XChange\Tests\Fakes\User::class),
        'issuer_model' => env('XCHANGE_ONBOARDING_DEFAULT_ISSUER_MODEL', 'App\\Models\\User'),
        'default_wallet_slug' => env('XCHANGE_ONBOARDING_DEFAULT_WALLET_SLUG', 'platform'),
        'default_wallet_name' => env('XCHANGE_ONBOARDING_DEFAULT_WALLET_NAME', 'Platform Wallet'),
        'driver' => env('XCHANGE_ONBOARDING_DRIVER', 'legacy'),
        'mobile_first_auth' => env('XCHANGE_MOBILE_FIRST_AUTH', true),
        'email_required' => env('XCHANGE_AUTH_EMAIL_REQUIRED', false),
        'auth_enforcement' => env('XCHANGE_ONBOARDING_AUTH_ENFORCEMENT', 'scaffold'),
        'voucher' => [
            'require_otp' => (bool) env('XCHANGE_ONBOARDING_REQUIRE_OTP', true),
        ],
        'mobile_verification' => [
            'enabled' => (bool) env('XCHANGE_MOBILE_VERIFICATION_ENABLED', true),
            'ttl_minutes' => (int) env('XCHANGE_MOBILE_VERIFICATION_TTL_MINUTES', 10),
            'max_attempts' => (int) env('XCHANGE_MOBILE_VERIFICATION_MAX_ATTEMPTS', 5),
            'hash_key' => env('XCHANGE_MOBILE_VERIFICATION_HASH_KEY', env('APP_KEY')),
            'allow_null_driver_environments' => ['local', 'testing'],
            'show_local_code' => (bool) env(
                'XCHANGE_MOBILE_VERIFICATION_SHOW_LOCAL_CODE',
                env('APP_ENV') !== 'production',
            ),
        ],
    ],

    'provider_topologies' => [
        'default' => env('XCHANGE_PROVIDER_TOPOLOGY', 'manual'),
        'aliases' => [
            'manual' => 'manual',
            'netbank' => 'ledger_pooled',
            'paynamics' => 'provider_customer_wallet',
        ],
        'topologies' => [
            'manual' => ManualProviderTopology::class,
            'ledger_pooled' => LedgerPooledProviderTopology::class,
            'provider_customer_wallet' => ProviderCustomerWalletTopology::class,
        ],
    ],

    'provider_runtime' => [
        'default_provider' => env('XCHANGE_PROVIDER', env('XCHANGE_PROVIDER_TOPOLOGY', 'manual')),
        'payout_provider_hint' => env('XCHANGE_PAYOUT_PROVIDER'),
        'default_provisioning_gateway' => FakeProviderProvisioningGateway::class,

        'providers' => [
            'manual' => [
                'enabled' => env('XCHANGE_PROVIDER_MANUAL_ENABLED', true),
                'provisioning_gateway' => FakeProviderProvisioningGateway::class,
            ],
            'netbank' => [
                'enabled' => env('XCHANGE_PROVIDER_NETBANK_ENABLED', true),
                'provisioning_gateway' => NetbankProviderProvisioningGateway::class,
                'source_account_readiness' => [
                    'enabled' => env('XCHANGE_PROVIDER_NETBANK_SOURCE_ACCOUNT_READINESS_ENABLED', true),
                    'allow_unavailable' => env('XCHANGE_PROVIDER_NETBANK_SOURCE_ACCOUNT_ALLOW_UNAVAILABLE', false),
                    'account_number' => env('XCHANGE_PROVIDER_NETBANK_SOURCE_ACCOUNT_NUMBER', env('NETBANK_SOURCE_ACCOUNT_NUMBER')),
                ],
            ],
            'paynamics' => [
                'enabled' => env('XCHANGE_PROVIDER_PAYNAMICS_ENABLED', true),
                'provisioning_gateway' => PaynamicsProviderProvisioningGateway::class,
                'live_requests_enabled' => env('XCHANGE_PROVIDER_PAYNAMICS_LIVE_REQUESTS_ENABLED', false),
                'generate_kyc_link' => env('XCHANGE_PROVIDER_PAYNAMICS_GENERATE_KYC_LINK', true),
                'kyc_level' => (string) env('XCHANGE_PROVIDER_PAYNAMICS_KYC_LEVEL', '1'),
                'customer_profile_type' => env('XCHANGE_PROVIDER_PAYNAMICS_CUSTOMER_PROFILE_TYPE', 'DEFAULT_CONSUMER'),
            ],
        ],

        'lifecycle' => [
            'allow_live_provider_scenarios' => env('XCHANGE_LIFECYCLE_ALLOW_LIVE_PROVIDER_SCENARIOS', false),
        ],

        'settlement' => [
            'default_rail' => env('XCHANGE_DEFAULT_SETTLEMENT_RAIL', 'INSTAPAY'),
        ],
    ],

    'redemption' => [
        'field_mappings' => [
            'full_name' => 'name',
            'date_of_birth' => 'birth_date',
            'otp_code' => 'otp',
        ],
        'feedback' => [
            'enabled' => (bool) env('XCHANGE_REDEMPTION_FEEDBACK_ENABLED', true),
            'queue' => env('XCHANGE_REDEMPTION_FEEDBACK_QUEUE', 'x-change-feedback'),
            'terminal_claim_statuses' => [
                'succeeded',
                'redeemed',
                'withdrawn',
            ],
            'webhook' => [
                'secret' => env('XCHANGE_REDEMPTION_FEEDBACK_WEBHOOK_SECRET'),
                'headers' => [],
            ],
        ],
    ],
    'payout' => [
        'provider' => env('XCHANGE_PAYOUT_PROVIDER', NetbankPayoutProvider::class),
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
        'voucher_cancellation_middleware' => ['auth'],
        'synthetic_funding_environments' => ['local', 'testing'],
        'qrph_funding_simulation' => [
            'enabled' => (bool) env(
                'XCHANGE_LIFECYCLE_QRPH_SIMULATION_ENABLED',
                env('APP_ENV') !== 'production',
            ),
        ],
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

            'live-provider' => [
                'label' => 'Live Provider Verification',
                'description' => 'Runs opt-in provider API lifecycle verification scenarios.',
                'categories' => [],
                'tags' => [],
                'scenarios' => [
                    'provider_paynamics_wallet_live_provision',
                    'provider_paynamics_bank_account_live_link',
                    'provider_netbank_source_account_live_readiness',
                ],
            ],

            'demo' => [
                'label' => 'Demo Automation',
                'description' => 'Runs scenarios useful for predictable demos.',
                'categories' => [],
                'tags' => ['demo'],
                'scenarios' => [
                    'money_semantics_voucher_liability_demo',
                    'qrph_funding_existing_mobile_demo',
                    'qrph_funding_unknown_mobile_onboarding_demo',
                ],
            ],

            'turnkey-onboarding' => [
                'label' => 'Turnkey Onboarding',
                'description' => 'Runs safe mobile-first install and onboarding readiness scenarios.',
                'categories' => [],
                'tags' => [],
                'scenarios' => [
                    'turnkey_mobile_boot',
                    'turnkey_bank_onboarding_required',
                    'turnkey_provider_link_ready',
                    'turnkey_provider_link_pending_blocks',
                    'turnkey_netbank_ledger_wallet_ready',
                    'turnkey_netbank_bank_account_ready',
                    'turnkey_paynamics_wallet_fake_provisioned',
                    'turnkey_paynamics_bank_account_fake_linked',
                    'turnkey_paynamics_wallet_link_ready',
                    'turnkey_issuer_blocks_missing_provider_wallet',
                    'turnkey_issuer_allows_ready_provider_wallet',
                    'turnkey_claim_blocks_missing_bank_account',
                    'turnkey_claim_resumes_after_provider_account_ready',
                    'turnkey_basic_cash_mobile',
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
        ),

        'destination' => [
            'model' => env(
                'XCHANGE_REVENUE_DESTINATION_MODEL',
                'App\\Models\\User'
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

    'claim' => [
        'public_read_middleware' => [
            'throttle:60,1',
        ],
        'public_start_middleware' => [
            'throttle:12,1',
        ],
        'public_callback_middleware' => [
            'throttle:30,1',
        ],
        'public_submit_middleware' => [
            'throttle:12,1',
        ],
        'submission_lock_seconds' => 30,
        'submission_lock_wait_seconds' => 3,
        'experience_ui' => [
            'variant' => env(
                'XCHANGE_CLAIM_UI_VARIANT',
                env('FORM_FLOW_UI_VARIANT', 'default'),
            ),
            'show_progress' => (bool) env('XCHANGE_CLAIM_UI_SHOW_PROGRESS', true),
            'support_label' => env('XCHANGE_CLAIM_UI_SUPPORT_LABEL'),
            'layout' => [
                'density' => env('XCHANGE_CLAIM_UI_DENSITY', 'compact'),
                'capture_surface' => env('XCHANGE_CLAIM_UI_CAPTURE_SURFACE', 'edge_to_edge'),
                'minimize_scroll' => (bool) env('XCHANGE_CLAIM_UI_MINIMIZE_SCROLL', true),
            ],
            'copy' => [
                'entry_title' => env('XCHANGE_CLAIM_UI_ENTRY_TITLE', 'Claim Pay Code'),
                'wallet_title' => env('XCHANGE_CLAIM_UI_WALLET_TITLE', 'Where should we send the money?'),
                'confirmation_title' => env('XCHANGE_CLAIM_UI_CONFIRMATION_TITLE', 'Review your claim'),
                'success_title' => env('XCHANGE_CLAIM_UI_SUCCESS_TITLE', 'Claim completed'),
                'approval_waiting_title' => env('XCHANGE_CLAIM_UI_APPROVAL_WAITING_TITLE', 'Awaiting payout approval'),
                'issuer_otp_title' => env('XCHANGE_CLAIM_UI_ISSUER_OTP_TITLE', 'Approve payout OTP'),
            ],
            'permissions' => [
                'location' => [
                    'why' => env('XCHANGE_CLAIM_UI_LOCATION_WHY', 'This Pay Code requires location evidence.'),
                ],
                'camera' => [
                    'why' => env('XCHANGE_CLAIM_UI_CAMERA_WHY', 'This Pay Code requires selfie evidence.'),
                ],
                'signature' => [
                    'why' => env('XCHANGE_CLAIM_UI_SIGNATURE_WHY', 'This Pay Code requires signed confirmation.'),
                ],
                'kyc' => [
                    'why' => env('XCHANGE_CLAIM_UI_KYC_WHY', 'This Pay Code requires identity verification.'),
                ],
            ],
        ],
        'share' => [
            'public_image_middleware' => [
                'throttle:60,1',
            ],
            'cache_ttl_seconds' => (int) env(
                'XCHANGE_CLAIM_SHARE_CACHE_TTL_SECONDS',
                300,
            ),
            'artifact' => [
                'disk' => env(
                    'XCHANGE_CLAIM_SHARE_ARTIFACT_DISK',
                    'local',
                ),
                'directory' => env(
                    'XCHANGE_CLAIM_SHARE_ARTIFACT_DIRECTORY',
                    'x-change/claim/stamp-artifacts',
                ),
                'rendering_manifest_version' => env(
                    'XCHANGE_CLAIM_SHARE_RENDERING_MANIFEST_VERSION',
                    'x-change.rider-stamp-render-manifest.v2',
                ),
                'cache_ttl_seconds' => (int) env(
                    'XCHANGE_CLAIM_SHARE_ARTIFACT_CACHE_TTL_SECONDS',
                    31536000,
                ),
            ],
            'maximum_artwork_pixels' => (int) env(
                'XCHANGE_CLAIM_SHARE_MAXIMUM_ARTWORK_PIXELS',
                16000000,
            ),
            'recipient' => [
                'enabled' => (bool) env(
                    'XCHANGE_CLAIM_SHARE_SHOW_RECIPIENT',
                    true,
                ),
                'eyebrow' => env(
                    'XCHANGE_CLAIM_SHARE_RECIPIENT_EYEBROW',
                    'Prepared for',
                ),
                'fallback_label' => env(
                    'XCHANGE_CLAIM_SHARE_RECIPIENT_FALLBACK',
                    'Anyone with this Pay Code',
                ),
            ],
            'splash_artwork' => [
                'enabled' => (bool) env(
                    'XCHANGE_CLAIM_SHARE_SPLASH_ARTWORK_ENABLED',
                    true,
                ),
                'disk' => env(
                    'XCHANGE_CLAIM_SHARE_SPLASH_ARTWORK_DISK',
                    'local',
                ),
                'directory' => env(
                    'XCHANGE_CLAIM_SHARE_SPLASH_ARTWORK_DIRECTORY',
                    'x-change/claim/splash-artwork',
                ),
                'allowed_hosts' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) env(
                        'XCHANGE_CLAIM_SHARE_SPLASH_ARTWORK_HOSTS',
                        'raw.githubusercontent.com',
                    )),
                ))),
                'connect_timeout_seconds' => (int) env(
                    'XCHANGE_CLAIM_SHARE_SPLASH_ARTWORK_CONNECT_TIMEOUT_SECONDS',
                    3,
                ),
                'timeout_seconds' => (int) env(
                    'XCHANGE_CLAIM_SHARE_SPLASH_ARTWORK_TIMEOUT_SECONDS',
                    6,
                ),
                'retry_attempts' => (int) env(
                    'XCHANGE_CLAIM_SHARE_SPLASH_ARTWORK_RETRY_ATTEMPTS',
                    3,
                ),
                'retry_sleep_milliseconds' => (int) env(
                    'XCHANGE_CLAIM_SHARE_SPLASH_ARTWORK_RETRY_SLEEP_MILLISECONDS',
                    150,
                ),
                'maximum_image_bytes' => (int) env(
                    'XCHANGE_CLAIM_SHARE_SPLASH_ARTWORK_MAXIMUM_IMAGE_BYTES',
                    2097152,
                ),
            ],
            'site_name' => env(
                'XCHANGE_CLAIM_SHARE_SITE_NAME',
                env('XCHANGE_BRAND_NAME', env('XCHANGE_PRODUCT_NAME', 'X-Change')),
            ),
            'default_description' => env(
                'XCHANGE_CLAIM_SHARE_DEFAULT_DESCRIPTION',
                'A Pay Code is ready to claim securely in X-Change.',
            ),
            'copy' => [
                'default_title' => env(
                    'XCHANGE_CLAIM_SHARE_DEFAULT_TITLE',
                    'Pay Code',
                ),
                'url_description' => env(
                    'XCHANGE_CLAIM_SHARE_URL_DESCRIPTION',
                    'Continue to this link after the claim.',
                ),
                'splash_description' => env(
                    'XCHANGE_CLAIM_SHARE_SPLASH_DESCRIPTION',
                    'An introduction appears before the claim.',
                ),
            ],
            'default_image' => env(
                'XCHANGE_CLAIM_SHARE_DEFAULT_IMAGE',
                '/vendor/x-change/images/logo-orange.png',
            ),
        ],
    ],

    'claim_preview' => [
        'recorder' => [
            'node_binary' => env('XCHANGE_CLAIM_PREVIEW_NODE_BINARY'),
        ],
        'rider' => [
            'message' => env(
                'XCHANGE_CLAIM_PREVIEW_RIDER_MESSAGE',
                'The quick brown fox jumps over the lazy dog.',
            ),
            'url' => env(
                'XCHANGE_CLAIM_PREVIEW_RIDER_URL',
                'https://open.spotify.com/track/6kyxQuFD38mo4S3urD2Wkw?si=6yq6W4oRQ76HGpbDCG-74w&utm_source=copy-link&rowId=35e1bf6b4faf0da8',
            ),
            'redirect_timeout' => (int) env('XCHANGE_CLAIM_PREVIEW_RIDER_REDIRECT_TIMEOUT', 4),
            'splash_html' => env(
                'XCHANGE_CLAIM_PREVIEW_RIDER_SPLASH_HTML',
                $defaultClaimPreviewRiderSplashHtml,
            ),
            'splash_timeout' => env('XCHANGE_CLAIM_PREVIEW_RIDER_SPLASH_TIMEOUT'),
            'og_source' => env('XCHANGE_CLAIM_PREVIEW_RIDER_OG_SOURCE'),
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

        'attempts' => [
            'enabled' => env('X_CHANGE_PAYMENT_ATTEMPTS_ENABLED', true),
            'provider' => env('X_CHANGE_PAYMENT_ATTEMPT_PROVIDER', 'netbank'),
            'expires_after_minutes' => (int) env('X_CHANGE_PAYMENT_ATTEMPT_EXPIRES_AFTER_MINUTES', 15),
            'hash_key' => env('X_CHANGE_PAYMENT_ATTEMPT_HASH_KEY'),
            'instruction_lock_seconds' => 30,
            'instruction_lock_wait_seconds' => 5,
            'verification_lock_seconds' => 120,
            'verification_lock_wait_seconds' => 5,
            'verification_provider_rate_limit_per_minute' => 30,
            'scheduled_verification_enabled' => true,
            'scheduled_batch_size' => 100,
            'settlement_grace_seconds' => 300,
            'public_read_middleware' => [
                'throttle:60,1',
            ],
            'public_start_middleware' => [
                'throttle:12,1',
            ],
            'public_check_middleware' => [
                'throttle:6,1',
            ],
        ],

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
