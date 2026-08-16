<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Commercial\BootstrapCommercialOfferingFactory;
use LBHurtado\XChange\Services\Commercial\CommercialOfferingManifestCompiler;
use LBHurtado\XCommerce\Data\CommercialCatalogData;
use LBHurtado\XCommerce\Data\CommercialOfferingData;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
});

it('renders and parses a deterministic versioned Commercial Offering YAML artifact', function (): void {
    $offering = app(BootstrapCommercialOfferingFactory::class)->make('pay_code');
    $compiler = app(CommercialOfferingManifestCompiler::class);

    $first = $compiler->compile('pay_code', $offering);
    $second = $compiler->compile('pay_code', $offering);
    $parsed = $compiler->parse($first->yaml);

    expect($first->schema)->toBe(CommercialOfferingManifestCompiler::Schema)
        ->and($first->hash)->toHaveLength(64)
        ->and($first->yaml)->toBe($second->yaml)
        ->and($first->hash)->toBe($second->hash)
        ->and($parsed->hash)->toBe($first->hash)
        ->and($parsed->offering->snapshotHash())->toBe($offering->snapshotHash());
});

it('changes manifest identity when one governed price changes', function (): void {
    $offering = app(BootstrapCommercialOfferingFactory::class)->make('pay_code');
    $catalog = $offering->catalog->toArray();
    foreach ($catalog['items'] as $index => $item) {
        if ($item['reference'] === 'inputs.fields.selfie') {
            $catalog['items'][$index]['unit_price_minor']++;
        }
    }
    $changed = new CommercialOfferingData(
        reference: $offering->reference,
        version: $offering->version + 1,
        catalog: CommercialCatalogData::fromArray($catalog),
        waterfallPolicy: $offering->waterfallPolicy,
        attributionPolicy: $offering->attributionPolicy,
        legalTrace: $offering->legalTrace,
        effectiveAt: $offering->effectiveAt,
    );
    $compiler = app(CommercialOfferingManifestCompiler::class);

    expect($compiler->compile('pay_code', $changed)->hash)
        ->not->toBe($compiler->compile('pay_code', $offering)->hash);
});

it('rejects an unsupported or incomplete artifact', function (): void {
    expect(fn () => app(CommercialOfferingManifestCompiler::class)->parse(<<<'YAML'
schema: obsolete.schema
profile: pay_code
offering: { }
YAML))
        ->toThrow(DomainException::class, 'unsupported schema');
});
