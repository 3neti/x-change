<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use LBHurtado\EmiCore\Contracts\DeploymentEnvironmentContributor;
use LBHurtado\EmiCore\Data\Configuration\EnvironmentVariableData;
use RuntimeException;

final readonly class DeploymentEnvironmentCatalog
{
    /**
     * @param  iterable<DeploymentEnvironmentContributor>  $contributors
     */
    public function __construct(private iterable $contributors) {}

    /**
     * @return list<EnvironmentVariableData>
     */
    public function variables(): array
    {
        $variables = [];

        foreach ($this->contributors as $contributor) {
            foreach ($contributor->environmentVariables() as $variable) {
                if (isset($variables[$variable->key])) {
                    throw new RuntimeException(
                        "Duplicate deployment environment descriptor [{$variable->key}].",
                    );
                }

                $variables[$variable->key] = $variable;
            }
        }

        ksort($variables);

        return array_values($variables);
    }
}
