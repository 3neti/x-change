<?php

declare(strict_types=1);

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use LBHurtado\EmiCore\Contracts\ProviderBalanceReader;
use LBHurtado\EmiCore\Contracts\ProviderLivePreflightProbe;
use LBHurtado\EmiCore\Data\Providers\ProviderBalanceObservationData;
use LBHurtado\EmiCore\Data\Providers\ProviderBalanceRequestData;
use LBHurtado\EmiCore\Data\Providers\ProviderLivePreflightRequestData;
use LBHurtado\EmiCore\Data\Providers\ProviderLivePreflightResultData;
use LBHurtado\EmiCore\Enums\ProviderLivePreflightFailureCode;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Services\Treasury\TreasuryInitializationStateService;
use LBHurtado\XChange\Services\Treasury\TreasuryOpeningBalanceReconciliationService;
use LBHurtado\XChange\Services\Treasury\TreasuryPreflightService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;

it('stops a forced non-interactive install before every side effect when required live preflight fails', function () {
    enableNetbankTreasuryForTests();
    bindInstallerLiveProbe(
        static fn (ProviderLivePreflightRequestData $request): ProviderLivePreflightResultData => new ProviderLivePreflightResultData(
            provider: $request->provider,
            connectionReference: $request->connectionReference,
            ready: false,
            checkedAt: new DateTimeImmutable,
            failureCode: ProviderLivePreflightFailureCode::DnsResolutionFailed,
        ),
    );
    $commands = [];
    Event::listen(
        CommandStarting::class,
        function (CommandStarting $event) use (&$commands): void {
            $commands[] = $event->command;
        },
    );

    $this->artisan('x-change:install', [
        '--force' => true,
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('dns_resolution_failed')
        ->expectsOutputToContain(
            'No migrations, Treasury positions, or UI assets were changed.',
        )
        ->assertFailed();

    expect(TreasuryPosition::query()->count())->toBe(0)
        ->and($commands)->not->toContain('migrate')
        ->not->toContain('vendor:publish')
        ->not->toContain('x-change:treasury:provision')
        ->not->toContain('x-change:treasury:reconcile-opening');
});

it('continues with healthy required connections and skips a failed optional connection', function () {
    enableNetbankTreasuryForTests();
    $connections = (array) config('x-change.treasury.connections');
    $connections['netbank-optional'] = [
        ...$connections['netbank-primary'],
        'mode' => 'optional',
        'inventory_reference' => 'inventory:netbank:optional',
        'settlement_resource_reference' => 'resource:netbank:optional',
    ];
    config()->set('x-change.treasury.connections', $connections);
    bindInstallerLiveProbe(
        static function (
            ProviderLivePreflightRequestData $request,
        ): ProviderLivePreflightResultData {
            if ($request->connectionReference === 'netbank-optional') {
                return new ProviderLivePreflightResultData(
                    provider: $request->provider,
                    connectionReference: $request->connectionReference,
                    ready: false,
                    checkedAt: new DateTimeImmutable,
                    failureCode: ProviderLivePreflightFailureCode::ConnectionTimeout,
                );
            }

            $observation = installerObservation(
                provider: $request->provider,
                connectionReference: $request->connectionReference,
                settlementResourceReference: $request->settlementResourceReference,
                currency: $request->currency,
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
    bindInstallerBalanceReader();

    $this->artisan('x-change:install', installerTestOptions())
        ->expectsOutputToContain(
            'Treasury live preflight unavailable [netbank-optional]: connection_timeout.',
        )
        ->expectsOutputToContain('X-Change installed successfully.')
        ->assertSuccessful();

    expect(
        TreasuryPosition::query()
            ->where('connection_reference', 'netbank-primary')
            ->count(),
    )->toBe(10)
        ->and(
            TreasuryPosition::query()
                ->where('connection_reference', 'netbank-optional')
                ->count(),
        )->toBe(0);
});

it('skips provider access and opening reconciliation for an initialized Treasury', function () {
    enableNetbankTreasuryForTests();
    $providerAmountMinor = 70_000;
    $liveProbeCalls = 0;
    $balanceReaderCalls = 0;
    bindInstallerLiveProbe(
        static function (
            ProviderLivePreflightRequestData $request,
        ) use (
            &$liveProbeCalls,
            &$providerAmountMinor,
        ): ProviderLivePreflightResultData {
            $liveProbeCalls++;
            $observation = installerObservation(
                provider: $request->provider,
                connectionReference: $request->connectionReference,
                settlementResourceReference: $request->settlementResourceReference,
                currency: $request->currency,
                amountMinor: $providerAmountMinor,
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
    bindInstallerBalanceReader(
        static function (
            ProviderBalanceRequestData $request,
        ) use (
            &$balanceReaderCalls,
            &$providerAmountMinor,
        ): ProviderBalanceObservationData {
            $balanceReaderCalls++;

            return installerObservation(
                provider: $request->provider,
                connectionReference: $request->connectionReference,
                settlementResourceReference: $request->settlementResourceReference,
                currency: $request->currency,
                amountMinor: $providerAmountMinor,
            );
        },
    );

    $this->artisan('x-change:install', installerTestOptions())
        ->assertSuccessful();

    expect(TreasuryInventoryOperation::query()->count())->toBe(1);

    $providerAmountMinor = 0;
    $liveProbeCalls = 0;
    $balanceReaderCalls = 0;
    $commands = [];
    Event::listen(
        CommandStarting::class,
        function (CommandStarting $event) use (&$commands): void {
            $commands[] = $event->command;
        },
    );

    $this->artisan('x-change:install', installerTestOptions())
        ->expectsOutputToContain(
            'Treasury already initialized [netbank-primary]; '
            .'skipping opening live preflight and reconciliation.',
        )
        ->expectsOutputToContain('X-Change installed successfully.')
        ->assertSuccessful();

    expect($liveProbeCalls)->toBe(0)
        ->and($balanceReaderCalls)->toBe(0)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1)
        ->and($commands)->not->toContain('x-change:treasury:provision')
        ->not->toContain('x-change:treasury:reconcile-opening');
});

it('resumes an exact partial Treasury topology through the opening workflow', function () {
    enableNetbankTreasuryForTests();
    bindInstallerLiveProbe(
        static function (
            ProviderLivePreflightRequestData $request,
        ): ProviderLivePreflightResultData {
            $observation = installerObservation(
                provider: $request->provider,
                connectionReference: $request->connectionReference,
                settlementResourceReference: $request->settlementResourceReference,
                currency: $request->currency,
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
    bindInstallerBalanceReader();

    $this->artisan('x-change:treasury:provision', [
        '--connection' => ['netbank-primary'],
        '--no-interaction' => true,
    ])->assertSuccessful();

    $this->artisan('x-change:install', installerTestOptions())
        ->expectsOutputToContain(
            'Treasury live preflight ready [netbank-primary].',
        )
        ->expectsOutputToContain('X-Change installed successfully.')
        ->assertSuccessful();

    expect(
        app(TreasuryInitializationStateService::class)->inspect()->initialized,
    )->toBe(['netbank-primary']);
});

it('fails before provider access when existing Treasury topology conflicts', function () {
    enableNetbankTreasuryForTests();
    $liveProbeCalls = 0;
    bindInstallerLiveProbe(
        static function (
            ProviderLivePreflightRequestData $request,
        ) use (&$liveProbeCalls): ProviderLivePreflightResultData {
            $liveProbeCalls++;
            $observation = installerObservation(
                provider: $request->provider,
                connectionReference: $request->connectionReference,
                settlementResourceReference: $request->settlementResourceReference,
                currency: $request->currency,
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
    bindInstallerBalanceReader();

    $this->artisan('x-change:install', installerTestOptions())
        ->assertSuccessful();

    TreasuryPosition::query()
        ->where('position_reference', 'position:system:netbank:netbank-primary:php:clearing')
        ->update(['status' => 'suspended']);
    $liveProbeCalls = 0;

    $this->artisan('x-change:install', installerTestOptions())
        ->expectsOutputToContain(
            'Treasury topology is incomplete or conflicts with configuration [netbank-primary].',
        )
        ->assertFailed();

    expect($liveProbeCalls)->toBe(0);
});

it('makes deferred Treasury installation explicit and visible', function () {
    $this->artisan('x-change:install', [
        ...installerTestOptions(),
        '--no-treasury' => true,
    ])
        ->expectsOutputToContain(
            'Treasury initialization is explicitly deferred [--no-treasury].',
        )
        ->expectsOutputToContain('X-Change installed successfully.')
        ->assertSuccessful();

    expect(TreasuryPosition::query()->count())->toBe(0);
});

function bindInstallerLiveProbe(Closure $result): void
{
    $probe = new class($result) implements ProviderLivePreflightProbe
    {
        public function __construct(private readonly Closure $result) {}

        public function providerCode(): string
        {
            return 'netbank';
        }

        public function checkLiveReadiness(
            ProviderLivePreflightRequestData $request,
        ): ProviderLivePreflightResultData {
            return ($this->result)($request);
        }
    };

    app()->instance($probe::class, $probe);
    app()->tag($probe::class, 'emi.provider-live-preflight-probes');
    forgetInstallerTreasuryServices();
}

function bindInstallerBalanceReader(?Closure $result = null): void
{
    $reader = new class($result) implements ProviderBalanceReader
    {
        public function __construct(private readonly ?Closure $result) {}

        public function providerCode(): string
        {
            return 'netbank';
        }

        public function readBalance(
            ProviderBalanceRequestData $request,
        ): ProviderBalanceObservationData {
            if ($this->result instanceof Closure) {
                return ($this->result)($request);
            }

            return installerObservation(
                provider: $request->provider,
                connectionReference: $request->connectionReference,
                settlementResourceReference: $request->settlementResourceReference,
                currency: $request->currency,
            );
        }
    };

    app()->instance($reader::class, $reader);
    app()->tag($reader::class, 'emi.provider-balance-readers');
    forgetInstallerTreasuryServices();
}

function forgetInstallerTreasuryServices(): void
{
    foreach ([
        TreasuryProviderConnectionCatalog::class,
        TreasuryInitializationStateService::class,
        TreasuryPreflightService::class,
        TreasuryProvisioningService::class,
        TreasuryOpeningBalanceReconciliationService::class,
    ] as $abstract) {
        app()->forgetInstance($abstract);
    }
}

/**
 * @return array<string, bool>
 */
function installerTestOptions(): array
{
    return [
        '--force' => true,
        '--no-migrate' => true,
        '--no-auth' => true,
        '--no-settings' => true,
        '--no-assets' => true,
        '--no-handlers' => true,
        '--no-rider' => true,
        '--no-x-ray' => true,
        '--no-interaction' => true,
    ];
}

function installerObservation(
    string $provider,
    string $connectionReference,
    string $settlementResourceReference,
    string $currency,
    int $amountMinor = 0,
): ProviderBalanceObservationData {
    return new ProviderBalanceObservationData(
        provider: $provider,
        connectionReference: $connectionReference,
        settlementResourceReference: $settlementResourceReference,
        amountMinor: $amountMinor,
        currency: $currency,
        observedAt: new DateTimeImmutable,
        evidenceReference: 'evidence:installer-test',
    );
}
