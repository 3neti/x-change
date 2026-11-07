<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use LBHurtado\EmiCore\Contracts\BankRegistryContract;
use LBHurtado\EmiCore\Contracts\PhilippineLocalityDataset;
use LBHurtado\EmiCore\Data\PayoutPartyData;
use LBHurtado\EmiCore\Services\PayoutLocalityResolver;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Gateway;
use LBHurtado\XChange\Services\ClaimPayoutLocalityEvidence;
use LBHurtado\XChange\Services\CoordinateLocalityLookup;
use LBHurtado\XChange\Services\FallbackPhilippineLocalityDataset;

it('uses the existing location handler geocoder when payout-specific settings are absent', function (): void {
    config()->set('x-change.payout_locality.coordinate_endpoint', '');
    config()->set('x-change.payout_locality.coordinate_api_key', '');
    config()->set('location-handler.opencage_endpoint', 'https://geocoder.test/reverse');
    config()->set('location-handler.opencage_api_key', 'test-key');
    Http::fake(['geocoder.test/*' => Http::response(['results' => [['components' => ['country_code' => 'ph', 'suburb' => 'Barangay Test', 'city' => 'Manila', 'postcode' => '1000']]]])]);

    expect(app(CoordinateLocalityLookup::class)->find(14.5, 121.0)?->city)->toBe('Manila');
    Http::assertSentCount(1);
});

it('does not mix a partial payout override with location handler credentials', function (): void {
    config()->set('x-change.payout_locality.coordinate_endpoint', 'https://override.test/reverse');
    config()->set('x-change.payout_locality.coordinate_api_key', '');
    config()->set('location-handler.opencage_endpoint', 'https://geocoder.test/reverse');
    config()->set('location-handler.opencage_api_key', 'test-key');
    Http::fake();

    expect(app(CoordinateLocalityLookup::class)->find(14.5, 121.0))->toBeNull();
    Http::assertNothingSent();
});

it('prefers a complete payout geocoder override', function (): void {
    config()->set('x-change.payout_locality.coordinate_endpoint', 'https://override.test/reverse');
    config()->set('x-change.payout_locality.coordinate_api_key', 'override-key');
    config()->set('location-handler.opencage_endpoint', 'https://geocoder.test/reverse');
    config()->set('location-handler.opencage_api_key', 'other-key');
    Http::fake(['override.test/*' => Http::response(['results' => [['components' => ['country_code' => 'ph', 'suburb' => 'Barangay Test', 'city' => 'Manila', 'postcode' => '1000']]]])]);

    expect(app(CoordinateLocalityLookup::class)->find(14.5, 121.0)?->source)->toBe('location');
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://override.test/') && $request['key'] === 'override-key');
});

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
        ->and($address->dataset_version)->toBe('x-change-fallback-v2');
});

it('supplies multiple usable barangay candidates without any configured dataset', function (): void {
    config()->set('x-change.payout_locality.dataset_path', null);
    $dataset = app(PhilippineLocalityDataset::class);
    $candidates = $dataset->candidates();

    expect($dataset)->toBeInstanceOf(FallbackPhilippineLocalityDataset::class)
        ->and(count($candidates))->toBeGreaterThan(1);
    foreach ($candidates as $candidate) {
        expect($candidate->address1)->toStartWith('Barangay ')->not->toContain('N/A')
            ->and($candidate->city)->not->toBeEmpty()
            ->and($candidate->country)->toBe('PH')
            ->and($candidate->postal_code)->toMatch('/^\d{4}$/');
    }
});

it('selects different synthetic fallback candidates using the injected random selector', function (): void {
    $dataset = new FallbackPhilippineLocalityDataset;
    $first = (new PayoutLocalityResolver($dataset, fn (int $count): int => 0))->resolve();
    $last = (new PayoutLocalityResolver($dataset, fn (int $count): int => $count - 1))->resolve();

    expect($first->address1)->not->toBe($last->address1)
        ->and($first->source)->toBe('synthetic')
        ->and($last->source)->toBe('synthetic');
});

it('carries the unconfigured synthetic fallback through to both NetBank payload parties', function (): void {
    config()->set('x-change.payout_locality.dataset_path', null);
    $dataset = app(PhilippineLocalityDataset::class);
    $sender = new PayoutPartyData('sender', 'Issuer', (new PayoutLocalityResolver($dataset, fn (int $count): int => 0))->resolve());
    $recipient = new PayoutPartyData('recipient', 'Recipient', (new PayoutLocalityResolver($dataset, fn (int $count): int => $count - 1))->resolve());
    $banks = Mockery::mock(BankRegistryContract::class);
    $banks->shouldReceive('supportsRail')->andReturnTrue();
    app()->instance(BankRegistryContract::class, $banks);
    $gateway = new Gateway;
    $gateway->setRails(['INSTAPAY' => ['enabled' => true]]);
    $payload = $gateway->disburse([
        'amount' => 10000, 'accountNumber' => '09170000000', 'bankCode' => 'GXCHPHM2XXX',
        'reference' => 'synthetic-locality-test', 'via' => 'INSTAPAY',
        'senderParty' => $sender->toArray(), 'recipientParty' => $recipient->toArray(),
    ])->getData();

    expect(data_get($payload, 'sender.address.address1'))->toBe($sender->address->address1)->toStartWith('Barangay ')
        ->and(data_get($payload, 'recipient.address.address1'))->toBe($recipient->address->address1)->toStartWith('Barangay ')
        ->and($sender->address->source)->toBe('synthetic')
        ->and($recipient->address->source)->toBe('synthetic');
});

it('can build the request-bound evidence service without external configuration', function (): void {
    expect(app(ClaimPayoutLocalityEvidence::class))->toBeInstanceOf(ClaimPayoutLocalityEvidence::class);
});
