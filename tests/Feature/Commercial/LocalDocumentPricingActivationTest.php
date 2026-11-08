<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CommercialComponentEconomicsResolverContract;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Contracts\PricingServiceContract;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Models\CommercialOfferingActivation;
use LBHurtado\XChange\Services\Commercial\LocalDocumentPricingActivation;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    $catalog = config('x-commerce.catalogs.pay_code');
    unset($catalog['items']['inputs.fields.documents']);
    $catalog['version'] = 3;
    config()->set('x-commerce.catalogs.pay_code', $catalog);
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:local-document-test');
});

it('activates an auditable local document price without changing previous pricing', function (): void {
    $before = app(CommercialOfferingResolverContract::class)->resolve('pay_code');
    $oldEconomics = app(CommercialComponentEconomicsResolverContract::class)->resolve('pay_code');
    $activation = app(LocalDocumentPricingActivation::class)->activate('sandbox-owner:document-fee-2026-11-08');
    $after = app(CommercialOfferingResolverContract::class)->resolve('pay_code');
    $newEconomics = app(CommercialComponentEconomicsResolverContract::class)->resolve('pay_code');
    $document = collect($after->catalog->items)->firstWhere('reference', 'inputs.fields.documents');
    $documentEconomics = collect($newEconomics->components)->firstWhere('componentReference', 'inputs.fields.documents');
    $instruction = validVoucherInstructions(100, overrides: ['inputs' => [
        'fields' => ['documents'], 'documents' => [['key' => 'id', 'label' => 'ID', 'accept' => ['application/pdf'], 'required' => true]],
    ]]);
    $quote = app(PricingServiceContract::class)->estimate($instruction);
    $charge = collect($quote['charges'])->firstWhere('catalog_item_reference', 'inputs.fields.documents');

    expect($activation->authority)->toBe(CommercialActivationAuthority::LocalSandbox)
        ->and($document->unitPriceMinor)->toBe(200)
        ->and($charge['price_minor'])->toBe(200)
        ->and($documentEconomics->allocationSchedule->rules[0]->category)->toBe('commercial_revenue')
        ->and($before->catalog->items)->toHaveCount(count($after->catalog->items) - 1)
        ->and(collect($newEconomics->components)->firstWhere('componentReference', 'cash.amount')->toArray())
        ->toBe(collect($oldEconomics->components)->firstWhere('componentReference', 'cash.amount')->toArray());
    $again = app(LocalDocumentPricingActivation::class)->activate('sandbox-owner:document-fee-2026-11-08');
    expect($again->id)->toBe($activation->id)
        ->and(CommercialOfferingActivation::query()->where('profile', 'pay_code')->count())->toBe(2);
});

it('rejects the local-only activation outside local or test environments', function (): void {
    app()->instance('env', 'production');
    try {
        expect(fn () => app(LocalDocumentPricingActivation::class)->activate('sandbox-owner:document-fee'))
            ->toThrow(DomainException::class);
    } finally {
        app()->instance('env', 'testing');
    }
});
