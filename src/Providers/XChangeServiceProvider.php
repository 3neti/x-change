<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Providers;

use Composer\InstalledVersions;
use DateInterval;
use FrittenKeeZ\Vouchers\Models\Voucher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use InvalidArgumentException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passport\Passport;
use LBHurtado\Cash\Contracts\WithdrawalIntervalEnforcerContract;
use LBHurtado\EmiCore\Contracts\DeploymentConnectionContributor;
use LBHurtado\EmiCore\Contracts\DeploymentEnvironmentContributor;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Contracts\SettlementProviderRegistryContract;
use LBHurtado\EmiPaynamicsConstellation\Contracts\PendingOtpStore;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Services\TxtcmdrOtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Services\UnavailableOtpChallengeGateway;
use LBHurtado\Onboarding\Contracts\ContactUserProvisionerContract;
use LBHurtado\PaymentGateway\Adapters\NetbankPayoutProvider;
use LBHurtado\PaymentGateway\Contracts\WalletProxy;
use LBHurtado\PaymentGateway\Funding\NetbankReusableFundingAddressProvider;
use LBHurtado\ReportRegistry\Contracts\ReportResolverInterface;
use LBHurtado\Voucher\Contracts\SettlementEnvelopeExecutionGateway;
use LBHurtado\Voucher\Contracts\StoredValueExecutionGateway;
use LBHurtado\Voucher\Events\VoucherDisbursementFailed;
use LBHurtado\Voucher\Events\VoucherDisbursementSucceeded;
use LBHurtado\Voucher\Services\ExecutionDriverRegistry;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\XChange\Actions\Auth\AuthenticateMobileFirstUser;
use LBHurtado\XChange\Actions\Auth\CreateNewMobileFirstUser;
use LBHurtado\XChange\Actions\Auth\ResetMobileFirstPin;
use LBHurtado\XChange\Console\Commands\AdoptCommissioningManifestCommand;
use LBHurtado\XChange\Console\Commands\AdoptHostCommand;
use LBHurtado\XChange\Console\Commands\AdoptXChangeCommand;
use LBHurtado\XChange\Console\Commands\Claim\ClaimWalkthroughCommand;
use LBHurtado\XChange\Console\Commands\Claim\LoadPayCodeRedemptionCompletionContextCommand;
use LBHurtado\XChange\Console\Commands\Claim\PreparePayCodeRedemptionFlowCommand;
use LBHurtado\XChange\Console\Commands\Claim\SnapshotRiderSplashArtworkCommand;
use LBHurtado\XChange\Console\Commands\Claim\SubmitPayCodeClaimCommand;
use LBHurtado\XChange\Console\Commands\CloudRecipeCommand;
use LBHurtado\XChange\Console\Commands\Cockpit\SeedCockpitDiagnosticActivityCommand;
use LBHurtado\XChange\Console\Commands\Cockpit\ShowCockpitOperatorActivityRuntimeProfileCommand;
use LBHurtado\XChange\Console\Commands\Cockpit\ValidateLinkPreviewDriversCommand;
use LBHurtado\XChange\Console\Commands\Commercial\ApprovePartnerCommissionPayoutBatchCommand;
use LBHurtado\XChange\Console\Commands\Commercial\AuthorizeCommercialOperatorCommand;
use LBHurtado\XChange\Console\Commands\Commercial\CommercialGovernanceStatusCommand;
use LBHurtado\XChange\Console\Commands\Commercial\ProvisionCommercialBaselinesCommand;
use LBHurtado\XChange\Console\Commands\Commercial\ReconcilePartnerCommissionPayoutBatchCommand;
use LBHurtado\XChange\Console\Commands\Commercial\ReconcilePendingPartnerCommissionPayoutsCommand;
use LBHurtado\XChange\Console\Commands\Commercial\RecordProviderCostBatchCommand;
use LBHurtado\XChange\Console\Commands\Commercial\RequestPartnerCommissionPayoutBatchCommand;
use LBHurtado\XChange\Console\Commands\Commercial\SubmitPartnerCommissionPayoutBatchCommand;
use LBHurtado\XChange\Console\Commands\CommissioningStatusCommand;
use LBHurtado\XChange\Console\Commands\CommissionXChangeCommand;
use LBHurtado\XChange\Console\Commands\ConfigureXChangeCommand;
use LBHurtado\XChange\Console\Commands\DeployXChangeCommand;
use LBHurtado\XChange\Console\Commands\Disbursement\CheckDisbursementStatusCommand;
use LBHurtado\XChange\Console\Commands\Disbursement\RestoreUnsubmittedPayoutCorrectionCommand;
use LBHurtado\XChange\Console\Commands\Disbursement\ResumeMissingTreasuryPayCodeDisbursementCommand;
use LBHurtado\XChange\Console\Commands\Disbursement\RetryFailedPayCodeDisbursementCommand;
use LBHurtado\XChange\Console\Commands\DoctorXChangeCommand;
use LBHurtado\XChange\Console\Commands\Feedback\ShowFeedbackHistoryCommand;
use LBHurtado\XChange\Console\Commands\Feedback\TestFeedbackEmailCommand;
use LBHurtado\XChange\Console\Commands\Feedback\TestFeedbackSmsCommand;
use LBHurtado\XChange\Console\Commands\Funding\ApproveFundingRequestCommand;
use LBHurtado\XChange\Console\Commands\Funding\AttestAccountFundingPayCodeJournalIntegrityCommand;
use LBHurtado\XChange\Console\Commands\Funding\BackfillAccountFundingPayCodeJournalCommand;
use LBHurtado\XChange\Console\Commands\Funding\IssueSystemAccountFundingPayCodeCommand;
use LBHurtado\XChange\Console\Commands\Funding\SyncStandingFundingAddressesCommand;
use LBHurtado\XChange\Console\Commands\Funding\VerifyFundingRequestBackingCommand;
use LBHurtado\XChange\Console\Commands\Funding\VerifyOpenFundingIntentsCommand;
use LBHurtado\XChange\Console\Commands\GenerateDeploymentManifestCommand;
use LBHurtado\XChange\Console\Commands\InspectXChangeConfigurationCommand;
use LBHurtado\XChange\Console\Commands\InstallXChangeCommand;
use LBHurtado\XChange\Console\Commands\Lifecycle\PrepareLifecycleEnvironmentCommand;
use LBHurtado\XChange\Console\Commands\Lifecycle\RunLifecycleScenarioCommand;
use LBHurtado\XChange\Console\Commands\Lifecycle\RunLifecycleScenarioGroupCommand;
use LBHurtado\XChange\Console\Commands\Onboarding\OnboardIssuerCommand;
use LBHurtado\XChange\Console\Commands\Onboarding\OpenIssuerWalletCommand;
use LBHurtado\XChange\Console\Commands\Onboarding\VerifyTestMobileCommand;
use LBHurtado\XChange\Console\Commands\PartnerApi\AuthorizePartnerApiOperatorCommand;
use LBHurtado\XChange\Console\Commands\PartnerApi\CreatePartnerApiClientCommand;
use LBHurtado\XChange\Console\Commands\PartnerApi\RunPartnerApiLifecycleCommand;
use LBHurtado\XChange\Console\Commands\PayCode\EstimatePayCodeCostCommand;
use LBHurtado\XChange\Console\Commands\PayCode\GeneratePayCodeCommand;
use LBHurtado\XChange\Console\Commands\Payment\VerifyOpenPaymentAttemptsCommand;
use LBHurtado\XChange\Console\Commands\Provisioning\AuthorizeProvisioningOperatorCommand;
use LBHurtado\XChange\Console\Commands\Provisioning\ExpireProvisioningOffersCommand;
use LBHurtado\XChange\Console\Commands\Provisioning\ProvisionCommissioningSeatsCommand;
use LBHurtado\XChange\Console\Commands\PublishXChangeCommand;
use LBHurtado\XChange\Console\Commands\ReconcilePendingDisbursementsCommand;
use LBHurtado\XChange\Console\Commands\Revenue\CollectRevenueCommand;
use LBHurtado\XChange\Console\Commands\Revenue\ShowPendingRevenueCommand;
use LBHurtado\XChange\Console\Commands\Settlement\EvaluateSettlementEnvelopeCommand;
use LBHurtado\XChange\Console\Commands\SetupXChangeCommand;
use LBHurtado\XChange\Console\Commands\Treasury\AttestCommercialAccountingCommand;
use LBHurtado\XChange\Console\Commands\Treasury\AuthorizeTreasuryOperatorCommand;
use LBHurtado\XChange\Console\Commands\Treasury\BackfillCommercialAccountingJournalCommand;
use LBHurtado\XChange\Console\Commands\Treasury\BackfillDisbursementSettlementJournalCommand;
use LBHurtado\XChange\Console\Commands\Treasury\BackfillStandingFundingPositionsCommand;
use LBHurtado\XChange\Console\Commands\Treasury\CapitalizeTreasuryOpeningBalanceCommand;
use LBHurtado\XChange\Console\Commands\Treasury\CorrectLegacyPayCodeFeePostingCommand;
use LBHurtado\XChange\Console\Commands\Treasury\MigrateLegacyAccountBalanceCommand;
use LBHurtado\XChange\Console\Commands\Treasury\PreflightTreasuryCommand;
use LBHurtado\XChange\Console\Commands\Treasury\ProvisionSystemPrincipalCommand;
use LBHurtado\XChange\Console\Commands\Treasury\ProvisionTreasuryCommand;
use LBHurtado\XChange\Console\Commands\Treasury\ReconcileOpeningTreasuryBalanceCommand;
use LBHurtado\XChange\Console\Commands\Treasury\RedactTreasurySensitiveMetadataCommand;
use LBHurtado\XChange\Console\Commands\Treasury\RefreshTreasuryLiquidityCommand;
use LBHurtado\XChange\Console\Commands\Treasury\ReleaseExpiredPayCodeReservesCommand;
use LBHurtado\XChange\Console\Commands\Treasury\RepairMissingDisbursementPostingsCommand;
use LBHurtado\XChange\Console\Commands\Treasury\SimulateTreasuryProviderDepositCommand;
use LBHurtado\XChange\Console\Commands\ValidateDeploymentManifestCommand;
use LBHurtado\XChange\Console\Commands\Wallet\GetWalletBalanceCommand;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\AccountProvisioningContract;
use LBHurtado\XChange\Contracts\ApprovalWorkflowContract;
use LBHurtado\XChange\Contracts\CampaignBankTransferDispatcherContract;
use LBHurtado\XChange\Contracts\CampaignBankTransferStatusCheckerContract;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalStatusResolver;
use LBHurtado\XChange\Contracts\Claim\ClaimSurfaceResolverContract;
use LBHurtado\XChange\Contracts\Claim\ClaimViewerResolverContract;
use LBHurtado\XChange\Contracts\ClaimApprovalExecutionContract;
use LBHurtado\XChange\Contracts\ClaimApprovalInitiationContract;
use LBHurtado\XChange\Contracts\ClaimApprovalNotificationContract;
use LBHurtado\XChange\Contracts\ClaimApprovalWorkflowStoreContract;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Contracts\ClaimOtpChallengeContract;
use LBHurtado\XChange\Contracts\ClaimOtpVerificationContract;
use LBHurtado\XChange\Contracts\ClaimShareCardRendererContract;
use LBHurtado\XChange\Contracts\ClaimShareCardUrlResolverContract;
use LBHurtado\XChange\Contracts\ClaimShareMetadataResolverContract;
use LBHurtado\XChange\Contracts\ClaimUrlQrRendererContract;
use LBHurtado\XChange\Contracts\ClaimWorkflowResolverContract;
use LBHurtado\XChange\Contracts\CockpitCampaignIssuanceDraftAdapterContract;
use LBHurtado\XChange\Contracts\CockpitHeaderReadModelProviderContract;
use LBHurtado\XChange\Contracts\CockpitIssuanceDraftAuditMetadataBuilderContract;
use LBHurtado\XChange\Contracts\CockpitIssuanceDraftCompilerContract;
use LBHurtado\XChange\Contracts\CockpitIssuanceDraftValidatorContract;
use LBHurtado\XChange\Contracts\CockpitIssuanceTemplateRegistryContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityActionHandoffContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityFeedbackHandoffContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectorContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityPresenterContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRecorderContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRedactionPolicyContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRepositoryContract;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRetentionPolicyContract;
use LBHurtado\XChange\Contracts\CockpitQuickGenerateDraftFactoryContract;
use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Contracts\CockpitRedactorContract;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;
use LBHurtado\XChange\Contracts\CommercialLegalTraceResolverContract;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Contracts\CommercialPartnerResolverContract;
use LBHurtado\XChange\Contracts\Deployment\CloudMutationGatewayContract;
use LBHurtado\XChange\Contracts\Deployment\CloudStateReaderContract;
use LBHurtado\XChange\Contracts\DisbursementReconciliationContract;
use LBHurtado\XChange\Contracts\DisbursementReconciliationStoreContract;
use LBHurtado\XChange\Contracts\DisbursementStatusFetcherContract;
use LBHurtado\XChange\Contracts\DisbursementStatusResolverContract;
use LBHurtado\XChange\Contracts\EventLifecycleServiceContract;
use LBHurtado\XChange\Contracts\EventStoreContract;
use LBHurtado\XChange\Contracts\ExecutionCashDisbursementPollerContract;
use LBHurtado\XChange\Contracts\ExecutionResultActionHandoffContract;
use LBHurtado\XChange\Contracts\ExecutionResultCockpitActivityHandoffContract;
use LBHurtado\XChange\Contracts\ExecutionResultFeedbackHandoffContract;
use LBHurtado\XChange\Contracts\ExecutionResultHandoffPipelineContract;
use LBHurtado\XChange\Contracts\ExecutionResultHandoffSummaryJournalWriterContract;
use LBHurtado\XChange\Contracts\ExecutionResultJournalHandoffContract;
use LBHurtado\XChange\Contracts\FeedbackDeliveryJournalWriterContract;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Contracts\FundingAccountRecoveryContract;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Contracts\FundingProjectionPublisherContract;
use LBHurtado\XChange\Contracts\InstructionCapabilityContributor;
use LBHurtado\XChange\Contracts\LifecycleScenarioContributor;
use LBHurtado\XChange\Contracts\MinimumWithdrawalPolicyResolverContract;
use LBHurtado\XChange\Contracts\MoneyMovementAccountingDecisionContract;
use LBHurtado\XChange\Contracts\MoneyMovementLifecycleTriggerMatrixContract;
use LBHurtado\XChange\Contracts\MoneyMovementTargetModelContract;
use LBHurtado\XChange\Contracts\PayCodePresentationResolverContract;
use LBHurtado\XChange\Contracts\PayoutDestinationValidatorContract;
use LBHurtado\XChange\Contracts\PricelistServiceContract;
use LBHurtado\XChange\Contracts\PricingServiceContract;
use LBHurtado\XChange\Contracts\ProviderFundingPolicyContract;
use LBHurtado\XChange\Contracts\ProviderPayoutDestinationValidationContract;
use LBHurtado\XChange\Contracts\Publication\XChangePublicationContributor;
use LBHurtado\XChange\Contracts\ReconciliationLifecycleServiceContract;
use LBHurtado\XChange\Contracts\RedemptionCompletionContextContract;
use LBHurtado\XChange\Contracts\RedemptionCompletionStoreContract;
use LBHurtado\XChange\Contracts\RedemptionContextResolverContract;
use LBHurtado\XChange\Contracts\RedemptionExecutionContract;
use LBHurtado\XChange\Contracts\RedemptionFlowPreparationContract;
use LBHurtado\XChange\Contracts\RedemptionProcessorContract;
use LBHurtado\XChange\Contracts\RedemptionValidationContract;
use LBHurtado\XChange\Contracts\RiderSplashArtworkSnapshotterContract;
use LBHurtado\XChange\Contracts\RiderStampArtifactStoreContract;
use LBHurtado\XChange\Contracts\RiderStampClaimCardComposerContract;
use LBHurtado\XChange\Contracts\RiderStampCopyResolverContract;
use LBHurtado\XChange\Contracts\RiderStampRecipientResolverContract;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Contracts\SettlementExecutionContract;
use LBHurtado\XChange\Contracts\SettlementFlowPreparationContract;
use LBHurtado\XChange\Contracts\SettlementReadinessGateContract;
use LBHurtado\XChange\Contracts\SystemAccountFundingPayCodeAuthorizationContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\TreasuryOpeningCapitalizationAuthorizationContract;
use LBHurtado\XChange\Contracts\TreasuryPositionLedgerResolverContract;
use LBHurtado\XChange\Contracts\TreasuryPrincipalReferenceResolverContract;
use LBHurtado\XChange\Contracts\TreasuryVocabularyReadModelContract;
use LBHurtado\XChange\Contracts\UserLifecycleServiceContract;
use LBHurtado\XChange\Contracts\VendorRegistryContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Contracts\VoucherCollectionWalletResolverContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Contracts\VoucherLiabilitySummaryContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Contracts\VoucherOperationalStatusResolverContract;
use LBHurtado\XChange\Contracts\VoucherPaymentConfirmationContract;
use LBHurtado\XChange\Contracts\VoucherPaymentProviderContract;
use LBHurtado\XChange\Contracts\VoucherPaymentQrGeneratorContract;
use LBHurtado\XChange\Contracts\VoucherPaymentQrRendererContract;
use LBHurtado\XChange\Contracts\WithdrawalExecutionContract;
use LBHurtado\XChange\Contracts\WithdrawalLifecycleServiceContract;
use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;
use LBHurtado\XChange\Contracts\WithdrawalProcessorContract;
use LBHurtado\XChange\Contracts\WithdrawalValidationContract;
use LBHurtado\XChange\Contracts\XChangeOnboardingGatewayContract;
use LBHurtado\XChange\Contracts\XChangeProviderTopologyResolverContract;
use LBHurtado\XChange\Events\DisbursementConfirmed;
use LBHurtado\XChange\Events\DisbursementRejected;
use LBHurtado\XChange\Exceptions\FundingIntentConflict;
use LBHurtado\XChange\Exceptions\FundingIntentTransitionDenied;
use LBHurtado\XChange\Exceptions\FundingProviderUnavailable;
use LBHurtado\XChange\Exceptions\IdempotencyConflict;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceBusy;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Exceptions\PayCodeIssuerNotResolved;
use LBHurtado\XChange\Exceptions\PayCodeWalletNotResolved;
use LBHurtado\XChange\Exceptions\ProviderProvisioningRequired;
use LBHurtado\XChange\Exceptions\VoucherCannotCollect;
use LBHurtado\XChange\Exceptions\VoucherCannotDisburse;
use LBHurtado\XChange\Exceptions\VoucherCollectionConflict;
use LBHurtado\XChange\Exceptions\VoucherFlowCapabilityException;
use LBHurtado\XChange\Exceptions\VoucherNotFound;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;
use LBHurtado\XChange\Http\Middleware\EnsureXChangeIsCommissioned;
use LBHurtado\XChange\Http\Middleware\RequireInitialPinSetup;
use LBHurtado\XChange\Http\Responses\MobileFirstRegisterResponse;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRepository;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleUserModelResolver;
use LBHurtado\XChange\Listeners\HandleConfirmedDisbursement;
use LBHurtado\XChange\Listeners\HandleRejectedDisbursement;
use LBHurtado\XChange\Listeners\RecordFailedVoucherDisbursement;
use LBHurtado\XChange\Listeners\RecordSuccessfulVoucherDisbursement;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\Base64PngClaimUrlQrRenderer;
use LBHurtado\XChange\Services\CacheClaimApprovalWorkflowStore;
use LBHurtado\XChange\Services\Campaigns\NetbankCampaignBankTransferDispatcher;
use LBHurtado\XChange\Services\Campaigns\NetbankCampaignBankTransferStatusChecker;
use LBHurtado\XChange\Services\Campaigns\NullCampaignBankTransferDispatcher;
use LBHurtado\XChange\Services\Campaigns\NullCampaignBankTransferStatusChecker;
use LBHurtado\XChange\Services\Claim\ClaimViewerResolver;
use LBHurtado\XChange\Services\Claim\DefaultClaimShareCardUrlResolver;
use LBHurtado\XChange\Services\Claim\DefaultClaimSurfaceResolver;
use LBHurtado\XChange\Services\Claim\DefaultClaimWorkflowResolver;
use LBHurtado\XChange\Services\Claim\DefaultRiderSplashArtworkSnapshotter;
use LBHurtado\XChange\Services\Claim\DefaultRiderStampArtifactStore;
use LBHurtado\XChange\Services\Claim\DefaultRiderStampCopyResolver;
use LBHurtado\XChange\Services\Claim\DefaultRiderStampRecipientResolver;
use LBHurtado\XChange\Services\Claim\GdRiderStampClaimShareCardRenderer;
use LBHurtado\XChange\Services\Claim\RiderStampClaimShareMetadataResolver;
use LBHurtado\XChange\Services\Claim\StoredRiderStampClaimShareCardRenderer;
use LBHurtado\XChange\Services\Cockpit\CockpitPayCodeDetailProjection;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitCampaignIssuanceDraftAdapter;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitIssuanceDraftAuditMetadataBuilder;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitIssuanceDraftCompiler;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitIssuanceDraftValidator;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitIssuanceTemplateRegistry;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitOperatorIssuanceActivityPresenter;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitOperatorIssuanceActivityRedactionPolicy;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitOperatorIssuanceActivityRetentionPolicy;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitQuickGenerateDraftFactory;
use LBHurtado\XChange\Services\Cockpit\DurableCockpitOperatorIssuanceActivityReadModelProvider;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityActionHandoff;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityActionHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityFeedbackHandoff;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityJournalHandoff;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityJournalHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\Cockpit\NullCockpitReadModelProvider;
use LBHurtado\XChange\Services\Cockpit\OptionalCockpitIntegrationReadModels;
use LBHurtado\XChange\Services\Cockpit\SystemPrincipalCockpitTreasuryAccess;
use LBHurtado\XChange\Services\Cockpit\VoucherLifecycleCockpitReadModelProvider;
use LBHurtado\XChange\Services\Cockpit\WalletCockpitHeaderReadModelProvider;
use LBHurtado\XChange\Services\Commercial\ConfigCommercialPartnerResolver;
use LBHurtado\XChange\Services\Commercial\DatabaseCommercialOfferingResolver;
use LBHurtado\XChange\Services\Commercial\DatabaseCommercialOperatorAuthority;
use LBHurtado\XChange\Services\Commercial\OptionalXLegalCommercialTraceResolver;
use LBHurtado\XChange\Services\ConfigMinimumWithdrawalPolicyResolver;
use LBHurtado\XChange\Services\ConfigProviderTopologyResolver;
use LBHurtado\XChange\Services\Configuration\CoreDeploymentEnvironmentContributor;
use LBHurtado\XChange\Services\Configuration\CoreInstructionCapabilityContributor;
use LBHurtado\XChange\Services\Configuration\DeploymentConfigurationInspector;
use LBHurtado\XChange\Services\Configuration\DeploymentConnectionCatalog;
use LBHurtado\XChange\Services\Configuration\DeploymentEnvironmentCatalog;
use LBHurtado\XChange\Services\Configuration\DeploymentProfileCatalog;
use LBHurtado\XChange\Services\Configuration\DeploymentTreasuryConnectionConfiguration;
use LBHurtado\XChange\Services\Configuration\InstructionCapabilityReadinessRegistry;
use LBHurtado\XChange\Services\ConfigVendorRegistry;
use LBHurtado\XChange\Services\DefaultApprovalWorkflowService;
use LBHurtado\XChange\Services\DefaultClaimApprovalExecutionService;
use LBHurtado\XChange\Services\DefaultClaimApprovalInitiationService;
use LBHurtado\XChange\Services\DefaultClaimExecutionFactory;
use LBHurtado\XChange\Services\DefaultDisbursementReconciliationService;
use LBHurtado\XChange\Services\DefaultDisbursementReconciliationStore;
use LBHurtado\XChange\Services\DefaultDisbursementStatusFetcherService;
use LBHurtado\XChange\Services\DefaultDisbursementStatusResolverService;
use LBHurtado\XChange\Services\DefaultMoneyMovementAccountingDecisionService;
use LBHurtado\XChange\Services\DefaultMoneyMovementLifecycleTriggerMatrixService;
use LBHurtado\XChange\Services\DefaultMoneyMovementTargetModelService;
use LBHurtado\XChange\Services\DefaultPayCodePresentationResolver;
use LBHurtado\XChange\Services\DefaultRedemptionCompletionContextService;
use LBHurtado\XChange\Services\DefaultRedemptionContextResolverService;
use LBHurtado\XChange\Services\DefaultRedemptionExecutionService;
use LBHurtado\XChange\Services\DefaultRedemptionFlowPreparationService;
use LBHurtado\XChange\Services\DefaultRedemptionProcessorService;
use LBHurtado\XChange\Services\DefaultRedemptionValidationService;
use LBHurtado\XChange\Services\DefaultSettlementExecutionService;
use LBHurtado\XChange\Services\DefaultSettlementFlowPreparationService;
use LBHurtado\XChange\Services\DefaultSettlementReadinessGate;
use LBHurtado\XChange\Services\DefaultVoucherCollectionWalletResolver;
use LBHurtado\XChange\Services\DefaultVoucherFlowCapabilityResolver;
use LBHurtado\XChange\Services\DefaultVoucherOperationalStatusResolver;
use LBHurtado\XChange\Services\DefaultVoucherPaymentConfirmationService;
use LBHurtado\XChange\Services\DefaultVoucherPaymentQrGenerator;
use LBHurtado\XChange\Services\DefaultWithdrawalExecutionService;
use LBHurtado\XChange\Services\DefaultWithdrawalProcessorService;
use LBHurtado\XChange\Services\DefaultWithdrawalValidationService;
use LBHurtado\XChange\Services\DefaultXChangeOnboardingGateway;
use LBHurtado\XChange\Services\Deployment\LaravelCloudCliMutationGateway;
use LBHurtado\XChange\Services\Deployment\LaravelCloudCliStateReader;
use LBHurtado\XChange\Services\Disbursement\LayeredPayoutDestinationValidator;
use LBHurtado\XChange\Services\Disbursement\UnavailableProviderPayoutDestinationValidation;
use LBHurtado\XChange\Services\EventLifecycleService;
use LBHurtado\XChange\Services\Execution\ExecutionAwarePostRedemptionGate;
use LBHurtado\XChange\Services\Execution\ExecutionResultHandoffPipeline;
use LBHurtado\XChange\Services\Execution\LifecycleExecutionCashDisbursementPoller;
use LBHurtado\XChange\Services\Execution\NullExecutionResultActionHandoff;
use LBHurtado\XChange\Services\Execution\NullExecutionResultCockpitActivityHandoff;
use LBHurtado\XChange\Services\Execution\NullExecutionResultFeedbackHandoff;
use LBHurtado\XChange\Services\Execution\NullExecutionResultHandoffSummaryJournalWriter;
use LBHurtado\XChange\Services\Execution\NullExecutionResultJournalHandoff;
use LBHurtado\XChange\Services\Execution\OnboardingAccountProvisioningExecutionDriver;
use LBHurtado\XChange\Services\Execution\XChangeLiveCashExecutionDriver;
use LBHurtado\XChange\Services\Execution\XChangeSettlementEnvelopeExecutionGateway;
use LBHurtado\XChange\Services\Execution\XChangeStoredValueExecutionGateway;
use LBHurtado\XChange\Services\Feedback\QueuedEngageSparkSmsFeedbackChannelDriver;
use LBHurtado\XChange\Services\Feedback\XJournalFeedbackDeliveryWriter;
use LBHurtado\XChange\Services\Funding\BavixFundingAccountCredit;
use LBHurtado\XChange\Services\Funding\BroadcastFundingProjectionPublisher;
use LBHurtado\XChange\Services\Funding\ConfigSystemAccountFundingPayCodeAuthorization;
use LBHurtado\XChange\Services\Funding\DefaultFundingDestinationResolver;
use LBHurtado\XChange\Services\Funding\FundingProjectionChannel;
use LBHurtado\XChange\Services\Funding\FundingProviderAdapterRegistry;
use LBHurtado\XChange\Services\Funding\QrPhSimulatorFundingProviderAdapter;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressProviderRegistry;
use LBHurtado\XChange\Services\InstructionBackedPricingService;
use LBHurtado\XChange\Services\LinkPreview\LinkCanonicalizerRegistry;
use LBHurtado\XChange\Services\LinkPreview\LinkPreviewDriverRepository;
use LBHurtado\XChange\Services\LinkPreview\LinkPreviewEngine;
use LBHurtado\XChange\Services\NullClaimApprovalNotificationService;
use LBHurtado\XChange\Services\NullRedemptionCompletionStore;
use LBHurtado\XChange\Services\NullSettlementEnvelopeReadinessService;
use LBHurtado\XChange\Services\NullWithdrawalOtpApprovalService;
use LBHurtado\XChange\Services\Onboarding\DefaultAccountProvisioningService;
use LBHurtado\XChange\Services\Onboarding\XChangeContactUserProvisioner;
use LBHurtado\XChange\Services\OnboardingVoucherInstructionPolicy;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiRequestContext;
use LBHurtado\XChange\Services\Payment\AccountFundingCollectionPosting;
use LBHurtado\XChange\Services\Payment\ProviderFundingCollectionPosting;
use LBHurtado\XChange\Services\Payment\ProviderWalletCollectionPosting;
use LBHurtado\XChange\Services\Payment\VoucherCollectionPostingRegistry;
use LBHurtado\XChange\Services\PaymentProviders\ManualVoucherPaymentProvider;
use LBHurtado\XChange\Services\PaynamicsWithdrawalOtpApprovalService;
use LBHurtado\XChange\Services\PayoutProviderResolver;
use LBHurtado\XChange\Services\PricelistService;
use LBHurtado\XChange\Services\ProviderAwareFundingPolicy;
use LBHurtado\XChange\Services\Provisioning\XChangeProvisioningActorGuard;
use LBHurtado\XChange\Services\Provisioning\XChangeProvisioningEvidenceVerifier;
use LBHurtado\XChange\Services\ProvisioningAwareOnboardingService;
use LBHurtado\XChange\Services\Publication\CorePublicationContributor;
use LBHurtado\XChange\Services\Publication\PublicationCatalog;
use LBHurtado\XChange\Services\ReconciliationLifecycleService;
use LBHurtado\XChange\Services\SettlementCollectionGate;
use LBHurtado\XChange\Services\SettlementEnvelopeReadinessService;
use LBHurtado\XChange\Services\StartProviderProvisioningFromOnboardingCompletion;
use LBHurtado\XChange\Services\SystemWalletProxy;
use LBHurtado\XChange\Services\Treasury\AdvisoryTreasuryVocabularyReadModel;
use LBHurtado\XChange\Services\Treasury\BavixTreasuryPositionLedgerResolver;
use LBHurtado\XChange\Services\Treasury\ConfigTreasuryOpeningCapitalizationAuthorization;
use LBHurtado\XChange\Services\Treasury\FederatedTreasuryPrincipalReferenceResolver;
use LBHurtado\XChange\Services\Treasury\LegacyAccountBalanceMigrationService;
use LBHurtado\XChange\Services\Treasury\TreasuryAccountBalanceReadModel;
use LBHurtado\XChange\Services\Treasury\TreasuryAccountPortfolioProvisioningService;
use LBHurtado\XChange\Services\Treasury\TreasuryInventoryRegistrationService;
use LBHurtado\XChange\Services\Treasury\TreasuryOpeningBalanceReconciliationService;
use LBHurtado\XChange\Services\Treasury\TreasuryPreflightService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XChange\Services\Treasury\VerifiedTreasuryFundingAllocationService;
use LBHurtado\XChange\Services\TxtcmdrWithdrawalOtpApprovalService;
use LBHurtado\XChange\Services\UserLifecycleService;
use LBHurtado\XChange\Services\VoucherAccessService;
use LBHurtado\XChange\Services\VoucherLiabilitySummaryService;
use LBHurtado\XChange\Services\VoucherLifecycleService;
use LBHurtado\XChange\Services\VoucherPaymentQrRendererFactory;
use LBHurtado\XChange\Services\WithdrawalLifecycleService;
use LBHurtado\XChange\Services\WithdrawalPipeline;
use LBHurtado\XChange\Services\XChangeWithdrawalIntervalEnforcer;
use LBHurtado\XChange\Support\Claim\ClaimApprovalPendingOtpStore;
use LBHurtado\XChange\Support\Claim\ClaimAuthenticationIntent;
use LBHurtado\XChange\Support\Claim\DefaultClaimApprovalStatusResolver;
use LBHurtado\XChange\Support\Cockpit\DefaultCockpitRedactor;
use LBHurtado\XChange\Support\Logging\CacheEventStore;
use LBHurtado\XFeedback\Contracts\FeedbackChannelRegistryContract;
use LBHurtado\XProvisioning\Contracts\ProvisioningActorGuardContract;
use LBHurtado\XProvisioning\Contracts\ProvisioningEvidenceVerifierContract;

class XChangeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            $this->packagePath('config/x-change.php'),
            'x-change'
        );
        $this->app->singleton(
            ProvisioningActorGuardContract::class,
            XChangeProvisioningActorGuard::class,
        );
        $this->app->singleton(
            ProvisioningEvidenceVerifierContract::class,
            XChangeProvisioningEvidenceVerifier::class,
        );
        $this->app->scoped(PartnerApiRequestContext::class);
        $this->configureIdentityOtpGateway();
        $this->configureFormFlowSplashDefaults();
        $this->app->singleton(OtpChallengeGateway::class, function ($app): OtpChallengeGateway {
            return match (config('x-change.onboarding.identity_otp.driver', 'unavailable')) {
                'txtcmdr' => $app->make(TxtcmdrOtpChallengeGateway::class),
                default => $app->make(UnavailableOtpChallengeGateway::class),
            };
        });
        $this->mergeLifecycleScenarioDefaults();

        $this->app->singleton(LinkCanonicalizerRegistry::class);
        $this->app->singleton(LinkPreviewDriverRepository::class);
        $this->app->singleton(LinkPreviewEngine::class);

        $this->app->singleton(CoreDeploymentEnvironmentContributor::class);
        $this->app->tag(
            CoreDeploymentEnvironmentContributor::class,
            DeploymentEnvironmentContributor::class,
        );
        $this->app->singleton(
            DeploymentEnvironmentCatalog::class,
            fn ($app): DeploymentEnvironmentCatalog => new DeploymentEnvironmentCatalog(
                $app->tagged(DeploymentEnvironmentContributor::class),
            ),
        );
        $this->app->singleton(CoreInstructionCapabilityContributor::class);
        $this->app->tag(
            CoreInstructionCapabilityContributor::class,
            InstructionCapabilityContributor::class,
        );
        $this->app->singleton(
            InstructionCapabilityReadinessRegistry::class,
            fn ($app): InstructionCapabilityReadinessRegistry => new InstructionCapabilityReadinessRegistry(
                $app->tagged(InstructionCapabilityContributor::class),
            ),
        );
        $this->app->singleton(
            DeploymentConnectionCatalog::class,
            fn ($app): DeploymentConnectionCatalog => new DeploymentConnectionCatalog(
                $app->tagged(DeploymentConnectionContributor::class),
            ),
        );
        $this->app->singleton(DeploymentProfileCatalog::class);
        $this->app->singleton(
            CommercialPartnerResolverContract::class,
            ConfigCommercialPartnerResolver::class,
        );
        $this->app->singleton(
            CommercialOfferingResolverContract::class,
            DatabaseCommercialOfferingResolver::class,
        );
        $this->app->singleton(
            CommercialOperatorAuthorityContract::class,
            DatabaseCommercialOperatorAuthority::class,
        );
        $this->app->singleton(
            CommercialLegalTraceResolverContract::class,
            OptionalXLegalCommercialTraceResolver::class,
        );
        $this->app->singleton(DeploymentTreasuryConnectionConfiguration::class);
        $this->app->singleton(DeploymentConfigurationInspector::class);
        $this->app->singleton(CorePublicationContributor::class);
        $this->app->tag(
            CorePublicationContributor::class,
            XChangePublicationContributor::class,
        );
        $this->app->singleton(
            PublicationCatalog::class,
            fn ($app): PublicationCatalog => new PublicationCatalog(
                $app->tagged(XChangePublicationContributor::class),
            ),
        );
        $this->app->singleton(CloudStateReaderContract::class, LaravelCloudCliStateReader::class);
        $this->app->singleton(CloudMutationGatewayContract::class, LaravelCloudCliMutationGateway::class);
        $this->app->singleton(LifecycleUserModelResolver::class);
        $this->app->singleton(
            LifecycleScenarioRepository::class,
            fn ($app): LifecycleScenarioRepository => new LifecycleScenarioRepository(
                $app->tagged(LifecycleScenarioContributor::class),
            ),
        );

        if ($this->mobileFirstAuthEnabled()) {
            $this->app['config']->set('fortify.username', 'mobile');
            $this->app['config']->set('fortify.lowercase_usernames', false);
            $this->app['config']->set('fortify.home', '/x/cockpit');
        }

        $this->alignWalletDefaults();
        $this->alignVoucherDefaults();
        $this->alignAccountSystemUser();
        $this->alignSettlementEnvelopeDefaults();
        $this->app->afterResolving(
            FeedbackChannelRegistryContract::class,
            static function (FeedbackChannelRegistryContract $registry): void {
                $registry->register(
                    'sms',
                    QueuedEngageSparkSmsFeedbackChannelDriver::class,
                );
            },
        );
        $this->app->singleton(QrPhSimulatorFundingProviderAdapter::class);
        $this->app->tag(
            QrPhSimulatorFundingProviderAdapter::class,
            'emi.funding-provider-adapters',
        );

        $this->app->singleton(
            FundingProviderAdapterRegistry::class,
            fn ($app) => new FundingProviderAdapterRegistry(
                $app->tagged('emi.funding-provider-adapters'),
            ),
        );
        $this->app->singleton(NetbankReusableFundingAddressProvider::class);
        $this->app->tag(
            NetbankReusableFundingAddressProvider::class,
            'emi.standing-funding-address-providers',
        );
        $this->app->singleton(
            StandingFundingAddressProviderRegistry::class,
            fn ($app) => new StandingFundingAddressProviderRegistry(
                $app->tagged('emi.standing-funding-address-providers'),
            ),
        );
        $this->app->singleton(
            TreasuryProviderConnectionCatalog::class,
            fn ($app): TreasuryProviderConnectionCatalog => new TreasuryProviderConnectionCatalog(
                $app->make(DeploymentTreasuryConnectionConfiguration::class)->resolve(),
            ),
        );
        $this->app->singleton(
            TreasuryPreflightService::class,
            fn ($app): TreasuryPreflightService => new TreasuryPreflightService(
                $app->make(TreasuryProviderConnectionCatalog::class),
                $app->make(SettlementProviderRegistryContract::class),
                $app->tagged('emi.provider-readiness-probes'),
                $app->tagged('emi.provider-live-preflight-probes'),
            ),
        );
        $this->app->singleton(TreasuryProvisioningService::class);
        $this->app->singleton(TreasuryInventoryRegistrationService::class);
        $this->app->singleton(LegacyAccountBalanceMigrationService::class);
        $this->app->singleton(
            TreasuryOpeningBalanceReconciliationService::class,
            fn ($app): TreasuryOpeningBalanceReconciliationService => new TreasuryOpeningBalanceReconciliationService(
                $app->make(TreasuryPreflightService::class),
                $app->make(TreasuryProvisioningService::class),
                $app->make(TreasuryInventoryOperationContract::class),
                $app->make(TreasuryInventoryRegistrationService::class),
                $app->make(TreasuryInventoryPositionReadModelContract::class),
                $app->make(TreasuryPositionOperationContract::class),
                $app->make(TreasuryPositionReadModelContract::class),
                $app->tagged('emi.provider-balance-readers'),
            ),
        );
        $this->app->singleton(
            TreasuryPrincipalReferenceResolverContract::class,
            FederatedTreasuryPrincipalReferenceResolver::class,
        );
        $this->app->singleton(
            TreasuryAccountPortfolioProvisioningContract::class,
            TreasuryAccountPortfolioProvisioningService::class,
        );
        $this->app->singleton(
            VerifiedTreasuryFundingAllocationContract::class,
            VerifiedTreasuryFundingAllocationService::class,
        );
        $this->app->singleton(
            TreasuryPositionLedgerResolverContract::class,
            BavixTreasuryPositionLedgerResolver::class,
        );
        $this->app->singleton(
            AccountBalanceReadModelContract::class,
            TreasuryAccountBalanceReadModel::class,
        );
        $this->app->singleton(
            FundingProjectionPublisherContract::class,
            BroadcastFundingProjectionPublisher::class,
        );

        $this->registerServices();
        $this->registerFundingAccountServices();
        $this->registerIntegrations();
        $this->registerServiceContracts();
        $this->registerCockpitTreasuryAccess();
        $this->registerFundingDestinationResolver();
        $this->registerMinimumWithdrawalPolicyResolver();
        $this->registerIntegrationContracts();
        $this->registerReportDriverSource();

        $this->app->bind(RedemptionFlowPreparationContract::class, function ($app) {
            $service = config('x-change.services.redemption_flow_preparation', DefaultRedemptionFlowPreparationService::class);

            return $app->make($service);
        });

        $this->app->bind(RedemptionCompletionStoreContract::class, function ($app) {
            $service = config('x-change.services.redemption_completion_store', NullRedemptionCompletionStore::class);

            return $app->make($service);
        });

        $this->app->bind(RedemptionCompletionContextContract::class, function ($app) {
            $service = config('x-change.services.redemption_completion_context', DefaultRedemptionCompletionContextService::class);

            return $app->make($service);
        });

        $this->app->bind(RedemptionContextResolverContract::class, function ($app) {
            $service = config('x-change.services.redemption_context_resolver', DefaultRedemptionContextResolverService::class);

            return $app->make($service);
        });

        $this->app->bind(RedemptionValidationContract::class, function ($app) {
            $service = config('x-change.services.redemption_validation', DefaultRedemptionValidationService::class);

            return $app->make($service);
        });

        $this->app->bind(RedemptionProcessorContract::class, function ($app) {
            $service = config('x-change.services.redemption_processor', DefaultRedemptionProcessorService::class);

            return $app->make($service);
        });

        $this->app->bind(RedemptionExecutionContract::class, function ($app) {
            $service = config('x-change.services.redemption_execution', DefaultRedemptionExecutionService::class);

            return $app->make($service);
        });

        $this->app->bind(ClaimExecutionFactoryContract::class, function ($app) {
            $service = config('x-change.services.claim_execution_factory', DefaultClaimExecutionFactory::class);

            return $app->make($service);
        });

        $this->app->singleton(ClaimWorkflowResolverContract::class, DefaultClaimWorkflowResolver::class);

        $this->app->singleton(OptionalCockpitIntegrationReadModels::class);
        $this->app->singleton(VoucherLiabilitySummaryContract::class, VoucherLiabilitySummaryService::class);
        $this->app->singleton(
            MoneyMovementAccountingDecisionContract::class,
            DefaultMoneyMovementAccountingDecisionService::class,
        );
        $this->app->singleton(
            MoneyMovementTargetModelContract::class,
            DefaultMoneyMovementTargetModelService::class,
        );
        $this->app->singleton(
            MoneyMovementLifecycleTriggerMatrixContract::class,
            DefaultMoneyMovementLifecycleTriggerMatrixService::class,
        );
        $this->app->singleton(CockpitReadModelProviderContract::class, function ($app): VoucherLifecycleCockpitReadModelProvider {
            return new VoucherLifecycleCockpitReadModelProvider(
                vouchers: $app->make(VoucherLifecycleServiceContract::class),
                fallback: new NullCockpitReadModelProvider,
                integrations: $app->make(OptionalCockpitIntegrationReadModels::class),
                operatorIssuanceActivity: $app->make(DurableCockpitOperatorIssuanceActivityReadModelProvider::class),
                liabilities: $app->make(VoucherLiabilitySummaryContract::class),
                moneyMovementDecision: $app->make(MoneyMovementAccountingDecisionContract::class),
                payCodeDetails: $app->make(CockpitPayCodeDetailProjection::class),
                qrRenderer: $app->make(ClaimUrlQrRendererContract::class),
            );
        });
        $this->app->singleton(
            TreasuryVocabularyReadModelContract::class,
            AdvisoryTreasuryVocabularyReadModel::class,
        );
        $this->app->singleton(CockpitHeaderReadModelProviderContract::class, WalletCockpitHeaderReadModelProvider::class);
        $this->app->singleton(CockpitRedactorContract::class, DefaultCockpitRedactor::class);
        $this->app->bind(CockpitOperatorIssuanceActivityRecorderContract::class, function ($app) {
            $service = $this->cockpitOperatorIssuanceActivityService(
                'recorder',
                'available_recorders',
                NullCockpitOperatorIssuanceActivityRecorder::class,
            );

            return $app->make($service);
        });
        $this->app->bind(CockpitOperatorIssuanceActivityRepositoryContract::class, function ($app) {
            $service = $this->cockpitOperatorIssuanceActivityService(
                'repository',
                'available_repositories',
                NullCockpitOperatorIssuanceActivityRepository::class,
            );

            return $app->make($service);
        });
        $this->app->bind(
            CockpitOperatorIssuanceActivityRedactionPolicyContract::class,
            DefaultCockpitOperatorIssuanceActivityRedactionPolicy::class,
        );
        $this->app->bind(
            CockpitOperatorIssuanceActivityRetentionPolicyContract::class,
            DefaultCockpitOperatorIssuanceActivityRetentionPolicy::class,
        );
        $this->app->bind(CockpitOperatorIssuanceActivityJournalHandoffContract::class, function ($app) {
            $service = $this->cockpitOperatorIssuanceActivityService(
                'journal_handoff',
                'available_journal_handoffs',
                NullCockpitOperatorIssuanceActivityJournalHandoff::class,
            );

            return $app->make($service);
        });
        $this->app->bind(CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract::class, function ($app) {
            $service = $this->cockpitOperatorIssuanceActivityService(
                'journal_handoff_status_projector',
                'available_journal_handoff_status_projectors',
                NullCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class,
            );

            return $app->make($service);
        });
        $this->app->bind(CockpitOperatorIssuanceActivityActionHandoffContract::class, function ($app) {
            $service = $this->cockpitOperatorIssuanceActivityService(
                'action_handoff',
                'available_action_handoffs',
                NullCockpitOperatorIssuanceActivityActionHandoff::class,
            );

            return $app->make($service);
        });
        $this->app->bind(CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract::class, function ($app) {
            $service = $this->cockpitOperatorIssuanceActivityService(
                'action_handoff_status_projector',
                'available_action_handoff_status_projectors',
                NullCockpitOperatorIssuanceActivityActionHandoffStatusProjector::class,
            );

            return $app->make($service);
        });
        $this->app->bind(CockpitOperatorIssuanceActivityFeedbackHandoffContract::class, function ($app) {
            $service = $this->cockpitOperatorIssuanceActivityService(
                'feedback_handoff',
                'available_feedback_handoffs',
                NullCockpitOperatorIssuanceActivityFeedbackHandoff::class,
            );

            return $app->make($service);
        });
        $this->app->bind(CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectorContract::class, function ($app) {
            $service = $this->cockpitOperatorIssuanceActivityService(
                'feedback_handoff_status_projector',
                'available_feedback_handoff_status_projectors',
                NullCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector::class,
            );

            return $app->make($service);
        });
        $this->app->bind(
            CockpitOperatorIssuanceActivityPresenterContract::class,
            DefaultCockpitOperatorIssuanceActivityPresenter::class,
        );
        $this->app->bind(
            CockpitIssuanceDraftCompilerContract::class,
            DefaultCockpitIssuanceDraftCompiler::class,
        );
        $this->app->bind(
            CockpitQuickGenerateDraftFactoryContract::class,
            DefaultCockpitQuickGenerateDraftFactory::class,
        );
        $this->app->singleton(
            CockpitIssuanceTemplateRegistryContract::class,
            DefaultCockpitIssuanceTemplateRegistry::class,
        );
        $this->app->bind(
            CockpitIssuanceDraftValidatorContract::class,
            DefaultCockpitIssuanceDraftValidator::class,
        );
        $this->app->bind(
            CockpitCampaignIssuanceDraftAdapterContract::class,
            DefaultCockpitCampaignIssuanceDraftAdapter::class,
        );
        $this->app->bind(
            CockpitIssuanceDraftAuditMetadataBuilderContract::class,
            DefaultCockpitIssuanceDraftAuditMetadataBuilder::class,
        );

        $this->app->bind(WithdrawalValidationContract::class, function ($app) {
            $service = config('x-change.services.withdrawal_validation', DefaultWithdrawalValidationService::class);

            return $app->make($service);
        });

        $this->app->bind(WithdrawalProcessorContract::class, function ($app) {
            $service = config('x-change.services.withdrawal_processor', DefaultWithdrawalProcessorService::class);

            return $app->make($service);
        });

        $this->app->bind(WithdrawalExecutionContract::class, function ($app) {
            $service = config('x-change.services.withdrawal_execution', DefaultWithdrawalExecutionService::class);

            return $app->make($service);
        });

        $this->app->bind(DisbursementReconciliationStoreContract::class, function ($app) {
            $service = config(
                'x-change.services.disbursement_reconciliation_store',
                DefaultDisbursementReconciliationStore::class
            );

            return $app->make($service);
        });

        $this->app->bind(DisbursementStatusResolverContract::class, function ($app) {
            $service = config(
                'x-change.services.disbursement_status_resolver',
                DefaultDisbursementStatusResolverService::class
            );

            return $app->make($service);
        });

        $this->app->bind(
            ProviderPayoutDestinationValidationContract::class,
            UnavailableProviderPayoutDestinationValidation::class,
        );
        $this->app->bind(
            PayoutDestinationValidatorContract::class,
            LayeredPayoutDestinationValidator::class,
        );

        $this->app->bind(DisbursementStatusFetcherContract::class, function ($app) {
            $service = config(
                'x-change.services.disbursement_status_fetcher',
                DefaultDisbursementStatusFetcherService::class,
            );

            return $app->make($service);
        });

        $this->app->bind(DisbursementReconciliationContract::class, function ($app) {
            $service = config(
                'x-change.services.disbursement_reconciliation',
                DefaultDisbursementReconciliationService::class,
            );

            return $app->make($service);
        });

        $this->app->bind(PricingServiceContract::class, InstructionBackedPricingService::class);

        $this->app->bind(WalletProxy::class, function ($app) {
            $service = config(
                'x-change.payout.wallet_proxy',
                SystemWalletProxy::class
            );

            return $app->make($service);
        });

        $this->app->bind(PricelistServiceContract::class, PricelistService::class);

        $this->app->bind(VoucherAccessContract::class, VoucherAccessService::class);
        $this->app->singleton(
            VoucherOperationalStatusResolverContract::class,
            DefaultVoucherOperationalStatusResolver::class,
        );
        $this->app->bind(VoucherLifecycleServiceContract::class, VoucherLifecycleService::class);
        $this->app->bind(ClaimViewerResolverContract::class, ClaimViewerResolver::class);
        $this->app->bind(ClaimSurfaceResolverContract::class, DefaultClaimSurfaceResolver::class);

        $this->app->bind(ReconciliationLifecycleServiceContract::class, ReconciliationLifecycleService::class);

        $this->app->bind(EventLifecycleServiceContract::class, EventLifecycleService::class);

        $this->app->singleton(EventStoreContract::class, CacheEventStore::class);

        $this->app->bind(
            WithdrawalLifecycleServiceContract::class,
            (string) config('x-change.lifecycle.withdrawals.service', WithdrawalLifecycleService::class),
        );

        $this->app->bind(UserLifecycleServiceContract::class, UserLifecycleService::class);

        $this->app->singleton(PayoutProvider::class, function ($app) {
            $provider = config(
                'x-change.payout.provider',
                NetbankPayoutProvider::class
            );

            return $app->make($app->make(PayoutProviderResolver::class)->resolve($provider));
        });

        $this->app->bind(
            WithdrawalIntervalEnforcerContract::class,
            XChangeWithdrawalIntervalEnforcer::class,
        );

        $this->app->bind(WithdrawalPipeline::class, function ($app) {
            $steps = config('x-change.withdrawal.pipeline.steps', []);

            foreach ($steps as $step) {
                if (! is_string($step) || ! class_exists($step)) {
                    throw new InvalidArgumentException("Invalid withdrawal pipeline step: {$step}");
                }
            }

            return new WithdrawalPipeline(
                pipeline: $app->make(Pipeline::class),
                steps: $steps,
            );
        });

        $this->app->bind(
            WithdrawalIntervalEnforcerContract::class,
            XChangeWithdrawalIntervalEnforcer::class,
        );

        $this->app->bind(WithdrawalOtpApprovalServiceContract::class, function ($app) {
            return match (config('x-change.withdrawal.otp.driver', 'null')) {
                'paynamics' => $app->make(PaynamicsWithdrawalOtpApprovalService::class),
                'txtcmdr' => $app->make(TxtcmdrWithdrawalOtpApprovalService::class),
                'null' => $app->make(NullWithdrawalOtpApprovalService::class),
                default => throw new InvalidArgumentException(
                    'Unsupported withdrawal OTP driver: '.config('x-change.withdrawal.otp.driver')
                ),
            };
        });

        $this->app->bind(VendorRegistryContract::class, function ($app) {
            return match (config('x-change.vendors.registry', 'config')) {
                'config' => $app->make(ConfigVendorRegistry::class),

                default => throw new InvalidArgumentException(
                    'Unsupported vendor registry: '.config('x-change.vendors.registry')
                ),
            };
        });

        $this->app->bind(ApprovalWorkflowContract::class, function ($app) {
            $handlers = [];

            foreach (config('x-change.approval_workflow.handlers', []) as $requirement => $handlerClass) {
                $handler = $app->make($handlerClass);
                $handlers[$requirement] = $handler;
            }

            return new DefaultApprovalWorkflowService($handlers);
        });

        $this->app->bind(
            VoucherFlowCapabilityResolverContract::class,
            DefaultVoucherFlowCapabilityResolver::class,
        );

        $this->app->bind(
            SettlementFlowPreparationContract::class,
            DefaultSettlementFlowPreparationService::class,
        );

        $this->app->bind(
            SettlementEnvelopeReadinessContract::class,
            NullSettlementEnvelopeReadinessService::class,
        );

        $this->app->bind(
            SettlementExecutionContract::class,
            DefaultSettlementExecutionService::class,
        );

        $this->app->bind(CampaignBankTransferDispatcherContract::class, function ($app) {
            return config('x-change.campaigns.netbank_dispatch.enabled', false)
                ? $app->make(NetbankCampaignBankTransferDispatcher::class)
                : $app->make(NullCampaignBankTransferDispatcher::class);
        });

        $this->app->bind(CampaignBankTransferStatusCheckerContract::class, function ($app) {
            return config('x-change.campaigns.netbank_dispatch.enabled', false)
                ? $app->make(NetbankCampaignBankTransferStatusChecker::class)
                : $app->make(NullCampaignBankTransferStatusChecker::class);
        });

        $this->app->bind(
            ClaimApprovalWorkflowStoreContract::class,
            CacheClaimApprovalWorkflowStore::class,
        );

        $this->app->bind(
            ClaimApprovalExecutionContract::class,
            DefaultClaimApprovalExecutionService::class,
        );

        $this->app->bind(
            ClaimApprovalInitiationContract::class,
            DefaultClaimApprovalInitiationService::class,
        );

        $this->app->bind(
            ClaimApprovalNotificationContract::class,
            NullClaimApprovalNotificationService::class,
        );

        $this->app->bind(ClaimOtpChallengeContract::class, function ($app) {
            $driver = (string) config('x-change.claim_approval.otp.driver', 'null');

            $service = config("x-change.claim_approval.otp.drivers.{$driver}.challenge");

            if (! is_string($service) || ! class_exists($service)) {
                throw new InvalidArgumentException("Unsupported claim approval OTP challenge driver [{$driver}].");
            }

            return $app->make($service);
        });

        $this->app->bind(ClaimOtpVerificationContract::class, function ($app) {
            $driver = (string) config('x-change.claim_approval.otp.driver', 'null');

            $service = config("x-change.claim_approval.otp.drivers.{$driver}.verify");

            if (! is_string($service) || ! class_exists($service)) {
                throw new InvalidArgumentException("Unsupported claim approval OTP verify driver [{$driver}].");
            }

            return $app->make($service);
        });

        $this->app->bind(
            PayCodePresentationResolverContract::class,
            DefaultPayCodePresentationResolver::class,
        );

        $this->app->bind(
            VoucherPaymentQrGeneratorContract::class,
            DefaultVoucherPaymentQrGenerator::class,
        );

        $this->app->bind(
            VoucherPaymentQrRendererContract::class,
            fn ($app) => $app->make(VoucherPaymentQrRendererFactory::class)->make(),
        );

        $this->app->singleton(
            ClaimUrlQrRendererContract::class,
            Base64PngClaimUrlQrRenderer::class,
        );

        $this->app->bind(
            SettlementReadinessGateContract::class,
            DefaultSettlementReadinessGate::class,
        );

        $this->app->bind(
            VoucherPaymentConfirmationContract::class,
            DefaultVoucherPaymentConfirmationService::class,
        );

        $this->app->bind(
            VoucherCollectionWalletResolverContract::class,
            DefaultVoucherCollectionWalletResolver::class,
        );

        $this->app->singleton(AccountFundingCollectionPosting::class);
        $this->app->tag(
            AccountFundingCollectionPosting::class,
            'x-change.voucher-collection-postings',
        );
        $this->app->singleton(ProviderFundingCollectionPosting::class);
        $this->app->tag(
            ProviderFundingCollectionPosting::class,
            'x-change.voucher-collection-postings',
        );
        $this->app->singleton(ProviderWalletCollectionPosting::class);
        $this->app->tag(
            ProviderWalletCollectionPosting::class,
            'x-change.voucher-collection-postings',
        );
        $this->app->singleton(
            VoucherCollectionPostingRegistry::class,
            fn ($app): VoucherCollectionPostingRegistry => new VoucherCollectionPostingRegistry(
                $app->tagged('x-change.voucher-collection-postings'),
            ),
        );

        $this->app->bind(
            VoucherPaymentProviderContract::class,
            ManualVoucherPaymentProvider::class,
        );

        $this->app->bind(
            SettlementEnvelopeReadinessContract::class,
            SettlementEnvelopeReadinessService::class,
        );

        $this->app->bind(
            SettlementEnvelopeExecutionGateway::class,
            XChangeSettlementEnvelopeExecutionGateway::class,
        );

        $this->app->singleton(
            StoredValueExecutionGateway::class,
            XChangeStoredValueExecutionGateway::class,
        );

        $this->app->bind(
            ExecutionCashDisbursementPollerContract::class,
            LifecycleExecutionCashDisbursementPoller::class,
        );

        $this->app->bind(
            ExecutionResultHandoffPipelineContract::class,
            ExecutionResultHandoffPipeline::class,
        );

        $this->app->bind(
            FeedbackDeliveryJournalWriterContract::class,
            XJournalFeedbackDeliveryWriter::class,
        );

        $this->app->bind(ExecutionResultHandoffSummaryJournalWriterContract::class, function ($app) {
            $service = $this->executionResultHandoffService(
                'summary_journal_writer',
                'available_summary_journal_writers',
                NullExecutionResultHandoffSummaryJournalWriter::class,
            );

            return $app->make($service);
        });

        $this->app->bind(ExecutionResultJournalHandoffContract::class, function ($app) {
            $service = $this->executionResultHandoffService(
                'journal',
                'available_journal_handoffs',
                NullExecutionResultJournalHandoff::class,
            );

            return $app->make($service);
        });

        $this->app->bind(ExecutionResultActionHandoffContract::class, function ($app) {
            $service = $this->executionResultHandoffService(
                'action',
                'available_action_handoffs',
                NullExecutionResultActionHandoff::class,
            );

            return $app->make($service);
        });

        $this->app->bind(ExecutionResultFeedbackHandoffContract::class, function ($app) {
            $service = $this->executionResultHandoffService(
                'feedback',
                'available_feedback_handoffs',
                NullExecutionResultFeedbackHandoff::class,
            );

            return $app->make($service);
        });

        $this->app->bind(
            ExecutionResultCockpitActivityHandoffContract::class,
            NullExecutionResultCockpitActivityHandoff::class,
        );

        $this->app
            ->make(ExecutionDriverRegistry::class)
            ->register('x_change_live_cash', XChangeLiveCashExecutionDriver::class);
        $this->app
            ->make(ExecutionDriverRegistry::class)
            ->register(
                OnboardingVoucherInstructionPolicy::ExecutionDriver,
                OnboardingAccountProvisioningExecutionDriver::class,
            );

        $this->app->singleton(
            AccountProvisioningContract::class,
            DefaultAccountProvisioningService::class,
        );
        $this->app->singleton(
            ContactUserProvisionerContract::class,
            XChangeContactUserProvisioner::class,
        );

        $this->app->bind(
            SettlementReadinessGateContract::class,
            DefaultSettlementReadinessGate::class,
        );

        $this->app->singleton(SettlementCollectionGate::class);

        $this->app->bind(PendingOtpStore::class, ClaimApprovalPendingOtpStore::class);

        $this->app->singleton(
            ClaimApprovalStatusResolver::class,
            DefaultClaimApprovalStatusResolver::class,
        );
    }

    private function configureFormFlowSplashDefaults(): void
    {
        config([
            'splash.app_logo' => config(
                'splash.app_logo',
                (string) config(
                    'x-change.branding.logo_light',
                    '/vendor/x-change/images/brand-library/x-change/svg/x-change-logo.svg',
                ),
            ),
        ]);
    }

    private function configureIdentityOtpGateway(): void
    {
        config([
            'otp-handler.driver' => config(
                'x-change.onboarding.identity_otp.driver',
                'unavailable',
            ),
            'otp-handler.txtcmdr.base_url' => config(
                'x-change.onboarding.identity_otp.base_url',
                'https://txtcmdr.test',
            ),
            'otp-handler.txtcmdr.api_token' => config(
                'x-change.onboarding.identity_otp.token',
            ),
            'otp-handler.txtcmdr.connect_timeout' => config(
                'x-change.onboarding.identity_otp.connect_timeout',
                5,
            ),
            'otp-handler.txtcmdr.timeout' => config(
                'x-change.onboarding.identity_otp.timeout',
                15,
            ),
            'otp-handler.txtcmdr.verify_ssl' => config(
                'x-change.onboarding.identity_otp.verify_ssl',
                true,
            ),
        ]);
    }

    public function boot(): void
    {
        Inertia::share(
            'xchange.version',
            InstalledVersions::getPrettyVersion('3neti/x-change') ?? 'dev',
        );

        $this->app->make(HttpKernel::class)->prependMiddleware(
            EnsureXChangeIsCommissioned::class,
        );
        $this->bootInitialPinSetupGuard();
        $this->decorateOnboardingCompletionHook();
        $this->prependExecutionAwarePostRedemptionGate();
        $this->bootConfig();
        $this->bootPartnerApiOAuth();
        $this->loadViewsFrom($this->packagePath('resources/views'), 'x-change');
        $this->bootFundingVerificationRateLimiter();
        $this->bootPaymentVerificationRateLimiter();
        $this->bootCommercialSettlementRateLimiter();
        $this->bootPartnerApiRateLimiter();
        $this->bootFundingVerificationSchedule();
        $this->bootFundingBroadcastChannel();
        $this->bootRoutes();
        $this->bootMobileFirstFortify();
        $this->bootExceptionRendering();
        $this->bootMigrations();

        if ($this->app->runningInConsole()) {
            $this->commands([
                OnboardIssuerCommand::class,
                OpenIssuerWalletCommand::class,
                VerifyTestMobileCommand::class,
                GetWalletBalanceCommand::class,
                EstimatePayCodeCostCommand::class,
                GeneratePayCodeCommand::class,
                ClaimWalkthroughCommand::class,
                PreparePayCodeRedemptionFlowCommand::class,
                LoadPayCodeRedemptionCompletionContextCommand::class,
                SubmitPayCodeClaimCommand::class,
                SnapshotRiderSplashArtworkCommand::class,
                CheckDisbursementStatusCommand::class,
                RetryFailedPayCodeDisbursementCommand::class,
                ResumeMissingTreasuryPayCodeDisbursementCommand::class,
                RestoreUnsubmittedPayoutCorrectionCommand::class,
                AttestAccountFundingPayCodeJournalIntegrityCommand::class,
                ApproveFundingRequestCommand::class,
                BackfillAccountFundingPayCodeJournalCommand::class,
                IssueSystemAccountFundingPayCodeCommand::class,
                VerifyFundingRequestBackingCommand::class,
                VerifyOpenFundingIntentsCommand::class,
                VerifyOpenPaymentAttemptsCommand::class,
                SyncStandingFundingAddressesCommand::class,
                ReconcilePendingDisbursementsCommand::class,
                TestFeedbackEmailCommand::class,
                TestFeedbackSmsCommand::class,
                ShowFeedbackHistoryCommand::class,
                CreatePartnerApiClientCommand::class,
                AuthorizePartnerApiOperatorCommand::class,
                RunPartnerApiLifecycleCommand::class,
                ProvisionCommissioningSeatsCommand::class,
                AuthorizeProvisioningOperatorCommand::class,
                ExpireProvisioningOffersCommand::class,

                PrepareLifecycleEnvironmentCommand::class,
                RunLifecycleScenarioCommand::class,
                CollectRevenueCommand::class,
                ShowPendingRevenueCommand::class,

                EvaluateSettlementEnvelopeCommand::class,
                RunLifecycleScenarioGroupCommand::class,
                BackfillDisbursementSettlementJournalCommand::class,
                AttestCommercialAccountingCommand::class,
                AuthorizeTreasuryOperatorCommand::class,
                AuthorizeCommercialOperatorCommand::class,
                ApprovePartnerCommissionPayoutBatchCommand::class,
                CommercialGovernanceStatusCommand::class,
                ProvisionCommercialBaselinesCommand::class,
                ReconcilePartnerCommissionPayoutBatchCommand::class,
                ReconcilePendingPartnerCommissionPayoutsCommand::class,
                RecordProviderCostBatchCommand::class,
                RequestPartnerCommissionPayoutBatchCommand::class,
                SubmitPartnerCommissionPayoutBatchCommand::class,
                BackfillCommercialAccountingJournalCommand::class,
                BackfillStandingFundingPositionsCommand::class,
                CapitalizeTreasuryOpeningBalanceCommand::class,
                CorrectLegacyPayCodeFeePostingCommand::class,
                MigrateLegacyAccountBalanceCommand::class,
                PreflightTreasuryCommand::class,
                RedactTreasurySensitiveMetadataCommand::class,
                RefreshTreasuryLiquidityCommand::class,
                ReleaseExpiredPayCodeReservesCommand::class,
                ProvisionSystemPrincipalCommand::class,
                ProvisionTreasuryCommand::class,
                ReconcileOpeningTreasuryBalanceCommand::class,
                RepairMissingDisbursementPostingsCommand::class,
                SimulateTreasuryProviderDepositCommand::class,
                InstallXChangeCommand::class,
                PublishXChangeCommand::class,
                AdoptHostCommand::class,
                AdoptXChangeCommand::class,
                SetupXChangeCommand::class,
                ConfigureXChangeCommand::class,
                CommissionXChangeCommand::class,
                CloudRecipeCommand::class,
                DeployXChangeCommand::class,
                GenerateDeploymentManifestCommand::class,
                ValidateDeploymentManifestCommand::class,
                InspectXChangeConfigurationCommand::class,
                DoctorXChangeCommand::class,
                CommissioningStatusCommand::class,
                AdoptCommissioningManifestCommand::class,
                SeedCockpitDiagnosticActivityCommand::class,
                ShowCockpitOperatorActivityRuntimeProfileCommand::class,
                ValidateLinkPreviewDriversCommand::class,
            ]);
        }

        Event::listen(
            VoucherDisbursementSucceeded::class,
            RecordSuccessfulVoucherDisbursement::class
        );

        Event::listen(
            VoucherDisbursementFailed::class,
            RecordFailedVoucherDisbursement::class
        );

        Event::listen(
            DisbursementConfirmed::class,
            HandleConfirmedDisbursement::class
        );

        Event::listen(
            DisbursementRejected::class,
            HandleRejectedDisbursement::class
        );
    }

    protected function bootInitialPinSetupGuard(): void
    {
        if (! $this->mobileFirstAuthEnabled()) {
            return;
        }

        $this->app['router']->pushMiddlewareToGroup(
            'web',
            RequireInitialPinSetup::class,
        );
    }

    protected function prependExecutionAwarePostRedemptionGate(): void
    {
        $pipeline = (array) config('voucher-pipeline.post-redemption', []);

        if (in_array(ExecutionAwarePostRedemptionGate::class, $pipeline, true)) {
            return;
        }

        array_unshift($pipeline, ExecutionAwarePostRedemptionGate::class);

        config()->set('voucher-pipeline.post-redemption', $pipeline);
    }

    protected function bootFundingVerificationRateLimiter(): void
    {
        RateLimiter::for('x-change-funding-verification', function (object $job): Limit {
            return Limit::perMinute(max(
                1,
                (int) config('x-change.funding.verification_provider_rate_limit_per_minute', 30),
            ))->by((string) data_get($job, 'providerCode', 'unknown'));
        });
    }

    protected function bootPaymentVerificationRateLimiter(): void
    {
        RateLimiter::for('x-change-payment-verification', function (object $job): Limit {
            return Limit::perMinute(max(
                1,
                (int) config('x-change.payment.attempts.verification_provider_rate_limit_per_minute', 30),
            ))->by((string) data_get($job, 'providerCode', 'unknown'));
        });
    }

    protected function bootCommercialSettlementRateLimiter(): void
    {
        RateLimiter::for('x-change-commercial-settlement', function (): Limit {
            return Limit::perMinute(max(
                1,
                (int) config(
                    'x-change.commercial.operations.provider_rate_limit_per_minute',
                    20,
                ),
            ));
        });
    }

    protected function bootFundingBroadcastChannel(): void
    {
        Broadcast::channel(
            'x-change.funding.{token}',
            fn (Model $user, string $token): bool => app(
                FundingProjectionChannel::class,
            )->authorizes($user, $token),
        );
    }

    protected function bootFundingVerificationSchedule(): void
    {
        if ((bool) config('x-change.provisioning.expiry.scheduled_enabled', true)) {
            $batchSize = max(
                1,
                (int) config('x-change.provisioning.expiry.scheduled_batch_size', 100),
            );

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) use ($batchSize): void {
                $schedule
                    ->command("x-change:provisioning:expire-offers --limit={$batchSize}")
                    ->name('x-change:provisioning:expire-offers')
                    ->everyMinute()
                    ->onOneServer()
                    ->withoutOverlapping(5);
            });
        }

        if ((bool) config(
            'x-change.commercial.operations.scheduled_reconciliation_enabled',
            true,
        )) {
            $batchSize = max(
                1,
                (int) config('x-change.commercial.operations.scheduled_batch_size', 50),
            );

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) use ($batchSize): void {
                $schedule
                    ->command("x-change:commercial:commission:reconcile-pending --limit={$batchSize}")
                    ->name('x-change:commercial:commission:reconcile-pending')
                    ->everyMinute()
                    ->onOneServer()
                    ->withoutOverlapping(5);
            });
        }

        if ((bool) config('x-change.treasury.expiry_release.scheduled_enabled', true)) {
            $batchSize = max(
                1,
                (int) config('x-change.treasury.expiry_release.scheduled_batch_size', 100),
            );

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) use ($batchSize): void {
                $schedule
                    ->command("xchange:pay-codes:release-expired --limit={$batchSize}")
                    ->name('xchange:pay-codes:release-expired')
                    ->everyMinute()
                    ->onOneServer()
                    ->withoutOverlapping(5);
            });
        }

        if ((bool) config('x-change.funding.liquidity_refresh.scheduled_enabled', true)) {
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule
                    ->command('xchange:treasury:refresh-liquidity')
                    ->name('xchange:treasury:refresh-liquidity')
                    ->everyFiveMinutes()
                    ->onOneServer()
                    ->withoutOverlapping(5);
            });
        }

        if ((bool) config('x-change.payment.attempts.scheduled_verification_enabled', true)) {
            $batchSize = max(
                1,
                (int) config('x-change.payment.attempts.scheduled_batch_size', 100),
            );

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) use ($batchSize): void {
                $schedule
                    ->command("xchange:payments:verify-open --provider=netbank --limit={$batchSize}")
                    ->name('xchange:payments:verify-open:netbank')
                    ->everyMinute()
                    ->onOneServer()
                    ->withoutOverlapping(5);
            });
        }

        if ((bool) config('x-change.disbursement.reconciliation.scheduled_enabled', true)) {
            $batchSize = max(
                1,
                (int) config('x-change.disbursement.reconciliation.scheduled_batch_size', 50),
            );

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) use ($batchSize): void {
                $schedule
                    ->command("xchange:reconcile:pending --limit={$batchSize}")
                    ->name('xchange:reconcile:pending')
                    ->everyMinute()
                    ->onOneServer()
                    ->withoutOverlapping(5);
            });
        }

        if ((bool) config('x-change.funding.scheduled_verification_enabled', true)) {
            $batchSize = max(
                1,
                (int) config('x-change.funding.scheduled_verification_batch_size', 100),
            );

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) use ($batchSize): void {
                $schedule
                    ->command("xchange:funding:verify-open --provider=netbank --limit={$batchSize}")
                    ->name('xchange:funding:verify-open:netbank')
                    ->everyMinute()
                    ->onOneServer()
                    ->withoutOverlapping(5);
            });
        }

        if ((bool) config('x-change.funding.standing_addresses.enabled', false)
            && (bool) config('x-change.funding.standing_addresses.scheduled_sync_enabled', true)) {
            $batchSize = max(
                1,
                (int) config('x-change.funding.standing_addresses.scheduled_batch_size', 100),
            );

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) use ($batchSize): void {
                $schedule
                    ->command("xchange:funding:sync-standing --provider=netbank --limit={$batchSize}")
                    ->name('xchange:funding:sync-standing:netbank')
                    ->everyMinute()
                    ->onOneServer()
                    ->withoutOverlapping(5);
            });
        }
    }

    protected function decorateOnboardingCompletionHook(): void
    {
        $contract = 'LBHurtado\\Onboarding\\Contracts\\OnboardingServiceContract';

        if (! interface_exists($contract) || ! $this->app->bound($contract)) {
            return;
        }

        $this->app->extend($contract, function (mixed $service, $app) {
            if ($service instanceof ProvisioningAwareOnboardingService) {
                return $service;
            }

            return new ProvisioningAwareOnboardingService(
                onboarding: $service,
                completionHook: $app->make(StartProviderProvisioningFromOnboardingCompletion::class),
            );
        });
    }

    protected function registerServices(): void
    {
        $services = (array) config('x-change.services', []);

        foreach ($services as $key => $concrete) {
            if (! is_string($key) || ! is_string($concrete) || $concrete === '') {
                continue;
            }

            $this->app->singleton("x-change.services.{$key}", function ($app) use ($concrete) {
                return $app->make($concrete);
            });
        }
    }

    protected function registerIntegrations(): void
    {
        $integrations = (array) config('x-change.integrations', []);

        foreach ($integrations as $key => $concrete) {
            if (! is_string($key) || ! is_string($concrete) || $concrete === '') {
                continue;
            }

            $this->app->singleton("x-change.integrations.{$key}", function ($app) use ($concrete) {
                return $app->make($concrete);
            });
        }
    }

    protected function registerServiceContracts(): void
    {
        $contracts = (array) config('x-change.service_contracts', []);

        foreach ($contracts as $contract => $serviceKey) {
            if (! is_string($contract) || ! is_string($serviceKey) || $serviceKey === '') {
                continue;
            }

            $this->app->singleton($contract, function ($app) use ($serviceKey) {
                return $app->make("x-change.services.{$serviceKey}");
            });
        }

        if (! $this->app->bound(RiderStampCopyResolverContract::class)) {
            $this->app->singleton(
                RiderStampCopyResolverContract::class,
                fn ($app) => $app->make(config(
                    'x-change.services.rider_stamp_copy',
                    DefaultRiderStampCopyResolver::class,
                )),
            );
        }

        if (! $this->app->bound(RiderStampRecipientResolverContract::class)) {
            $this->app->singleton(
                RiderStampRecipientResolverContract::class,
                fn ($app) => $app->make(config(
                    'x-change.services.rider_stamp_recipient',
                    DefaultRiderStampRecipientResolver::class,
                )),
            );
        }

        if (! $this->app->bound(RiderSplashArtworkSnapshotterContract::class)) {
            $this->app->singleton(
                RiderSplashArtworkSnapshotterContract::class,
                fn ($app) => $app->make(config(
                    'x-change.services.rider_splash_artwork_snapshotter',
                    DefaultRiderSplashArtworkSnapshotter::class,
                )),
            );
        }

        if (! $this->app->bound(RiderStampClaimCardComposerContract::class)) {
            $this->app->singleton(
                RiderStampClaimCardComposerContract::class,
                fn ($app) => $app->make(config(
                    'x-change.services.rider_stamp_claim_card_composer',
                    GdRiderStampClaimShareCardRenderer::class,
                )),
            );
        }

        if (! $this->app->bound(RiderStampArtifactStoreContract::class)) {
            $this->app->singleton(
                RiderStampArtifactStoreContract::class,
                fn ($app) => $app->make(config(
                    'x-change.services.rider_stamp_artifact_store',
                    DefaultRiderStampArtifactStore::class,
                )),
            );
        }

        if (! $this->app->bound(ClaimShareMetadataResolverContract::class)) {
            $this->app->singleton(
                ClaimShareMetadataResolverContract::class,
                fn ($app) => $app->make(config(
                    'x-change.services.claim_share_metadata',
                    RiderStampClaimShareMetadataResolver::class,
                )),
            );
        }

        if (! $this->app->bound(ClaimShareCardUrlResolverContract::class)) {
            $this->app->singleton(
                ClaimShareCardUrlResolverContract::class,
                fn ($app) => $app->make(config(
                    'x-change.services.claim_share_card_url_resolver',
                    DefaultClaimShareCardUrlResolver::class,
                )),
            );
        }

        if (! $this->app->bound(ClaimShareCardRendererContract::class)) {
            $this->app->singleton(
                ClaimShareCardRendererContract::class,
                fn ($app) => $app->make(config(
                    'x-change.services.claim_share_card_renderer',
                    StoredRiderStampClaimShareCardRenderer::class,
                )),
            );
        }

        if (! $this->app->bound(XChangeOnboardingGatewayContract::class)) {
            $this->app->singleton(XChangeOnboardingGatewayContract::class, function ($app) {
                return $app->make(config('x-change.services.onboarding_gateway', DefaultXChangeOnboardingGateway::class));
            });
        }

        if (! $this->app->bound(XChangeProviderTopologyResolverContract::class)) {
            $this->app->singleton(XChangeProviderTopologyResolverContract::class, function ($app) {
                return $app->make(config('x-change.services.provider_topology_resolver', ConfigProviderTopologyResolver::class));
            });
        }

        if (! $this->app->bound(ProviderFundingPolicyContract::class)) {
            $this->app->singleton(ProviderFundingPolicyContract::class, function ($app) {
                return $app->make(config('x-change.services.provider_funding_policy', ProviderAwareFundingPolicy::class));
            });
        }

        if (! $this->app->bound(SystemAccountFundingPayCodeAuthorizationContract::class)) {
            $this->app->singleton(
                SystemAccountFundingPayCodeAuthorizationContract::class,
                function ($app) {
                    return $app->make(config(
                        'x-change.services.system_account_funding_pay_code_authorization',
                        ConfigSystemAccountFundingPayCodeAuthorization::class,
                    ));
                },
            );
        }

        if (! $this->app->bound(TreasuryOpeningCapitalizationAuthorizationContract::class)) {
            $this->app->singleton(
                TreasuryOpeningCapitalizationAuthorizationContract::class,
                function ($app) {
                    return $app->make(config(
                        'x-change.treasury.opening_capitalization.authorization_service',
                        ConfigTreasuryOpeningCapitalizationAuthorization::class,
                    ));
                },
            );
        }
    }

    protected function registerMinimumWithdrawalPolicyResolver(): void
    {
        if (! $this->app->bound('x-change.services.minimum_withdrawal_policy')) {
            $this->app->singleton('x-change.services.minimum_withdrawal_policy', function ($app) {
                return $app->make(ConfigMinimumWithdrawalPolicyResolver::class);
            });
        }

        if (! $this->app->bound(MinimumWithdrawalPolicyResolverContract::class)) {
            $this->app->singleton(MinimumWithdrawalPolicyResolverContract::class, function ($app) {
                return $app->make('x-change.services.minimum_withdrawal_policy');
            });
        }
    }

    protected function registerCockpitTreasuryAccess(): void
    {
        if ($this->app->bound(CockpitTreasuryAccessContract::class)) {
            return;
        }

        $this->app->singleton(
            CockpitTreasuryAccessContract::class,
            fn ($app) => $app->make(config(
                'x-change.services.cockpit_treasury_access',
                SystemPrincipalCockpitTreasuryAccess::class,
            )),
        );
    }

    protected function registerFundingDestinationResolver(): void
    {
        if (! $this->app->bound('x-change.services.funding_destination_resolver')) {
            $this->app->singleton('x-change.services.funding_destination_resolver', function ($app) {
                return $app->make(DefaultFundingDestinationResolver::class);
            });
        }

        if (! $this->app->bound(FundingDestinationResolverContract::class)) {
            $this->app->singleton(FundingDestinationResolverContract::class, function ($app) {
                return $app->make('x-change.services.funding_destination_resolver');
            });
        }
    }

    protected function registerFundingAccountServices(): void
    {
        if (! $this->app->bound('x-change.services.funding_account_credit')) {
            $this->app->singleton('x-change.services.funding_account_credit', function ($app) {
                return $app->make(BavixFundingAccountCredit::class);
            });
        }

        foreach ([FundingAccountCreditContract::class, FundingAccountRecoveryContract::class] as $contract) {
            if (! $this->app->bound($contract)) {
                $this->app->singleton($contract, function ($app) {
                    return $app->make('x-change.services.funding_account_credit');
                });
            }
        }
    }

    protected function registerIntegrationContracts(): void
    {
        $contracts = (array) config('x-change.integration_contracts', []);

        foreach ($contracts as $contract => $integrationKey) {
            if (! is_string($contract) || ! is_string($integrationKey) || $integrationKey === '') {
                continue;
            }

            $this->app->singleton($contract, function ($app) use ($integrationKey) {
                return $app->make("x-change.integrations.{$integrationKey}");
            });
        }
    }

    protected function registerReportDriverSource(): void
    {
        if (! interface_exists(ReportResolverInterface::class)) {
            return;
        }

        $sources = $this->app['config']->get('report-registry.driver_sources', []);
        $path = $this->packagePath('resources/report-drivers');

        if (! in_array($path, $sources, true)) {
            $sources[] = $path;
            $this->app['config']->set('report-registry.driver_sources', $sources);
        }
    }

    protected function alignSettlementEnvelopeDefaults(): void
    {
        $publishedDrivers = config_path('envelope-drivers');
        $driverRoot = is_dir($publishedDrivers)
            ? $publishedDrivers
            : $this->packagePath('config/envelope-drivers');

        if (config('filesystems.disks.envelope-drivers') === null) {
            config()->set('filesystems.disks.envelope-drivers', [
                'driver' => 'local',
                'root' => $driverRoot,
                'throw' => true,
            ]);
        }

        config()->set(
            'settlement-envelope.driver_disk',
            'envelope-drivers',
        );
        config()->set(
            'settlement-envelope.storage_disk',
            (string) config(
                'x-change.funding.requests.evidence_disk',
                'local',
            ),
        );
    }

    protected function bootConfig(): void
    {
        $this->publishes([
            $this->packagePath('config/x-change.php') => config_path('x-change.php'),
        ], 'x-change-config');

        $this->publishes([
            $this->packagePath('config/form-flow-drivers/voucher-redemption.yaml') => config_path('form-flow-drivers/voucher-redemption.yaml'),
        ], 'x-change-form-flow-drivers');

        $this->publishes([
            $this->packagePath('config/link-preview-drivers') => config_path('link-preview-drivers'),
        ], 'x-change-link-preview-drivers');

        $this->publishes([
            $this->packagePath('config/envelope-drivers/account-funding-review.yaml') => config_path('envelope-drivers/account-funding-review.yaml'),
        ], 'x-change-envelope-drivers');

        $this->publishes([
            $this->packagePath('stubs/scripts/test-netbank-lifecycle.sh.stub') => base_path('scripts/test-netbank-lifecycle.sh'),
            $this->packagePath('stubs/scripts/.xchange-lifecycle.env.example') => base_path('scripts/.xchange-lifecycle.env.example'),
        ], 'x-change-scripts');

        $this->publishes([
            // x-change pages, components, layouts, composables
            $this->packagePath('resources/js/cockpit') => resource_path('js/cockpit'),
            $this->packagePath('resources/js/pages/x-change') => resource_path('js/pages/x-change'),
            $this->packagePath('resources/js/components/x-change') => resource_path('js/components/x-change'),
            $this->packagePath('resources/js/layouts/x-change') => resource_path('js/layouts/x-change'),
            $this->packagePath('resources/js/composables/useXChangeDashboardApi.ts') => resource_path('js/composables/useXChangeDashboardApi.ts'),
            $this->packagePath('resources/js/composables/usePayCodeApi.ts') => resource_path('js/composables/usePayCodeApi.ts'),
            $this->packagePath('resources/js/composables/usePayCodeForm.ts') => resource_path('js/composables/usePayCodeForm.ts'),
            $this->packagePath('resources/js/composables/usePayCodeCostEstimate.ts') => resource_path('js/composables/usePayCodeCostEstimate.ts'),
            $this->packagePath('resources/js/composables/useXChangeRoutes.ts') => resource_path('js/composables/useXChangeRoutes.ts'),
            $this->packagePath('resources/js/composables/useVoucherPreview.ts') => resource_path('js/composables/useVoucherPreview.ts'),
            $this->packagePath('resources/js/composables/useClipboard.ts') => resource_path('js/composables/useClipboard.ts'),
            $this->packagePath('resources/js/types/voucher.d.ts') => resource_path('js/types/voucher.d.ts'),
            $this->packagePath('resources/js/components/InputError.vue') => resource_path('js/components/InputError.vue'),
            $this->packagePath('resources/js/components/x-change-shared-tabs') => resource_path('js/components/ui/tabs'),
            $this->packagePath('resources/js/components/x-change-shared-alert-dialog') => resource_path('js/components/ui/alert-dialog'),
            $this->packagePath('resources/js/components/x-change-shared-textarea') => resource_path('js/components/ui/textarea'),

            // Form-flow shared dependencies
            $this->packagePath('resources/js/components/x-change-shared-financial') => resource_path('js/components/financial'),
            $this->packagePath('resources/js/components/x-change-shared-phone-input') => resource_path('js/components/ui/phone-input'),
            $this->packagePath('resources/js/components/NumberInputWithKeypad.vue') => resource_path('js/components/NumberInputWithKeypad.vue'),
            $this->packagePath('resources/js/components/NumericKeypad.vue') => resource_path('js/components/NumericKeypad.vue'),
            $this->packagePath('resources/js/composables/useFormFlowSummary.ts') => resource_path('js/composables/useFormFlowSummary.ts'),
            $this->packagePath('resources/js/composables/useTheme.ts') => resource_path('js/composables/useTheme.ts'),
            $this->packagePath('resources/js/layouts/PublicLayout.vue') => resource_path('js/layouts/PublicLayout.vue'),
            $this->packagePath('resources/js/config') => resource_path('js/config'),
            $this->packagePath('resources/js/data') => resource_path('js/data'),
            $this->packagePath('resources/documents') => resource_path('documents'),
        ], 'x-change-ui');

        $this->publishes([
            $this->packagePath('stubs/resources/js/components/AppSidebar.vue.stub') => resource_path('js/components/AppSidebar.vue'),
        ], 'x-change-shell');

        $this->publishes([
            $this->packagePath('resources/assets/images') => public_path('vendor/x-change/images'),
            $this->packagePath('resources/assets/favicon.ico') => public_path('vendor/x-change/favicon.ico'),
            $this->packagePath('resources/assets/favicon.png') => public_path('vendor/x-change/favicon.png'),
            $this->packagePath('resources/assets/images/brand-library/g-clef-pulley/svg/g-clef-pulley-favicon.svg') => public_path('vendor/x-change/favicon.svg'),
            $this->packagePath('resources/assets/apple-touch-icon.png') => public_path('vendor/x-change/apple-touch-icon.png'),
        ], 'x-change-assets');

        $this->publishes([
            $this->packagePath('stubs/migrations/2026_06_17_000000_prepare_users_for_mobile_first_xchange.php.stub') => database_path('migrations/2026_06_17_000000_prepare_users_for_mobile_first_xchange.php'),
        ], 'x-change-host-migrations');

        $this->publishes([
            $this->packagePath('stubs/migrations/2026_06_17_000000_prepare_users_for_mobile_first_xchange.php.stub') => database_path('migrations/2026_06_17_000000_prepare_users_for_mobile_first_xchange.php'),
            $this->packagePath('stubs/database/factories/UserFactory.php.stub') => database_path('factories/UserFactory.php'),
            $this->packagePath('stubs/resources/js/components/AppLogoIcon.vue.stub') => resource_path('js/components/AppLogoIcon.vue'),
            $this->packagePath('stubs/resources/js/pages/auth/Login.vue.stub') => resource_path('js/pages/auth/Login.vue'),
            $this->packagePath('stubs/resources/js/pages/auth/Register.vue.stub') => resource_path('js/pages/auth/Register.vue'),
            $this->packagePath('stubs/resources/js/pages/Welcome.vue.stub') => resource_path('js/pages/Welcome.vue'),
        ], 'x-change-auth');

        $this->publishes([
            $this->packagePath('stubs/database/factories/UserFactory.php.stub') => database_path('factories/UserFactory.php'),
            $this->packagePath('stubs/resources/js/components/AppLogoIcon.vue.stub') => resource_path('js/components/AppLogoIcon.vue'),
            $this->packagePath('stubs/resources/js/pages/auth/Login.vue.stub') => resource_path('js/pages/auth/Login.vue'),
            $this->packagePath('stubs/resources/js/pages/auth/Register.vue.stub') => resource_path('js/pages/auth/Register.vue'),
            $this->packagePath('stubs/resources/js/pages/Welcome.vue.stub') => resource_path('js/pages/Welcome.vue'),
        ], 'x-change-auth-scaffold');

        $this->publishes([
            $this->packagePath('stubs/app/Models/User.php.stub') => app_path('Models/User.php'),
        ], 'x-change-auth-user-replacement');

        $this->publishes([
            $this->packagePath('stubs/tests/Feature/Auth/AuthenticationTest.php.stub') => base_path('tests/Feature/Auth/AuthenticationTest.php'),
            $this->packagePath('stubs/tests/Feature/Auth/EmailVerificationTest.php.stub') => base_path('tests/Feature/Auth/EmailVerificationTest.php'),
            $this->packagePath('stubs/tests/Feature/Auth/PasswordResetTest.php.stub') => base_path('tests/Feature/Auth/PasswordResetTest.php'),
            $this->packagePath('stubs/tests/Feature/Auth/RegistrationTest.php.stub') => base_path('tests/Feature/Auth/RegistrationTest.php'),
            $this->packagePath('stubs/tests/Feature/Auth/TwoFactorChallengeTest.php.stub') => base_path('tests/Feature/Auth/TwoFactorChallengeTest.php'),
            $this->packagePath('stubs/tests/Feature/Auth/VerificationNotificationTest.php.stub') => base_path('tests/Feature/Auth/VerificationNotificationTest.php'),
        ], 'x-change-auth-tests');

        $this->publishes([
            $this->packagePath('stubs/app/Concerns/ProfileValidationRules.php.stub') => app_path('Concerns/ProfileValidationRules.php'),
            $this->packagePath('stubs/app/Http/Requests/Settings/ProfileUpdateRequest.php.stub') => app_path('Http/Requests/Settings/ProfileUpdateRequest.php'),
            $this->packagePath('stubs/app/Http/Controllers/Settings/ProfileController.php.stub') => app_path('Http/Controllers/Settings/ProfileController.php'),
            $this->packagePath('stubs/app/Http/Controllers/Settings/SecurityController.php.stub') => app_path('Http/Controllers/Settings/SecurityController.php'),
            $this->packagePath('stubs/routes/settings.php.stub') => base_path('routes/settings.php'),
            $this->packagePath('stubs/resources/js/pages/settings/Profile.vue.stub') => resource_path('js/pages/settings/Profile.vue'),
            $this->packagePath('stubs/resources/js/pages/settings/SecurityConfirm.vue.stub') => resource_path('js/pages/settings/SecurityConfirm.vue'),
        ], 'x-change-settings');

        $this->publishes([
            $this->packagePath('stubs/tests/Feature/Settings/ProfileUpdateTest.php.stub') => base_path('tests/Feature/Settings/ProfileUpdateTest.php'),
            $this->packagePath('stubs/tests/Feature/Settings/SecurityTest.php.stub') => base_path('tests/Feature/Settings/SecurityTest.php'),
        ], 'x-change-settings-tests');
    }

    protected function bootMobileFirstFortify(): void
    {
        if (
            ! $this->mobileFirstAuthEnabled()
            || ! class_exists(Fortify::class)
            || ! interface_exists(CreatesNewUsers::class)
            || ! class_exists(Inertia::class)
        ) {
            return;
        }

        $this->app->booted(function (): void {
            Fortify::authenticateUsing($this->app->make(AuthenticateMobileFirstUser::class));
            Fortify::createUsersUsing(CreateNewMobileFirstUser::class);
            Fortify::resetUserPasswordsUsing(ResetMobileFirstPin::class);

            $this->app->singleton(CreatesNewUsers::class, CreateNewMobileFirstUser::class);
            $this->app->singleton(ResetsUserPasswords::class, ResetMobileFirstPin::class);
            $this->app->singleton(RegisterResponse::class, MobileFirstRegisterResponse::class);

            Fortify::confirmPasswordsUsing(function ($user, string $password): bool {
                return Hash::check($password, (string) $user->getAuthPassword());
            });

            Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
                'canResetPassword' => Features::enabled(Features::resetPasswords()),
                'canRegister' => Features::enabled(Features::registration()),
                'status' => $request->session()->get('status'),
                'auth_intent' => app(ClaimAuthenticationIntent::class)->current($request),
            ]));

            Fortify::requestPasswordResetLinkView(
                fn (Request $request) => Inertia::render(
                    'x-change/auth/ForgotPin',
                    ['status' => $request->session()->get('status')],
                ),
            );
            Fortify::resetPasswordView(
                fn (Request $request) => Inertia::render(
                    'x-change/auth/ResetPin',
                    [
                        'email' => $request->email,
                        'token' => $request->route('token'),
                    ],
                ),
            );
            Fortify::registerView(fn () => Inertia::render('auth/Register'));
        });
    }

    protected function mobileFirstAuthEnabled(): bool
    {
        return (bool) $this->app['config']->get(
            'x-change.onboarding.mobile_first_auth',
            env('XCHANGE_MOBILE_FIRST_AUTH', true),
        );
    }

    protected function bootRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $config = $this->app['config'];

        $this->loadRoutesFrom($this->packagePath('routes/commissioning.php'));

        if ((bool) $config->get('x-change.routes.web', true)) {
            $this->loadRoutesFrom($this->packagePath('routes/web.php'));
        }

        if ((bool) $config->get('x-change.routes.api', true)) {
            $this->loadRoutesFrom($this->packagePath('routes/api.php'));
        }

        if ((bool) $config->get('x-change.partner_api.public_discovery_enabled', true)) {
            $this->loadRoutesFrom($this->packagePath('routes/partner-api-discovery.php'));
        }

        if ((bool) $config->get('x-change.partner_api.enabled', false)) {
            $this->loadRoutesFrom($this->packagePath('routes/partner-api.php'));
        }

        $legacyLifecycleApiEnvironments = (array) $config->get(
            'x-change.routes.legacy_lifecycle_api.environments',
            ['local', 'testing'],
        );

        if (
            (bool) $config->get('x-change.routes.legacy_lifecycle_api.enabled', false)
            && $this->app->environment($legacyLifecycleApiEnvironments)
        ) {
            $this->loadRoutesFrom($this->packagePath('routes/lifecycle-api.php'));
        }
    }

    protected function bootPartnerApiOAuth(): void
    {
        Passport::tokensCan((array) config('x-change.partner_api.scopes', []));

        $minutes = max(1, (int) config('x-change.partner_api.token_ttl_minutes', 15));
        Passport::clientCredentialsTokensExpireIn(new DateInterval(sprintf('PT%dM', $minutes)));
    }

    protected function bootPartnerApiRateLimiter(): void
    {
        RateLimiter::for('x-change-partner-api-discovery', fn (Request $request): Limit => Limit::perMinute(30)
            ->by((string) $request->ip()));

        RateLimiter::for('x-change-partner-api', function (Request $request): Limit {
            $tokenFingerprint = hash('sha256', (string) $request->bearerToken());

            return Limit::perMinute(max(
                1,
                (int) config('x-change.partner_api.rate_limit_per_minute', 60),
            ))->by($tokenFingerprint !== hash('sha256', '') ? $tokenFingerprint : $request->ip());
        });
    }

    protected function bootExceptionRendering(): void
    {
        $exceptions = $this->app->make('Illuminate\Contracts\Debug\ExceptionHandler');

        if (! method_exists($exceptions, 'renderable')) {
            return;
        }

        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->error(
                'The given data was invalid.',
                'VALIDATION_ERROR',
                $e->errors(),
                422,
            );
        });

        $exceptions->renderable(function (VoucherNotFound $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'VOUCHER_NOT_FOUND',
                [],
                404,
            );
        });

        $exceptions->renderable(function (VoucherFlowCapabilityException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $code = match (true) {
                $e instanceof VoucherCannotDisburse => 'VOUCHER_CANNOT_DISBURSE',
                $e instanceof VoucherCannotCollect => 'VOUCHER_CANNOT_COLLECT',
                $e instanceof VoucherRequiresSettlementEnvelope => 'VOUCHER_REQUIRES_SETTLEMENT_ENVELOPE',
                default => 'VOUCHER_FLOW_CAPABILITY_VIOLATION',
            };

            $status = $e instanceof VoucherRequiresSettlementEnvelope ? 409 : 422;

            return $this->apiResponses()->errorFromThrowable(
                $e,
                $code,
                [
                    'type' => 'capability_violation',
                    'flow' => $e->capabilities?->type->value,
                    'can_disburse' => $e->capabilities?->can_disburse,
                    'can_collect' => $e->capabilities?->can_collect,
                    'can_settle' => $e->capabilities?->can_settle,
                ],
                $status,
            );
        });

        $exceptions->renderable(function (InsufficientWalletBalance $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'INSUFFICIENT_WALLET_BALANCE',
                [],
                422,
            );
        });

        $exceptions->renderable(function (PayCodeIssuerNotResolved $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'PAY_CODE_ISSUER_NOT_RESOLVED',
                [],
                401,
            );
        });

        $exceptions->renderable(function (PayCodeWalletNotResolved $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'PAY_CODE_WALLET_NOT_RESOLVED',
                [],
                422,
            );
        });

        $exceptions->renderable(function (PayCodeIssuanceFailed $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'PAY_CODE_ISSUANCE_FAILED',
                [],
                500,
            );
        });

        $exceptions->renderable(function (PayCodeIssuanceBusy $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()
                ->error(
                    $e->getMessage(),
                    'PAY_CODE_ISSUANCE_BUSY',
                    [
                        'submission' => [
                            $e->getMessage(),
                        ],
                    ],
                    503,
                )
                ->header('Retry-After', '1');
        });

        $exceptions->renderable(function (ProviderProvisioningRequired $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->error(
                $e->getMessage(),
                'PROVIDER_PROVISIONING_REQUIRED',
                [
                    'provisioning' => $e->provisioning,
                ],
                409,
            );
        });

        $exceptions->renderable(function (IdempotencyConflict $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'IDEMPOTENCY_CONFLICT',
                [],
                409,
            );
        });

        $exceptions->renderable(function (FundingIntentConflict|FundingIntentTransitionDenied $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'FUNDING_INTENT_CONFLICT',
                [],
                409,
            );
        });

        $exceptions->renderable(function (FundingProviderUnavailable $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'FUNDING_PROVIDER_UNAVAILABLE',
                [],
                503,
            );
        });

        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'UNAUTHENTICATED',
                [],
                401,
            );
        });

        $exceptions->renderable(function (AuthorizationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'FORBIDDEN',
                [],
                403,
            );
        });

        $exceptions->renderable(function (ModelNotFoundException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'RESOURCE_NOT_FOUND',
                [],
                404,
            );
        });

        $exceptions->renderable(function (VoucherCollectionConflict $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return $this->apiResponses()->errorFromThrowable(
                $e,
                'VOUCHER_COLLECTION_CONFLICT',
                [],
                409,
            );
        });
    }

    protected function bootMigrations(): void
    {
        $this->loadMigrationsFrom($this->packagePath('database/migrations'));
    }

    protected function apiResponses(): ApiResponseFactory
    {
        return $this->app->make(ApiResponseFactory::class);
    }

    protected function cockpitOperatorIssuanceActivityService(
        string $configuredKey,
        string $availableKey,
        string $fallback,
    ): string {
        $configured = config("x-change.cockpit.operator_issuance_activity.{$configuredKey}");

        if (! is_string($configured) || trim($configured) === '') {
            return $fallback;
        }

        $available = config("x-change.cockpit.operator_issuance_activity.{$availableKey}", []);

        if (is_array($available) && is_string($available[$configured] ?? null)) {
            return $available[$configured];
        }

        return $configured;
    }

    protected function executionResultHandoffService(
        string $configuredKey,
        string $availableKey,
        string $fallback,
    ): string {
        $configured = config("x-change.execution_result_handoffs.{$configuredKey}");

        if (! is_string($configured) || trim($configured) === '') {
            return $fallback;
        }

        $available = config("x-change.execution_result_handoffs.{$availableKey}", []);

        if (is_array($available) && is_string($available[$configured] ?? null)) {
            return $available[$configured];
        }

        return $configured;
    }

    protected function packagePath(string $path = ''): string
    {
        $base = dirname(__DIR__, 2);

        return $path !== ''
            ? $base.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR)
            : $base;
    }

    protected function mergeLifecycleScenarioDefaults(): void
    {
        $packageLifecycle = require $this->packagePath('config/lifecycle-scenarios.php');
        $configuredLifecycle = $this->app['config']->get('x-change.lifecycle', []);

        if (! is_array($packageLifecycle) || ! is_array($configuredLifecycle)) {
            return;
        }

        foreach ($packageLifecycle as $key => $value) {
            if ($key === 'scenarios' && is_array($value)) {
                $configuredScenarios = $configuredLifecycle['scenarios'] ?? [];

                $this->app['config']->set(
                    'x-change.lifecycle.scenarios',
                    array_replace($value, is_array($configuredScenarios) ? $configuredScenarios : []),
                );

                continue;
            }

            if (! array_key_exists($key, $configuredLifecycle)) {
                $this->app['config']->set("x-change.lifecycle.{$key}", $value);
            }
        }
    }

    protected function alignWalletDefaults(): void
    {
        $slug = config('x-change.payout.system_wallet_slug')
            ?? config('x-change.onboarding.default_wallet_slug')
            ?? 'platform';

        $name = config('x-change.onboarding.default_wallet_name', 'Platform Wallet');

        config()->set('wallet.wallet.default.slug', $slug);
        config()->set('wallet.wallet.default.name', $name);
    }

    protected function alignVoucherDefaults(): void
    {
        $currentVoucherModel = config('vouchers.models.voucher');

        if (! is_string($currentVoucherModel)
            || $currentVoucherModel === ''
            || $currentVoucherModel === Voucher::class) {
            config()->set(
                'vouchers.models.voucher',
                \LBHurtado\Voucher\Models\Voucher::class
            );
        }
    }

    protected function alignAccountSystemUser(): void
    {
        $model = config(
            'x-change.onboarding.issuer_model',
            config('auth.providers.users.model', 'App\\Models\\User'),
        );
        $identifier = config('x-change.payout.system_user_id');
        $column = config('x-change.payout.system_user_column', 'id');

        $candidate = [
            'model' => $model,
            'identifier' => $identifier,
            'identifier_column' => $column,
        ];

        $configuredCandidates = config('account.system_user.candidates', []);

        config()->set('account.system_user.candidates', [
            ...(is_array($configuredCandidates) ? $configuredCandidates : []),
            'x-change' => $candidate,
        ]);

        // Preserve the wallet package's original configuration surface for
        // integrations that have not yet adopted the resolver contract.
        config()->set('account.system_user.model', $model);
        config()->set('account.system_user.identifier', $identifier);
        config()->set('account.system_user.identifier_column', $column);
    }
}
