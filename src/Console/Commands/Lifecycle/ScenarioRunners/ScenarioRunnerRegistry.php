<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners;

use RuntimeException;

final class ScenarioRunnerRegistry
{
    public function has(?string $mode): bool
    {
        return in_array($mode, [
            null,
            'default',
            'settlement_envelope_evaluation',
            'settlement_three_party_flow',
        ], true);
    }

    public function for(?string $mode): ScenarioRunnerContract
    {
        return match ($mode) {
            null, 'default' => app(DefaultClaimScenarioRunner::class),
            'settlement_envelope_evaluation' => app(SettlementEnvelopeEvaluationScenarioRunner::class),
            'settlement_three_party_flow' => app(SettlementThreePartyScenarioRunner::class),
            default => throw new RuntimeException("No lifecycle scenario runner registered for mode [{$mode}]."),
        };
    }
}
