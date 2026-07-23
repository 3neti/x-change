<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Lifecycle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Lifecycle\Output\NullLifecycleOutput;
use LBHurtado\XChange\Lifecycle\Runners\QrPhFundingSimulationScenarioRunner;
use LBHurtado\XChange\Lifecycle\Runners\ScenarioRunContext;
use LBHurtado\XChange\Lifecycle\Runners\ScenarioRunResult;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRepository;

final class RunQrPhFundingSimulation
{
    public const SCENARIO_KEY = 'qrph_funding_existing_mobile_demo';

    public function __construct(
        private readonly LifecycleScenarioRepository $scenarios,
        private readonly QrPhFundingSimulationScenarioRunner $runner,
        private readonly SettlementEnvelopeReadinessContract $readiness,
    ) {}

    public function handle(Model $operator): ScenarioRunResult
    {
        $scenario = $this->scenarios->findOrFail(self::SCENARIO_KEY);
        $mobile = $operator->getRawOriginal('mobile');

        return $this->runner->run(new ScenarioRunContext(
            output: new NullLifecycleOutput,
            scenarioKey: self::SCENARIO_KEY,
            scenario: $scenario,
            issuer: $operator,
            generated: null,
            voucher: null,
            attempts: [],
            baseClaimMobile: is_string($mobile) ? $mobile : '',
            estimate: [],
            idempotencyKey: 'cockpit-qrph-simulation-'.Str::lower((string) Str::ulid()),
            readiness: $this->readiness,
        ));
    }
}
