<?php

declare(strict_types=1);

use LBHurtado\XChange\Enums\QrArtifactKind;
use LBHurtado\XChange\Services\QrArtifactFactory;

it('builds direct and manual-entry Pay Code QR artifacts from canonical routes', function (): void {
    $factory = app(QrArtifactFactory::class);
    $direct = $factory->payCode('ABCD', 'https://example.test/x/claim/ABCD');
    $entry = $factory->claimEntry('ABCD');

    expect($direct->kind)->toBe(QrArtifactKind::PayCode)
        ->and($direct->destination)->toBe('https://example.test/x/claim/ABCD')
        ->and($direct->identifier)->toBe('ABCD')
        ->and($direct->image_data_uri)->toStartWith('data:image/png;base64,')
        ->and($entry->kind)->toBe(QrArtifactKind::ClaimEntry)
        ->and($entry->destination)->toBe(route('x-change.claim.start'))
        ->and($entry->identifier)->toBe('ABCD')
        ->and($entry->image_data_uri)->toStartWith('data:image/png;base64,');
});

it('builds a campaign endpoint artifact without pretending it is an issued Pay Code', function (): void {
    $artifact = app(QrArtifactFactory::class)->campaignEndpoint(
        'Public assistance',
        'https://example.test/x/o/merchant/public-assistance',
    );

    expect($artifact->kind)->toBe(QrArtifactKind::CampaignEndpoint)
        ->and($artifact->identifier)->toBe('Public assistance')
        ->and($artifact->description)->toContain('fresh Pay Code');
});

it('rejects empty artifact identifiers', function (): void {
    expect(fn () => app(QrArtifactFactory::class)->payCode(' ', 'https://example.test/x/claim/ABCD'))
        ->toThrow(InvalidArgumentException::class);
});
