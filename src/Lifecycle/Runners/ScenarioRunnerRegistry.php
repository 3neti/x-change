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
            'treasury_basic_cash',
            'treasury_live_basic_cash',
            'treasury_onboarding_grant',
            'feedback_delivery',
            'onboarding_voucher',
            'commercial_operations_simulation',
            'treasury_account_grant_simulation',
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
            'treasury_basic_cash' => app(TreasuryBasicCashScenarioRunner::class),
            'treasury_live_basic_cash' => app(TreasuryLiveBasicCashScenarioRunner::class),
            'treasury_onboarding_grant' => app(TreasuryOnboardingGrantScenarioRunner::class),
            'feedback_delivery' => app(FeedbackDeliveryScenarioRunner::class),
            'onboarding_voucher' => app(OnboardingVoucherScenarioRunner::class),
            'commercial_operations_simulation' => app(CommercialOperationsSimulationScenarioRunner::class),
            'treasury_account_grant_simulation' => app(TreasuryAccountGrantSimulationScenarioRunner::class),
            default => throw new RuntimeException("No lifecycle scenario runner registered for mode [{$mode}]."),
        };
    }
}
