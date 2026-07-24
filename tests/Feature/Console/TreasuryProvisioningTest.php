<?php

declare(strict_types=1);

use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Transaction;
use Illuminate\Console\Command;
use LBHurtado\EmiCore\Contracts\ProviderReadinessProbe;
use LBHurtado\EmiCore\Contracts\SettlementProvider;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityManifestData;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityReadinessData;
use LBHurtado\EmiCore\Data\Providers\ProviderReadinessRequestData;
use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\EmiCore\Support\SettlementProviderRegistry;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Console\Commands\InstallXChangeCommand;
use LBHurtado\XChange\Services\Treasury\TreasuryPreflightService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XChange\Tests\Fakes\User;

it('provisions an idempotent zero-balance system treasury position', function () {
    $systemPrincipal = User::query()->create([
        'name' => 'System Treasury Principal',
        'email' => 'system-treasury@example.com',
        'password' => 'not-a-login-credential',
    ]);

    config()->set('x-change.treasury.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.treasury.principal_reference', 'principal:system');
    config()->set('x-change.treasury.system_mandate_reference', 'mandate:system:treasury');
    config()->set('x-change.treasury.legal_profile', 'treasury-settlement-ph-v1');
    config()->set('x-change.treasury.legal_profile_version', '2026-07-24.1');

    $provider = new class implements SettlementProvider
    {
        public function manifest(): ProviderCapabilityManifestData
        {
            return new ProviderCapabilityManifestData(
                provider: 'future_emi',
                label: 'Future EMI',
                capabilities: [
                    ProviderCapability::ReadinessProbe,
                    ProviderCapability::FundingEvidenceRead,
                ],
            );
        }
    };
    $probe = new class implements ProviderReadinessProbe
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
                    ProviderCapability::FundingEvidenceRead->value => true,
                ],
                issues: [],
                checkedAt: new DateTimeImmutable,
            );
        }
    };
    $resolver = new class($systemPrincipal) implements SystemUserResolverContract
    {
        public function __construct(private readonly User $systemPrincipal) {}

        public function resolve(): Wallet
        {
            return $this->systemPrincipal;
        }
    };

    $preflight = new TreasuryPreflightService(
        new TreasuryProviderConnectionCatalog([
            'future-primary' => [
                'provider' => 'future_emi',
                'mode' => 'required',
                'currency' => 'PHP',
                'decimal_places' => 2,
                'settlement_resource_reference' => 'resource:future_emi:primary:php',
                'settlement_resource_type' => 'regulated_stored_value',
                'custody_mode' => 'provider_projection',
                'required_capabilities' => [
                    'readiness_probe',
                    'funding_evidence_read',
                ],
            ],
        ]),
        new SettlementProviderRegistry([$provider]),
        [$probe],
    );
    $service = new TreasuryProvisioningService(
        $preflight,
        $resolver,
        app(TreasuryPositionProvisioningContract::class),
    );
    $transactionsBefore = Transaction::query()->count();

    $first = $service->provision();
    $second = $service->provision();

    expect($first->positions)->toHaveCount(1)
        ->and($second->positions)->toHaveCount(1)
        ->and($first->positions[0]->positionReference)
        ->toBe('position:system:future_emi:future-primary:php:clearing')
        ->and($first->positions[0]->balanceMinor)->toBe(0)
        ->and($second->positions[0]->balanceMinor)->toBe(0)
        ->and(TreasuryPosition::query()->count())->toBe(1)
        ->and(Transaction::query()->count())->toBe($transactionsBefore);
});

it('registers treasury commands and keeps installation safe when no connection is active', function () {
    config()->set('x-change.treasury.connections', []);

    $this->artisan('x-change:treasury:preflight', ['--json' => true])
        ->expectsOutputToContain('"ready":true')
        ->assertExitCode(Command::SUCCESS);

    $this->artisan('x-change:treasury:provision', ['--json' => true])
        ->expectsOutputToContain('"provisioned":[]')
        ->assertExitCode(Command::SUCCESS);

    $signature = (new ReflectionClass(InstallXChangeCommand::class))
        ->getDefaultProperties()['signature'];

    expect($signature)->toContain('{--no-treasury');
});
