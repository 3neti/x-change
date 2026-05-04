<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;

interface ScenarioRunnerContract
{
    public function run(
        Command $command,
        string $scenarioKey,
        array $scenario,
        Model $issuer,
        mixed $generated,
        mixed $voucher,
        array $attempts,
        string $baseClaimMobile,
        array $estimate,
        string $idempotencyKey,
        ?SettlementEnvelopeReadinessContract $readiness = null,
    ): ScenarioRunResult;
}
