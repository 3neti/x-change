<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Actions\Commercial\ManageCommercialOffering;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Services\Commercial\ActivateCommercialOffering;
use LBHurtado\XChange\Services\Commercial\BackfillCommercialOfferingManifests;
use LBHurtado\XChange\Services\Commercial\BootstrapCommercialOfferingFactory;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Tests\Fakes\User;
use LBHurtado\XCommerce\Data\CommercialOfferingData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
});

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
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:test');

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
        ->and($published->manifest_schema)->toBe('3neti.x-change.commercial-offering-manifest.v1')
        ->and($published->manifest_hash)->toHaveLength(64)
        ->and($published->manifest_yaml)->toContain('schema: 3neti.x-change.commercial-offering-manifest.v1')
        ->and($published->created_by_id)->toBe($maker->getKey())
        ->and($published->approved_by_id)->toBe($checker->getKey())
        ->and($published->authorization_reference)->toBe('board-resolution:2026-08-07:pricing-v1');

    app(ActivateCommercialOffering::class)->execute(
        $published,
        CommercialActivationAuthority::IndependentApproval,
        'commercial-activation:pricing-v1',
    );

    expect(app(CommercialOfferingResolverContract::class)->resolve('pay_code')->snapshotHash())
        ->toBe($published->snapshot_hash)
        ->and(ExecutionJournalEntry::query()
            ->whereIn('event_type', [
                'commercial.offering.drafted',
                'commercial.offering.submitted',
                'commercial.offering.published',
                'commercial.offering.activated',
            ])->count())->toBe(4);
});

it('locks the latest offering row without combining a lock with an aggregate query', function (): void {
    $maker = actingAsTestUser();
    grantCommercialCapability($maker, CommercialOperatorCapability::ManageOfferings);
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:test');

    DB::flushQueryLog();
    DB::enableQueryLog();

    app(ManageCommercialOffering::class)->createDraft(
        $maker,
        'pay_code',
        commercialOfferingVersion(2, now()->subMinute()->toIso8601String()),
    );

    $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");

    expect($queries)
        ->toContain('order by "version" desc')
        ->not->toContain('max("version")');
});

it('retires the prior active offering only when a later published version is activated', function (): void {
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

    expect($first->refresh()->status)->toBe(CommercialOfferingStatus::Published)
        ->and($second->status)->toBe(CommercialOfferingStatus::Published)
        ->and(CommercialOffering::query()->where('status', CommercialOfferingStatus::Published->value)->count())
        ->toBe(2);

    $activation = app(ActivateCommercialOffering::class);
    $activation->execute(
        $first,
        CommercialActivationAuthority::IndependentApproval,
        'commercial-activation:v1',
    );
    $activation->execute(
        $second,
        CommercialActivationAuthority::IndependentApproval,
        'commercial-activation:v2',
    );

    expect($first->refresh()->status)->toBe(CommercialOfferingStatus::Retired)
        ->and($second->refresh()->status)->toBe(CommercialOfferingStatus::Published);
});

it('fails closed when a governed profile has no active offering', function (): void {
    expect(fn () => app(CommercialOfferingResolverContract::class)->resolve('pay_code'))
        ->toThrow(DomainException::class, 'has no active governed version');
});

it('refuses to activate an Offering without frozen manifest evidence', function (): void {
    $maker = actingAsTestUser();
    $checker = actingAsTestUser();
    grantCommercialCapability($maker, CommercialOperatorCapability::ManageOfferings);
    grantCommercialCapability($checker, CommercialOperatorCapability::ApproveOfferings);
    $action = app(ManageCommercialOffering::class);
    $published = $action->publish(
        $checker,
        $action->submit($maker, $action->createDraft(
            $maker,
            'pay_code',
            commercialOfferingVersion(1, now()->subMinute()->toIso8601String()),
        )),
        'pricing-approval:missing-manifest',
    );
    $published->forceFill([
        'manifest_schema' => null,
        'manifest_hash' => null,
        'manifest_yaml' => null,
    ])->save();

    expect(fn () => app(ActivateCommercialOffering::class)->execute(
        $published,
        CommercialActivationAuthority::IndependentApproval,
        'commercial-activation:missing-manifest',
    ))->toThrow(DomainException::class, 'requires frozen manifest evidence');
});

it('backfills legacy Offering manifests without changing governed commercial authority', function (): void {
    $maker = actingAsTestUser();
    $checker = actingAsTestUser();
    grantCommercialCapability($maker, CommercialOperatorCapability::ManageOfferings);
    grantCommercialCapability($checker, CommercialOperatorCapability::ApproveOfferings);
    $action = app(ManageCommercialOffering::class);
    $published = $action->publish(
        $checker,
        $action->submit($maker, $action->createDraft(
            $maker,
            'pay_code',
            commercialOfferingVersion(1, now()->subMinute()->toIso8601String()),
        )),
        'pricing-approval:legacy-manifest',
    );
    $activation = app(ActivateCommercialOffering::class)->execute(
        $published,
        CommercialActivationAuthority::IndependentApproval,
        'commercial-activation:legacy-manifest',
    );
    $authority = $published->only([
        'snapshot_hash',
        'snapshot',
        'status',
        'approved_by_type',
        'approved_by_id',
        'authorization_reference',
    ]);
    $effectiveAt = $published->effective_at?->toIso8601String();

    $published->forceFill([
        'manifest_schema' => null,
        'manifest_hash' => null,
        'manifest_yaml' => null,
    ])->save();

    $backfill = app(BackfillCommercialOfferingManifests::class);

    expect($backfill->execute())->toBe(1)
        ->and($backfill->execute())->toBe(0);

    $published->refresh();

    expect($published->only(array_keys($authority)))->toBe($authority)
        ->and($published->effective_at?->toIso8601String())->toBe($effectiveAt)
        ->and($published->manifest_schema)->toBe('3neti.x-change.commercial-offering-manifest.v1')
        ->and($published->manifest_hash)->toHaveLength(64)
        ->and($published->manifest_yaml)->toContain('schema: 3neti.x-change.commercial-offering-manifest.v1')
        ->and($activation->refresh()->deactivated_at)->toBeNull();
});

it('fails closed when legacy Offering evidence is partial or conflicts with its snapshot', function (): void {
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:legacy-conflict');
    $offering = CommercialOffering::query()->where('profile', 'pay_code')->sole();
    $offering->forceFill([
        'manifest_schema' => '3neti.x-change.commercial-offering-manifest.v1',
        'manifest_hash' => null,
        'manifest_yaml' => null,
    ])->save();

    expect(fn () => app(BackfillCommercialOfferingManifests::class)->execute())
        ->toThrow(DomainException::class, 'has incomplete manifest evidence');

    $offering->forceFill([
        'manifest_schema' => null,
        'snapshot_hash' => str_repeat('a', 64),
    ])->save();

    expect(fn () => app(BackfillCommercialOfferingManifests::class)->execute())
        ->toThrow(DomainException::class, 'conflicts with its persisted snapshot')
        ->and($offering->refresh()->manifest_hash)->toBeNull()
        ->and($offering->manifest_yaml)->toBeNull();
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
