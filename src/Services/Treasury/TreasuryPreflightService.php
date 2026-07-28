<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use LBHurtado\EmiCore\Contracts\ProviderLivePreflightProbe;
use LBHurtado\EmiCore\Contracts\ProviderReadinessProbe;
use LBHurtado\EmiCore\Contracts\SettlementProviderRegistryContract;
use LBHurtado\EmiCore\Data\Providers\ProviderLivePreflightRequestData;
use LBHurtado\EmiCore\Data\Providers\ProviderReadinessRequestData;
use LBHurtado\EmiCore\Enums\ProviderLivePreflightFailureCode;
use LBHurtado\XChange\Data\Treasury\TreasuryConnectionPreflightData;
use LBHurtado\XChange\Data\Treasury\TreasuryPreflightData;
use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use Throwable;

final class TreasuryPreflightService
{
    /** @var array<string, ProviderReadinessProbe> */
    private array $probes = [];

    /** @var array<string, ProviderLivePreflightProbe> */
    private array $liveProbes = [];

    /**
     * @param  iterable<ProviderReadinessProbe>  $probes
     * @param  iterable<ProviderLivePreflightProbe>  $liveProbes
     */
    public function __construct(
        private readonly TreasuryProviderConnectionCatalog $connections,
        private readonly SettlementProviderRegistryContract $providers,
        iterable $probes,
        iterable $liveProbes = [],
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

        foreach ($liveProbes as $probe) {
            $provider = mb_strtolower(trim($probe->providerCode()));

            if (isset($this->liveProbes[$provider])) {
                throw new TreasuryConfigurationException(
                    "Multiple live preflight probes are registered for provider [{$provider}].",
                );
            }

            $this->liveProbes[$provider] = $probe;
        }
    }

    /**
     * @param  list<string>  $connectionReferences
     */
    public function run(
        array $connectionReferences = [],
        bool $live = false,
    ): TreasuryPreflightData {
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

            $staticReady = $issues === [];
            $liveReady = null;

            if ($live && $staticReady) {
                $liveReady = $this->runLivePreflight($connection, $issues);
            }

            $ready = $staticReady && (! $live || $liveReady === true);

            $results[] = new TreasuryConnectionPreflightData(
                connection: $connection,
                ready: $ready,
                issues: array_values(array_unique($issues)),
                staticReady: $staticReady,
                liveReady: $liveReady,
            );
        }

        return new TreasuryPreflightData($results);
    }

    /**
     * @param  list<string>  $issues
     */
    private function runLivePreflight(
        TreasuryProviderConnectionData $connection,
        array &$issues,
    ): bool {
        $probe = $this->liveProbes[$connection->provider] ?? null;

        if (! $probe instanceof ProviderLivePreflightProbe) {
            $issues[] = 'live-preflight-probe-not-installed';

            return false;
        }

        try {
            $result = $probe->checkLiveReadiness(
                new ProviderLivePreflightRequestData(
                    provider: $connection->provider,
                    connectionReference: $connection->reference,
                    settlementResourceReference: $connection->settlementResourceReference,
                    currency: $connection->currency,
                ),
            );
        } catch (Throwable) {
            $issues[] = ProviderLivePreflightFailureCode::ProviderUnavailable->value;

            return false;
        }

        if (
            $result->provider !== $connection->provider
            || $result->connectionReference !== $connection->reference
        ) {
            $issues[] = ProviderLivePreflightFailureCode::InvalidBalanceResponse->value;

            return false;
        }

        if (! $result->ready) {
            $issues[] = (
                $result->failureCode
                ?? ProviderLivePreflightFailureCode::ProviderUnavailable
            )->value;

            return false;
        }

        $observation = $result->observation;

        if ($observation === null) {
            $issues[] = ProviderLivePreflightFailureCode::InvalidBalanceResponse->value;

            return false;
        }

        if (
            $observation->provider !== $connection->provider
            || $observation->connectionReference !== $connection->reference
            || $observation->settlementResourceReference
                !== $connection->settlementResourceReference
            || mb_strtoupper($observation->currency)
                !== mb_strtoupper($connection->currency)
        ) {
            $issues[] = ProviderLivePreflightFailureCode::InvalidBalanceResponse->value;

            return false;
        }

        return true;
    }
}
