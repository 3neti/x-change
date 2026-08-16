<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Services\Commercial\BootstrapCommercialOfferingFactory;
use LBHurtado\XChange\Services\PricelistService;

it('projects the public price list exclusively from the active Commercial Offering', function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    config()->set('x-change.pricing.components.selfie', 999.99);
    $offering = app(BootstrapCommercialOfferingFactory::class)->make('pay_code');
    $resolver = Mockery::mock(CommercialOfferingResolverContract::class);
    $resolver->shouldReceive('resolve')->with('pay_code')->andReturn($offering);
    $service = new PricelistService($resolver);

    $pricelist = $service->showPricelist();
    $selfie = collect($pricelist['items'])->firstWhere('code', 'inputs.fields.selfie');

    expect($selfie['amount_minor'])->toBe(300)
        ->and($selfie['amount'])->toBe(3)
        ->and($pricelist['commercial_offering'])->toBe([
            'reference' => $offering->reference,
            'version' => $offering->version,
            'snapshot_hash' => $offering->snapshotHash(),
            'effective_at' => $offering->effectiveAt,
        ])
        ->and($pricelist['catalog'])->toBe([
            'reference' => $offering->catalog->reference,
            'version' => $offering->catalog->version,
        ]);
});
