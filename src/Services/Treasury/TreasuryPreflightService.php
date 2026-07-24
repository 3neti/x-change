<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use LBHurtado\EmiCore\Contracts\ProviderReadinessProbe;
use LBHurtado\EmiCore\Contracts\SettlementProviderRegistryContract;
use LBHurtado\EmiCore\Data\Providers\ProviderReadinessRequestData;
use LBHurtado\XChange\Data\Treasury\TreasuryConnectionPreflightData;
use LBHurtado\XChange\Data\Treasury\TreasuryPreflightData;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use Throwable;

final class TreasuryPreflightService
{
    /** @var array<string, ProviderReadinessProbe> */
    private array $probes = [];

    /**
     * @param  iterable<ProviderReadinessProbe>  $probes
     */
    public function __construct(
        private readonly TreasuryProviderConnectionCatalog $connections,
        private readonly SettlementProviderRegistryContract $providers,
        iterable $probes,
    ) {
        foreach ($probes as $probe) {
            $provider = mb_strtolower(trim($probe->providerCode()));

            if (isset($this->probes[$provider])) {
                throw new TreasuryConfigurationException(
                    "Multiple readiness probes are registered for provider [{$provider}].",
                );
            }

            $this->probes[$provider] = $probe;
        }
    }

    /**
     * @param  list<string>  $connectionReferences
     */
    public function run(array $connectionReferences = []): TreasuryPreflightData
    {
        $results = [];

        foreach ($this->connections->active($connectionReferences) as $connection) {
            $issues = [];

            if (! $this->providers->has($connection->provider)) {
                $issues[] = 'provider-not-installed';
            } else {
                $manifest = $this->providers->get($connection->provider);

                foreach ($connection->requiredCapabilities as $capability) {
                    if (! $manifest->supports($capability)) {
                        $issues[] = "capability-not-supported:{$capability->value}";
                    }
                }
            }

            $probe = $this->probes[$connection->provider] ?? null;

            if ($issues === [] && $probe === null) {
                $issues[] = 'readiness-probe-not-installed';
            }

            if ($issues === [] && $probe !== null) {
                try {
                    $readiness = $probe->checkReadiness(new ProviderReadinessRequestData(
                        provider: $connection->provider,
                        connectionReference: $connection->reference,
                        requiredCapabilities: $connection->requiredCapabilities,
                    ));

                    if (
                        $readiness->provider !== $connection->provider
                        || $readiness->connectionReference !== $connection->reference
                    ) {
                        $issues[] = 'provider-readiness-identity-mismatch';
                    }

                    if (! $readiness->readyFor($connection->requiredCapabilities)) {
                        $issues[] = 'provider-capability-not-ready';
                    }

                    foreach ($readiness->issues as $capabilityIssues) {
                        foreach ($capabilityIssues as $issue) {
                            $issues[] = $issue;
                        }
                    }
                } catch (Throwable) {
                    $issues[] = 'provider-probe-unavailable';
                }
            }

            $results[] = new TreasuryConnectionPreflightData(
                connection: $connection,
                ready: $issues === [],
                issues: array_values(array_unique($issues)),
            );
        }

        return new TreasuryPreflightData($results);
    }
}
