<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners;

interface ScenarioRunnerContract
{
    public function run(ScenarioRunContext $context): ScenarioRunResult;
}
