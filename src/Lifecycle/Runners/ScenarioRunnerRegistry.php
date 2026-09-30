<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

use RuntimeException;

final class ScenarioRunnerRegistry
{
    public function has(?string $mode): bool
    {
        return in_array($mode, [
            null,
            'default',
            'turnkey_onboarding',
            'sequential_claims',
            'settlement_envelope_evaluation',
            'settlement_three_party_flow',
            'execution_engine_contract_demo',
            'live_provider_verification',
            'account_management',
            'qrph_funding_simulation',
            'qrph_unknown_mobile_onboarding',
            'on_demand_issuance_funding',
            'public_auto_generate',
            'treasury_basic_cash',
            'treasury_live_basic_cash',
            'treasury_onboarding_grant',
            'feedback_delivery',
            'onboarding_voucher',
            'payment_voucher_collection',
            'aui_insurance_acquisition',
            'commercial_operations_simulation',
            'treasury_account_grant_simulation',
            'provisioning_governance_simulation',
            'affiliation_networking_simulation',
            'campaign_batch',
            'commercial_pay_code',
        ], true);
    }

    public function for(?string $mode): ScenarioRunnerContract
    {
        return match ($mode) {
            null, 'default' => app(DefaultClaimScenarioRunner::class),
            'turnkey_onboarding' => app(TurnkeyOnboardingScenarioRunner::class),
            'sequential_claims' => app(SequentialClaimsScenarioRunner::class),
            'settlement_envelope_evaluation' => app(SettlementEnvelopeEvaluationScenarioRunner::class),
            'settlement_three_party_flow' => app(SettlementThreePartyScenarioRunner::class),
            'execution_engine_contract_demo' => app(ExecutionEngineContractScenarioRunner::class),
            'live_provider_verification' => app(LiveProviderVerificationScenarioRunner::class),
            'account_management' => app(AccountManagementScenarioRunner::class),
            'qrph_funding_simulation' => app(QrPhFundingSimulationScenarioRunner::class),
            'qrph_unknown_mobile_onboarding' => app(QrPhUnknownMobileOnboardingScenarioRunner::class),
            'on_demand_issuance_funding' => app(OnDemandIssuanceFundingScenarioRunner::class),
            'public_auto_generate' => app(PublicAutoGenerateScenarioRunner::class),
            'treasury_basic_cash' => app(TreasuryBasicCashScenarioRunner::class),
            'treasury_live_basic_cash' => app(TreasuryLiveBasicCashScenarioRunner::class),
            'treasury_onboarding_grant' => app(TreasuryOnboardingGrantScenarioRunner::class),
            'feedback_delivery' => app(FeedbackDeliveryScenarioRunner::class),
            'onboarding_voucher' => app(OnboardingVoucherScenarioRunner::class),
            'payment_voucher_collection' => app(PaymentVoucherCollectionScenarioRunner::class),
            'aui_insurance_acquisition' => app(AuiInsuranceAcquisitionScenarioRunner::class),
            'commercial_operations_simulation' => app(CommercialOperationsSimulationScenarioRunner::class),
            'treasury_account_grant_simulation' => app(TreasuryAccountGrantSimulationScenarioRunner::class),
            'provisioning_governance_simulation' => app(ProvisioningGovernanceSimulationScenarioRunner::class),
            'affiliation_networking_simulation' => app(AffiliationNetworkingSimulationScenarioRunner::class),
            'campaign_batch' => app(CampaignBatchScenarioRunner::class),
            'commercial_pay_code' => app(CommercialPayCodeScenarioRunner::class),
            default => throw new RuntimeException("No lifecycle scenario runner registered for mode [{$mode}]."),
        };
    }
}
