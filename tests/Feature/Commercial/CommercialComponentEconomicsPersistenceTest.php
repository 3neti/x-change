<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CommercialComponentEconomicsResolverContract;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Models\CommercialComponentEconomics;
use LBHurtado\XChange\Models\CommercialComponentEconomicsActivation;
use LBHurtado\XChange\Models\CommercialComponentEconomicsHead;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Services\Commercial\ActivateCommercialComponentEconomics;
use LBHurtado\XChange\Services\Commercial\ActivateCommercialOffering;
use LBHurtado\XChange\Services\Commercial\BootstrapCommercialComponentEconomicsFactory;
use LBHurtado\XChange\Services\Commercial\CommercialComponentEconomicsManifestCompiler;
use LBHurtado\XChange\Services\Commercial\CommercialOfferingManifestCompiler;
use LBHurtado\XChange\Services\Commercial\PersistCommercialComponentEconomicsManifest;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XCommerce\Data\CommercialOfferingData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
});

it('persists and activates complete economics bound to every commissioned offering', function (): void {
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:component-economics');

    expect(CommercialComponentEconomics::query()->count())->toBe(2)
        ->and(CommercialComponentEconomicsActivation::query()->count())->toBe(2)
        ->and(CommercialComponentEconomicsHead::query()->count())->toBe(2);

    $manifest = CommercialComponentEconomics::query()->where('profile', 'pay_code')->sole();
    $head = CommercialComponentEconomicsHead::query()
        ->with('currentActivation.economics')
        ->whereKey('pay_code')
        ->sole();
    $resolved = app(CommercialComponentEconomicsResolverContract::class)->resolve('pay_code');

    expect($manifest->commercial_offering_id)->toBe($manifest->offering->getKey())
        ->and($manifest->offering_snapshot_hash)->toBe($manifest->offering->snapshot_hash)
        ->and($manifest->snapshot_hash)->toBe($resolved->snapshotHash())
        ->and($manifest->artifact_yaml)->not->toContain('unit_price_minor')
        ->and($head->currentActivation?->economics?->is($manifest))->toBeTrue()
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'commercial.component_economics.baseline_provisioned')
            ->count())->toBe(2)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'commercial.component_economics.activated')
            ->count())->toBe(2);
});

it('replays identical commissioning without duplicating manifests activations heads or journals', function (): void {
    $service = app(ProvisionCommercialBaselines::class);
    $service->provision('commissioning-manifest:first');
    $service->provision('commissioning-manifest:second');

    expect(CommercialComponentEconomics::query()->count())->toBe(2)
        ->and(CommercialComponentEconomicsActivation::query()->count())->toBe(2)
        ->and(CommercialComponentEconomicsHead::query()->count())->toBe(2)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'commercial.component_economics.baseline_provisioned')
            ->count())->toBe(2)
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'commercial.component_economics.activated')
            ->count())->toBe(2);
});

it('keeps activation history append only while moving the locked profile head', function (): void {
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:history');
    $baseline = CommercialComponentEconomics::query()->where('profile', 'pay_code')->sole();
    $firstActivation = CommercialComponentEconomicsActivation::query()
        ->where('profile', 'pay_code')
        ->sole();
    $offeringData = CommercialOfferingData::fromArray([
        ...$baseline->offering->offering()->toArray(),
        'version' => 2,
    ]);
    $offeringManifest = app(CommercialOfferingManifestCompiler::class)->compile('pay_code', $offeringData);
    $offeringRevision = CommercialOffering::query()->create([
        'reference' => $offeringData->reference,
        'version' => $offeringData->version,
        'profile' => 'pay_code',
        'status' => 'published',
        'origin' => CommercialOfferingOrigin::MakerCheckerRevision,
        'currency' => $offeringData->catalog->currency,
        'snapshot_hash' => $offeringData->snapshotHash(),
        'snapshot' => $offeringData->toArray(),
        'manifest_schema' => $offeringManifest->schema,
        'manifest_hash' => $offeringManifest->hash,
        'manifest_yaml' => $offeringManifest->yaml,
        'authorization_reference' => 'approval:offering:v2',
        'effective_at' => $offeringData->effectiveAt,
    ]);
    app(ActivateCommercialOffering::class)->execute(
        $offeringRevision,
        CommercialActivationAuthority::IndependentApproval,
        'commercial-offering-activation:pay-code:v2',
    );
    $economicsData = app(BootstrapCommercialComponentEconomicsFactory::class)
        ->make('pay_code', $offeringData);
    $economicsManifest = app(CommercialComponentEconomicsManifestCompiler::class)->compile(
        'pay_code',
        $offeringData,
        $offeringManifest->hash,
        $economicsData,
    );
    $revision = app(PersistCommercialComponentEconomicsManifest::class)->execute(
        offering: $offeringRevision,
        manifest: $economicsManifest,
        reference: 'component-economics:pay_code',
        version: 2,
        origin: CommercialOfferingOrigin::MakerCheckerRevision,
        authority: CommercialActivationAuthority::IndependentApproval,
    );

    $secondActivation = app(ActivateCommercialComponentEconomics::class)->execute(
        $revision,
        CommercialActivationAuthority::IndependentApproval,
        'component-economics-activation:pay-code:v2',
        authorizationReference: 'approval:component-economics:v2',
    );

    expect($secondActivation->previous_activation_id)->toBe($firstActivation->getKey())
        ->and(CommercialComponentEconomicsActivation::query()->count())->toBe(3)
        ->and(CommercialComponentEconomicsHead::query()
            ->whereKey('pay_code')
            ->value('current_activation_id'))->toBe($secondActivation->getKey())
        ->and($firstActivation->refresh()->getAttributes())->not->toHaveKey('deactivated_at')
        ->and(CommercialComponentEconomics::query()->whereKey($baseline->getKey())->exists())->toBeTrue();

    expect(fn () => app(ActivateCommercialComponentEconomics::class)->execute(
        $revision,
        CommercialActivationAuthority::CommissioningManifest,
        'component-economics-activation:pay-code:v2',
    ))->toThrow(DomainException::class, 'conflicts with prior evidence');
});

it('fails closed when active component economics evidence is tampered', function (): void {
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:tamper');
    CommercialComponentEconomics::query()
        ->where('profile', 'pay_code')
        ->update(['snapshot_hash' => str_repeat('a', 64)]);

    expect(fn () => app(CommercialComponentEconomicsResolverContract::class)->resolve('pay_code'))
        ->toThrow(DomainException::class, 'evidence is inconsistent');
});
