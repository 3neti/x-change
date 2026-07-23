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
            default => throw new RuntimeException("No lifecycle scenario runner registered for mode [{$mode}]."),
        };
    }
}
