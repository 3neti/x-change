<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners;

final readonly class ScenarioRunnerResolution
{
    /**
     * @param  array<string, mixed>  $scenario
     */
    public function __construct(
        public string $mode,
        public array $scenario,
        public ScenarioRunnerContract $runner,
    ) {}
}
