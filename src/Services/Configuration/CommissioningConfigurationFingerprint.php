<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use Illuminate\Support\Arr;
use JsonException;
use RuntimeException;

final readonly class CommissioningConfigurationFingerprint
{
    public function __construct(
        private DeploymentConfigurationInspector $deployment,
        private DeploymentEnvironmentCatalog $environment,
    ) {}

    /**
     * @throws JsonException
     */
    public function current(): string
    {
        $deployment = $this->deployment->inspect();
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('A stable application key is required for commissioning.');
        }

        $configuration = [];

        foreach ($this->environment->variables() as $variable) {
            if ($variable->configPath === null) {
                continue;
            }

            $configuration[$variable->key] = config($variable->configPath);
        }

        ksort($configuration);

        $payload = json_encode([
            'schema' => 1,
            'profile' => $deployment['profile'],
            'connections' => Arr::sort($deployment['active_connections']),
            'commercial_governance_mode' => config(
                'x-change.commercial.offerings.governance_mode',
                'bootstrap_immutable',
            ),
            'configuration' => $configuration,
        ], JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $payload, $key);
    }
}
