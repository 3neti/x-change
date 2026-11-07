<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\PhilippineLocalityDataset;
use LBHurtado\EmiCore\Services\PayoutLocalityResolver;
use LBHurtado\XChange\Services\ClaimPayoutLocalityEvidence;

it('resolves an injectable configured Philippine locality dataset', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'locality-');
    file_put_contents($path, json_encode([
        ['address1' => 'Barangay Sample', 'city' => 'Sample City', 'region' => 'Sample Region', 'postal_code' => '1234'],
    ], JSON_THROW_ON_ERROR));
    try {
        config()->set('x-change.payout_locality.dataset_path', $path);
        $dataset = app(PhilippineLocalityDataset::class);
        expect($dataset->candidates('Sample City', 'Sample Region'))->toHaveCount(1)
            ->and(app(PayoutLocalityResolver::class)->resolve(ipCity: 'Sample City', ipRegion: 'Sample Region')->source)->toBe('ip_assisted_synthetic');
    } finally {
        unlink($path);
    }
});

it('uses a bounded synthetic fallback when the configured dataset is unavailable', function (): void {
    config()->set('x-change.payout_locality.dataset_path', '/no/such/locality-dataset.json');
    $address = app(PayoutLocalityResolver::class)->resolve();
    expect($address->source)->toBe('synthetic')->and($address->country)->toBe('PH')
        ->and($address->dataset_version)->toBe('x-change-fallback-v1');
});

it('can build the request-bound evidence service without external configuration', function (): void {
    expect(app(ClaimPayoutLocalityEvidence::class))->toBeInstanceOf(ClaimPayoutLocalityEvidence::class);
});
