<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners;

final readonly class ScenarioRunResult
{
    public function __construct(
        public int $exitCode,
        public array $payload,
    ) {}
}
