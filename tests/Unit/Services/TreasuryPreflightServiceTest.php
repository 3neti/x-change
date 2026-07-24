<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\ProviderReadinessProbe;
use LBHurtado\EmiCore\Contracts\SettlementProvider;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityManifestData;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityReadinessData;
use LBHurtado\EmiCore\Data\Providers\ProviderReadinessRequestData;
use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\EmiCore\Support\SettlementProviderRegistry;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Services\Treasury\TreasuryPreflightService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

function treasuryConnectionConfiguration(
    string $mode = 'required',
    string $provider = 'future_emi',
): array {
    return [
        'provider' => $provider,
        'mode' => $mode,
        'currency' => 'PHP',
        'decimal_places' => 2,
        'settlement_resource_reference' => "resource:{$provider}:primary:php",
        'settlement_resource_type' => 'regulated_stored_value',
        'custody_mode' => 'provider_projection',
        'required_capabilities' => [
            'readiness_probe',
            'funding_evidence_read',
        ],
    ];
}

function treasuryTestProvider(string $provider = 'future_emi'): SettlementProvider
{
    return new class($provider) implements SettlementProvider
    {
        public function __construct(private readonly string $provider) {}

        public function manifest(): ProviderCapabilityManifestData
        {
            return new ProviderCapabilityManifestData(
                provider: $this->provider,
                label: 'Future EMI',
                capabilities: [
                    ProviderCapability::ReadinessProbe,
                    ProviderCapability::FundingEvidenceRead,
                ],
            );
        }
    };
}

function treasuryReadinessProbe(
    bool $ready,
    string $provider = 'future_emi',
): ProviderReadinessProbe {
    return new class($ready, $provider) implements ProviderReadinessProbe
    {
        public function __construct(
            private readonly bool $ready,
            private readonly string $provider,
        ) {}

        public function providerCode(): string
        {
            return $this->provider;
        }

        public function checkReadiness(
            ProviderReadinessRequestData $request,
        ): ProviderCapabilityReadinessData {
            $checks = [];
            $issues = [];

            foreach ($request->requiredCapabilities as $capability) {
                $checks[$capability->value] = $this->ready;

                if (! $this->ready) {
                    $issues[$capability->value] = ['provider-configuration-incomplete'];
                }
            }

            return new ProviderCapabilityReadinessData(
                provider: $request->provider,
                connectionReference: $request->connectionReference,
                checks: $checks,
                issues: $issues,
                checkedAt: new DateTimeImmutable,
            );
        }
    };
}

it('passes only when required provider capabilities are installed and ready', function () {
    $preflight = new TreasuryPreflightService(
        new TreasuryProviderConnectionCatalog([
            'future-primary' => treasuryConnectionConfiguration(),
        ]),
        new SettlementProviderRegistry([treasuryTestProvider()]),
        [treasuryReadinessProbe(true)],
    );

    $result = $preflight->run();

    expect($result->passes())->toBeTrue()
        ->and($result->connections)->toHaveCount(1)
        ->and($result->connections[0]->ready)->toBeTrue()
        ->and($result->connections[0]->issues)->toBe([]);
});

it('blocks a required connection and permits an unavailable optional connection', function () {
    $provider = treasuryTestProvider();
    $probe = treasuryReadinessProbe(false);

    $required = (new TreasuryPreflightService(
        new TreasuryProviderConnectionCatalog([
            'future-primary' => treasuryConnectionConfiguration(),
        ]),
        new SettlementProviderRegistry([$provider]),
        [$probe],
    ))->run();

    $optional = (new TreasuryPreflightService(
        new TreasuryProviderConnectionCatalog([
            'future-primary' => treasuryConnectionConfiguration('optional'),
        ]),
        new SettlementProviderRegistry([$provider]),
        [$probe],
    ))->run();

    expect($required->passes())->toBeFalse()
        ->and($required->connections[0]->issues)->toContain('provider-capability-not-ready')
        ->and($optional->passes())->toBeTrue()
        ->and($optional->connections[0]->ready)->toBeFalse();
});

it('reports a configured provider that is not installed', function () {
    $result = (new TreasuryPreflightService(
        new TreasuryProviderConnectionCatalog([
            'future-primary' => treasuryConnectionConfiguration(),
        ]),
        new SettlementProviderRegistry([]),
        [],
    ))->run();

    expect($result->passes())->toBeFalse()
        ->and($result->connections[0]->issues)->toBe(['provider-not-installed']);
});

it('rejects ambiguous readiness probe registrations', function () {
    expect(fn () => new TreasuryPreflightService(
        new TreasuryProviderConnectionCatalog([]),
        new SettlementProviderRegistry([]),
        [treasuryReadinessProbe(true), treasuryReadinessProbe(true)],
    ))->toThrow(
        TreasuryConfigurationException::class,
        'Multiple readiness probes are registered for provider [future_emi].',
    );
});
