<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialGovernanceMode;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialOfferingActivation;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;

it('commissions immutable baseline offerings without fabricating human approval', function (): void {
    config()->set(
        'x-change.commercial.offerings.governance_mode',
        CommercialGovernanceMode::BootstrapImmutable->value,
    );

    $activations = app(ProvisionCommercialBaselines::class)
        ->provision('commissioning-manifest:test-baseline');

    expect($activations)->toHaveCount(2)
        ->and(CommercialOffering::query()->count())->toBe(2)
        ->and(CommercialOfferingActivation::query()->count())->toBe(2);

    $payCode = CommercialOffering::query()->where('profile', 'pay_code')->sole();
    $activation = CommercialOfferingActivation::query()->where('profile', 'pay_code')->sole();

    expect($payCode->origin)->toBe(CommercialOfferingOrigin::InstallationBaseline)
        ->and($payCode->created_by_type)->toBeNull()
        ->and($payCode->approved_by_type)->toBeNull()
        ->and($payCode->source_package)->toBe('3neti/x-change')
        ->and($activation->authority)->toBe(CommercialActivationAuthority::CommissioningManifest)
        ->and(app(CommercialOfferingResolverContract::class)->resolve('pay_code')->snapshotHash())
        ->toBe($payCode->snapshot_hash);
});

it('reuses identical baseline and activation evidence idempotently', function (): void {
    $service = app(ProvisionCommercialBaselines::class);

    $first = $service->provision('commissioning-manifest:first');
    $second = $service->provision('commissioning-manifest:second');

    expect($second[0]->is($first[0]))->toBeTrue()
        ->and(CommercialOffering::query()->count())->toBe(2)
        ->and(CommercialOfferingActivation::query()->count())->toBe(2);
});

it('persists baselines without activating them in maker checker from start mode', function (): void {
    config()->set(
        'x-change.commercial.offerings.governance_mode',
        CommercialGovernanceMode::MakerCheckerFromStart->value,
    );

    $activations = app(ProvisionCommercialBaselines::class)
        ->provision('commissioning-manifest:strict');

    expect($activations)->toBe([])
        ->and(CommercialOffering::query()->count())->toBe(2)
        ->and(CommercialOfferingActivation::query()->count())->toBe(0);
});

it('refuses to overwrite a conflicting persisted baseline', function (): void {
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:first');

    CommercialOffering::query()->where('profile', 'pay_code')->update([
        'snapshot_hash' => str_repeat('a', 64),
    ]);

    expect(fn () => app(ProvisionCommercialBaselines::class)
        ->provision('commissioning-manifest:second'))
        ->toThrow(DomainException::class, 'conflicts with its persisted snapshot');
});
