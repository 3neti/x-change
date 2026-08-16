<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Commercial\BootstrapCommercialComponentEconomicsFactory;
use LBHurtado\XChange\Services\Commercial\BootstrapCommercialOfferingFactory;
use LBHurtado\XChange\Services\Commercial\CommercialComponentEconomicsManifestCompiler;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
});

it('compiles every catalog item into one deterministic reviewable economics artifact', function (): void {
    $offering = app(BootstrapCommercialOfferingFactory::class)->make('pay_code');
    $economics = app(BootstrapCommercialComponentEconomicsFactory::class)->make('pay_code', $offering);
    $compiler = app(CommercialComponentEconomicsManifestCompiler::class);
    $offeringManifestHash = str_repeat('a', 64);
    $first = $compiler->compile('pay_code', $offering, $offeringManifestHash, $economics);
    $second = $compiler->compile('pay_code', $offering, $offeringManifestHash, $economics);
    $parsed = $compiler->parse($first->yaml, $offering, $offeringManifestHash);

    expect($economics->components)->toHaveCount(count($offering->catalog->items))
        ->and($first->schema)->toBe(CommercialComponentEconomicsManifestCompiler::Schema)
        ->and($first->hash)->toHaveLength(64)
        ->and($first->hash)->toBe($second->hash)
        ->and($first->yaml)->toBe($second->yaml)
        ->and($parsed->hash)->toBe($first->hash)
        ->and($first->yaml)->toContain('counterparty:3neti')
        ->and($first->yaml)->toContain('agreement:commissioning:institution-3neti:v1')
        ->and($first->yaml)->toContain('designation:commissioning:3neti:v1')
        ->and($first->yaml)->not->toContain('tax_identification_number')
        ->and($first->yaml)->not->toContain('unit_price_minor');

    $kyc = collect($economics->components)->firstWhere('componentReference', 'inputs.fields.kyc');
    expect($kyc->capabilityReferences)->toBe(['identity:hyperverge']);
});

it('makes every non-zero bootstrap allocation equal its canonical catalog price', function (): void {
    $offering = app(BootstrapCommercialOfferingFactory::class)->make('pay_code');
    $economics = app(BootstrapCommercialComponentEconomicsFactory::class)->make('pay_code', $offering);

    foreach ($offering->catalog->items as $item) {
        $component = collect($economics->components)->firstWhere('componentReference', $item->reference);

        expect($component)->not->toBeNull();
        if ($item->deprecated || $item->unitPriceMinor === 0) {
            expect($component->isBillable())->toBeFalse()
                ->and($component->nonBillableReason)->not->toBeNull();

            continue;
        }

        expect($component->isBillable())->toBeTrue()
            ->and($component->allocationSchedule->rules)->toHaveCount(1)
            ->and($component->allocationSchedule->rules[0]->fixedAmountMinor)->toBe($item->unitPriceMinor);
    }
});

it('fails closed when an artifact is replayed against another Offering identity', function (): void {
    $offering = app(BootstrapCommercialOfferingFactory::class)->make('pay_code');
    $economics = app(BootstrapCommercialComponentEconomicsFactory::class)->make('pay_code', $offering);
    $compiler = app(CommercialComponentEconomicsManifestCompiler::class);
    $manifest = $compiler->compile('pay_code', $offering, str_repeat('a', 64), $economics);

    expect(fn () => $compiler->parse($manifest->yaml, $offering, str_repeat('b', 64)))
        ->toThrow(DomainException::class, 'does not match');
});
