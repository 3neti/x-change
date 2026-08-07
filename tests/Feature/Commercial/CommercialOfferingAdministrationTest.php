<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use LBHurtado\XChange\Actions\Commercial\ManageCommercialOffering;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Services\Commercial\BootstrapCommercialOfferingFactory;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XCommerce\Data\CommercialOfferingData;

function grantCommercialCapability(User $operator, CommercialOperatorCapability $capability): void
{
    CommercialOperatorAuthorization::query()->create([
        'operator_type' => $operator->getMorphClass(),
        'operator_id' => $operator->getKey(),
        'capability' => $capability->value,
        'authorization_reference' => 'test-authorization:'.$capability->value.':'.$operator->getKey(),
        'valid_from' => now()->subMinute(),
    ]);
}

function commercialOfferingVersion(int $version, string $effectiveAt): CommercialOfferingData
{
    $bootstrap = app(BootstrapCommercialOfferingFactory::class)->make('pay_code');

    return new CommercialOfferingData(
        reference: $bootstrap->reference,
        version: $version,
        catalog: $bootstrap->catalog,
        waterfallPolicy: $bootstrap->waterfallPolicy,
        attributionPolicy: $bootstrap->attributionPolicy,
        legalTrace: $bootstrap->legalTrace,
        effectiveAt: $effectiveAt,
    );
}

it('uses package bootstrap configuration until published offerings are activated', function (): void {
    $resolved = app(CommercialOfferingResolverContract::class)->resolve('pay_code');

    expect($resolved->reference)->toBe('commercial-offering:pay_code')
        ->and($resolved->catalog->reference)->toBe('pay-code')
        ->and($resolved->waterfallPolicy->reference)->toBe('pay-code-commercial-waterfall')
        ->and($resolved->legalTrace->jurisdiction)->toBe('PH');
});

it('fails closed when x-legal enforcement is required but the package is unavailable', function (): void {
    $maker = actingAsTestUser();
    grantCommercialCapability($maker, CommercialOperatorCapability::ManageOfferings);
    config()->set('x-change.commercial.legal.enforcement', 'required');

    expect(fn () => app(ManageCommercialOffering::class)->createDraft(
        $maker,
        'pay_code',
        commercialOfferingVersion(1, now()->toIso8601String()),
    ))->toThrow(DomainException::class, 'x-legal is required');

    expect(CommercialOffering::query()->count())->toBe(0);
});

it('publishes an immutable offering through distinct maker and checker authority', function (): void {
    $maker = actingAsTestUser();
    $checker = actingAsTestUser();
    grantCommercialCapability($maker, CommercialOperatorCapability::ManageOfferings);
    grantCommercialCapability($checker, CommercialOperatorCapability::ApproveOfferings);

    $action = app(ManageCommercialOffering::class);
    $draft = $action->createDraft(
        $maker,
        'pay_code',
        commercialOfferingVersion(1, now()->subMinute()->toIso8601String()),
    );
    $submitted = $action->submit($maker, $draft);
    $published = $action->publish($checker, $submitted, 'board-resolution:2026-08-07:pricing-v1');

    expect($published->status)->toBe(CommercialOfferingStatus::Published)
        ->and($published->snapshot_hash)->toBe($published->offering()->snapshotHash())
        ->and($published->created_by_id)->toBe($maker->getKey())
        ->and($published->approved_by_id)->toBe($checker->getKey())
        ->and($published->authorization_reference)->toBe('board-resolution:2026-08-07:pricing-v1');

    config()->set('x-change.commercial.offerings.use_published', true);

    expect(app(CommercialOfferingResolverContract::class)->resolve('pay_code')->snapshotHash())
        ->toBe($published->snapshot_hash);
});

it('retires the prior offering when a later approved version becomes effective', function (): void {
    $firstMaker = actingAsTestUser();
    $secondMaker = actingAsTestUser();
    $checker = actingAsTestUser();

    foreach ([$firstMaker, $secondMaker] as $maker) {
        grantCommercialCapability($maker, CommercialOperatorCapability::ManageOfferings);
    }
    grantCommercialCapability($checker, CommercialOperatorCapability::ApproveOfferings);

    $action = app(ManageCommercialOffering::class);
    $first = $action->publish(
        $checker,
        $action->submit($firstMaker, $action->createDraft(
            $firstMaker,
            'pay_code',
            commercialOfferingVersion(1, now()->subMinutes(2)->toIso8601String()),
        )),
        'pricing-approval:v1',
    );
    $second = $action->publish(
        $checker,
        $action->submit($secondMaker, $action->createDraft(
            $secondMaker,
            'pay_code',
            commercialOfferingVersion(2, now()->subMinute()->toIso8601String()),
        )),
        'pricing-approval:v2',
    );

    expect($first->refresh()->status)->toBe(CommercialOfferingStatus::Retired)
        ->and($second->status)->toBe(CommercialOfferingStatus::Published)
        ->and(CommercialOffering::query()->where('status', CommercialOfferingStatus::Published->value)->count())
        ->toBe(1);
});

it('fails closed for unauthorized operators and same-person approval', function (): void {
    $maker = actingAsTestUser();
    $action = app(ManageCommercialOffering::class);

    expect(fn () => $action->createDraft(
        $maker,
        'pay_code',
        commercialOfferingVersion(1, now()->toIso8601String()),
    ))->toThrow(AuthorizationException::class);

    grantCommercialCapability($maker, CommercialOperatorCapability::ManageOfferings);
    grantCommercialCapability($maker, CommercialOperatorCapability::ApproveOfferings);

    $submitted = $action->submit($maker, $action->createDraft(
        $maker,
        'pay_code',
        commercialOfferingVersion(1, now()->toIso8601String()),
    ));

    expect(fn () => $action->publish($maker, $submitted, 'self-approval'))
        ->toThrow(DomainException::class, 'checker must be different');
});
