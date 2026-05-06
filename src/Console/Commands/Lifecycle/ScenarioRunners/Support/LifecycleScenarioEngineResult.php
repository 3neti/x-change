<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support;

final readonly class LifecycleScenarioEngineResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $exitCode,
        public array $payload,
    ) {}
}
