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
            'live_provider_verification',
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
            'live_provider_verification' => app(LiveProviderVerificationScenarioRunner::class),
            default => throw new RuntimeException("No lifecycle scenario runner registered for mode [{$mode}]."),
        };
    }
}
