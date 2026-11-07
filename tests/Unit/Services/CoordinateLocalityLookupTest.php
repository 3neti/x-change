<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Services\CoordinateLocalityLookup;

it('reads coarse locality from the existing geocoder without fetching a map', function (): void {
    Http::fake(['*' => Http::response(['results' => [['components' => ['country_code' => 'ph', 'suburb' => 'Barangay Test', 'city' => 'Manila', 'postcode' => '1000']]]])]);
    $lookup = new CoordinateLocalityLookup('https://example.test/reverse', 'test-key');
    $locality = $lookup->find(14.5, 121.0);
    expect($locality?->address1)->toBe('Barangay Test')->and($locality?->city)->toBe('Manila')
        ->and($locality?->postal_code)->toBe('1000')->and($locality?->source)->toBe('location');
    Http::assertSentCount(1);
});

it('returns no locality when the geocoder is unavailable, foreign, or incomplete', function (): void {
    Http::fakeSequence()->pushStatus(503)->push(['results' => [['components' => ['country_code' => 'us']]]])->push(['results' => [['components' => ['country_code' => 'ph', 'city' => 'Manila']]]]);
    $lookup = new CoordinateLocalityLookup('https://example.test/reverse', 'test-key');
    expect($lookup->find(14.5, 121.0))->toBeNull()
        ->and($lookup->find(14.5, 121.0))->toBeNull()
        ->and($lookup->find(14.5, 121.0))->toBeNull();
});

it('accepts a village locality and avoids storing the coordinate query at the provider', function (): void {
    Http::fake(['*' => Http::response(['results' => [['components' => ['country_code' => 'ph', 'village' => 'Barangay Sample', 'municipality' => 'Sample City', 'postcode' => '1234']]]])]);
    $lookup = new CoordinateLocalityLookup('https://example.test/reverse', 'test-key');

    expect($lookup->find(14.5, 121.0)?->address1)->toBe('Barangay Sample');
    Http::assertSent(fn ($request) => $request['no_record'] === 1);
});
