<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Configuration;

final readonly class DeploymentProfileData
{
    /**
     * @param  list<string>  $connectionReferences
     * @param  list<string>  $providerCodes
     */
    public function __construct(
        public string $name,
        public array $connectionReferences,
        public array $providerCodes,
        public bool $productionAllowed,
    ) {}
}
