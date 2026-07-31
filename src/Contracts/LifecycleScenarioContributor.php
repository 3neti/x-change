<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface LifecycleScenarioContributor
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function lifecycleScenarios(): array;
}
