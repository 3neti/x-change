<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\EmiCore\Contracts\ProviderLivePreflightProbe;
use LBHurtado\EmiCore\Contracts\ProviderReadinessProbe;
use LBHurtado\EmiCore\Contracts\SettlementProvider;
use LBHurtado\EmiCore\Data\Providers\ProviderBalanceObservationData;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityManifestData;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityReadinessData;
use LBHurtado\EmiCore\Data\Providers\ProviderLivePreflightRequestData;
use LBHurtado\EmiCore\Data\Providers\ProviderLivePreflightResultData;
use LBHurtado\EmiCore\Data\Providers\ProviderReadinessRequestData;
use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\EmiCore\Enums\ProviderLivePreflightFailureCode;
use LBHurtado\EmiCore\Support\SettlementProviderRegistry;
use LBHurtado\XChange\Services\Treasury\TreasuryPreflightService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;

it('reports static and live readiness separately without exposing provider details', function () {
    $preflight = livePreflightService(
        static fn (ProviderLivePreflightRequestData $request): ProviderLivePreflightResultData => new ProviderLivePreflightResultData(
            provider: $request->provider,
            connectionReference: $request->connectionReference,
            ready: false,
            checkedAt: new DateTimeImmutable,
            failureCode: ProviderLivePreflightFailureCode::AuthenticationFailed,
        ),
    );
    app()->instance(TreasuryPreflightService::class, $preflight);

    $exitCode = Artisan::call('x-change:treasury:preflight', [
        '--live' => true,
        '--json' => true,
        '--no-interaction' => true,
    ]);
    $output = Artisan::output();

    expect($exitCode)->not->toBe(0)
        ->and($output)->toContain('"level":"live"')
        ->toContain('"static_ready":true')
        ->toContain('"live_ready":false')
        ->toContain('"authentication_failed"')
        ->not->toContain('sensitive-client-secret')
        ->not->toContain('9150012345678901');
});

it('does not let an optional live failure block a healthy required connection', function () {
    $preflight = livePreflightService(
        static function (
            ProviderLivePreflightRequestData $request,
        ): ProviderLivePreflightResultData {
            if ($request->connectionReference === 'future-optional') {
                return new ProviderLivePreflightResultData(
                    provider: $request->provider,
                    connectionReference: $request->connectionReference,
                    ready: false,
                    checkedAt: new DateTimeImmutable,
                    failureCode: ProviderLivePreflightFailureCode::ConnectionTimeout,
                );
            }

            $observation = liveObservation($request);

            return new ProviderLivePreflightResultData(
                provider: $request->provider,
                connectionReference: $request->connectionReference,
                ready: true,
                checkedAt: $observation->observedAt,
                observation: $observation,
            );
        },
        includeOptional: true,
    );

    $result = $preflight->run(live: true);
    $connections = collect($result->connections)->keyBy(
        static fn ($connection): string => $connection->connection->reference,
    );

    expect($result->passes())->toBeTrue()
        ->and($result->connections)->toHaveCount(2)
        ->and($connections['future-required']->ready)->toBeTrue()
        ->and($connections['future-optional']->ready)->toBeFalse()
        ->and($connections['future-optional']->issues)->toBe([
            'connection_timeout',
        ]);
});

it('rejects a successful live result with mismatched observation identity', function () {
    $preflight = livePreflightService(
        static function (
            ProviderLivePreflightRequestData $request,
        ): ProviderLivePreflightResultData {
            $observation = new ProviderBalanceObservationData(
                provider: $request->provider,
                connectionReference: $request->connectionReference,
                settlementResourceReference: 'resource:unexpected',
                amountMinor: 0,
                currency: $request->currency,
                observedAt: new DateTimeImmutable,
                evidenceReference: 'evidence:sanitized',
            );

            return new ProviderLivePreflightResultData(
                provider: $request->provider,
                connectionReference: $request->connectionReference,
                ready: true,
                checkedAt: $observation->observedAt,
                observation: $observation,
            );
        },
    );

    $result = $preflight->run(live: true);

    expect($result->passes())->toBeFalse()
        ->and($result->connections[0]->issues)
        ->toBe(['invalid_balance_response']);
});

function livePreflightService(
    Closure $liveResult,
    bool $includeOptional = false,
): TreasuryPreflightService {
    $provider = new class implements SettlementProvider
    {
        public function manifest(): ProviderCapabilityManifestData
        {
            return new ProviderCapabilityManifestData(
                provider: 'future_emi',
                label: 'Future EMI',
                capabilities: [
                    ProviderCapability::ReadinessProbe,
                    ProviderCapability::BalanceRead,
                ],
            );
        }
    };
    $staticProbe = new class implements ProviderReadinessProbe
    {
        public function providerCode(): string
        {
            return 'future_emi';
        }

        public function checkReadiness(
            ProviderReadinessRequestData $request,
        ): ProviderCapabilityReadinessData {
            return new ProviderCapabilityReadinessData(
                provider: $request->provider,
                connectionReference: $request->connectionReference,
                checks: [
                    ProviderCapability::ReadinessProbe->value => true,
                    ProviderCapability::BalanceRead->value => true,
                ],
                issues: [],
                checkedAt: new DateTimeImmutable,
            );
        }
    };
    $liveProbe = new class($liveResult) implements ProviderLivePreflightProbe
    {
        public function __construct(private readonly Closure $result) {}

        public function providerCode(): string
        {
            return 'future_emi';
        }

        public function checkLiveReadiness(
            ProviderLivePreflightRequestData $request,
        ): ProviderLivePreflightResultData {
            return ($this->result)($request);
        }
    };
    $connections = [
        'future-required' => liveConnection('required'),
    ];

    if ($includeOptional) {
        $connections['future-optional'] = liveConnection('optional');
    }

    return new TreasuryPreflightService(
        new TreasuryProviderConnectionCatalog($connections),
        new SettlementProviderRegistry([$provider]),
        [$staticProbe],
        [$liveProbe],
    );
}

/**
 * @return array<string, mixed>
 */
function liveConnection(string $mode): array
{
    return [
        'provider' => 'future_emi',
        'mode' => $mode,
        'currency' => 'PHP',
        'decimal_places' => 2,
        'inventory_reference' => "inventory:future_emi:{$mode}:php",
        'settlement_resource_reference' => "resource:future_emi:{$mode}:php",
        'settlement_resource_type' => 'regulated_stored_value',
        'custody_mode' => 'provider_projection',
        'required_capabilities' => [
            'readiness_probe',
            'balance_read',
        ],
    ];
}

function liveObservation(
    ProviderLivePreflightRequestData $request,
): ProviderBalanceObservationData {
    return new ProviderBalanceObservationData(
        provider: $request->provider,
        connectionReference: $request->connectionReference,
        settlementResourceReference: $request->settlementResourceReference,
        amountMinor: 0,
        currency: $request->currency,
        observedAt: new DateTimeImmutable,
        evidenceReference: 'evidence:sanitized',
    );
}
