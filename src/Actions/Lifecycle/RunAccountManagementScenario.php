<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Lifecycle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Lifecycle\Output\NullLifecycleOutput;
use LBHurtado\XChange\Lifecycle\Runners\AccountManagementScenarioRunner;
use LBHurtado\XChange\Lifecycle\Runners\ScenarioRunContext;
use LBHurtado\XChange\Lifecycle\Runners\ScenarioRunResult;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRepository;

final class RunAccountManagementScenario
{
    public const SCENARIO_KEY = 'account_management_funding_destinations_demo';

    public function __construct(
        private readonly LifecycleScenarioRepository $scenarios,
        private readonly AccountManagementScenarioRunner $runner,
        private readonly SettlementEnvelopeReadinessContract $readiness,
    ) {}

    public function handle(Model $operator): ScenarioRunResult
    {
        $scenario = $this->scenarios->findOrFail(self::SCENARIO_KEY);

        return $this->runner->run(new ScenarioRunContext(
            output: new NullLifecycleOutput,
            scenarioKey: self::SCENARIO_KEY,
            scenario: $scenario,
            issuer: $operator,
            generated: null,
            voucher: null,
            attempts: [],
            baseClaimMobile: '',
            estimate: [],
            idempotencyKey: 'cockpit-account-scenario-'.Str::lower((string) Str::ulid()),
            readiness: $this->readiness,
        ));
    }
}
