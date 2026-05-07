<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

interface ScenarioRunnerContract
{
    public function run(ScenarioRunContext $context): ScenarioRunResult;
}
