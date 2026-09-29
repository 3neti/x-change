<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Lifecycle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Lifecycle\Output\NullLifecycleOutput;
use LBHurtado\XChange\Lifecycle\Runners\OnDemandIssuanceFundingScenarioRunner;
use LBHurtado\XChange\Lifecycle\Runners\QrPhFundingSimulationScenarioRunner;
use LBHurtado\XChange\Lifecycle\Runners\ScenarioRunContext;
use LBHurtado\XChange\Lifecycle\Runners\ScenarioRunResult;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRepository;

final class RunQrPhFundingSimulation
{
    public const SCENARIO_KEY = 'qrph_funding_existing_mobile_demo';

    public const ON_DEMAND_SCENARIO_KEY = 'on_demand_issuance_fixed_qr_demo';

    public function __construct(
        private readonly LifecycleScenarioRepository $scenarios,
        private readonly QrPhFundingSimulationScenarioRunner $runner,
        private readonly OnDemandIssuanceFundingScenarioRunner $onDemandRunner,
        private readonly SettlementEnvelopeReadinessContract $readiness,
    ) {}

    public function handle(Model $operator, string $scenarioKey = self::SCENARIO_KEY): ScenarioRunResult
    {
        $runner = match ($scenarioKey) {
            self::SCENARIO_KEY => $this->runner,
            self::ON_DEMAND_SCENARIO_KEY => $this->onDemandRunner,
            default => throw new \InvalidArgumentException('Unsupported QR Ph lifecycle scenario.'),
        };
        $scenario = $this->scenarios->findOrFail($scenarioKey);
        $mobile = $operator->getRawOriginal('mobile');

        return $runner->run(new ScenarioRunContext(
            output: new NullLifecycleOutput,
            scenarioKey: $scenarioKey,
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
