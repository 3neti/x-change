<?php

declare(strict_types=1);

it('ships payout-destination icon metadata whose assets all exist on disk', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $metadataPath = $packageRoot.'/resources/documents/payout-destination-icons.json';
    $assetRoot = $packageRoot.'/resources/assets/images/payout-destinations';

    expect($metadataPath)->toBeFile();

    $metadata = json_decode(
        file_get_contents($metadataPath) ?: '',
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($metadata)->toHaveKey('entries')
        ->and($metadata['entries'])->not->toBeEmpty();

    foreach ($metadata['entries'] as $code => $entry) {
        expect($entry)->toHaveKeys([
            'code', 'slug', 'display_name', 'kind', 'rails', 'assets',
            'source_url', 'source_type', 'confidence', 'needs_legal_review',
            'license_notes', 'retrieved_at',
        ])
            ->and($entry['code'])->toBe($code)
            ->and($entry['assets'])->not->toBeEmpty()
            ->and(in_array($entry['confidence'], ['high', 'medium', 'low'], true))->toBeTrue()
            ->and(in_array($entry['kind'], ['rail', 'provider', 'orchestrator', 'emi', 'bank'], true))->toBeTrue();

        foreach ($entry['assets'] as $format => $filename) {
            $assetPath = $assetRoot.'/'.$filename;

            expect($assetPath)->toBeFile("Missing asset for {$code} ({$format}): {$assetPath}");

            if ($format === 'svg') {
                $svg = file_get_contents($assetPath);

                expect($svg)->not->toBeFalse()
                    ->toContain('<svg')
                    ->not->toContain('<script')
                    ->not->toMatch('/(?:href|src)=["\']https?:\/\//i');
            }
        }
    }
});

it('flags every third-party bank/EMI/provider mark for legal review while keeping x-change\'s own mark exempt', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $metadataPath = $packageRoot.'/resources/documents/payout-destination-icons.json';
    $metadata = json_decode(
        file_get_contents($metadataPath) ?: '',
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($metadata['entries']['ORCHESTRATOR:XCHANGE']['needs_legal_review'])->toBeFalse();

    foreach ($metadata['entries'] as $code => $entry) {
        if ($code === 'ORCHESTRATOR:XCHANGE') {
            continue;
        }

        expect($entry['needs_legal_review'])->toBeTrue("{$code} should be flagged for legal review");
    }
});
