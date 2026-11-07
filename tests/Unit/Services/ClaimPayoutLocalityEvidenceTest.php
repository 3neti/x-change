<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use LBHurtado\XChange\Services\ClaimPayoutLocalityEvidence;
use LBHurtado\XChange\Services\CoordinateLocalityLookup;
use LBHurtado\XChange\Services\GeoLiteLocalityLookup;

it('uses the trusted request client IP and keeps only coarse locality', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'geolite-');
    $reader = new class
    {
        public array $ips = [];

        public function get(string $ip): array
        {
            $this->ips[] = $ip;

            return ['country' => ['iso_code' => 'PH'], 'city' => ['names' => ['en' => 'Manila']]];
        }

        public function close(): void {}
    };
    try {
        $request = Request::create('/', 'POST', server: ['REMOTE_ADDR' => '8.8.8.8', 'HTTP_X_FORWARDED_FOR' => '1.1.1.1']);
        $service = new ClaimPayoutLocalityEvidence(new GeoLiteLocalityLookup($path, 30, fn () => $reader), new CoordinateLocalityLookup('', ''));
        $evidence = $service->capture($request, [], false);
        expect($reader->ips)->toBe(['8.8.8.8'])->and($evidence)->toBe(['ip_locality' => ['city' => 'Manila', 'region' => null], 'captured' => null])
            ->and(json_encode($evidence))->not->toContain('8.8.8.8')->not->toContain('1.1.1.1');
    } finally {
        unlink($path);
    }
});

it('only looks up coordinates from explicitly permitted location evidence', function (): void {
    Http::fake(['*' => Http::response(['results' => [['components' => ['country_code' => 'ph', 'suburb' => 'Barangay Test', 'city' => 'Manila', 'postcode' => '1000']]]])]);
    $service = new ClaimPayoutLocalityEvidence(new GeoLiteLocalityLookup('/missing'), new CoordinateLocalityLookup('https://example.test/reverse', 'key'));
    $request = Request::create('/', 'POST');
    $location = ['latitude' => 14.5, 'longitude' => 121.0];
    expect($service->capture($request, $location, false)['captured'])->toBeNull();
    Http::assertNothingSent();
    $captured = $service->capture($request, $location, true)['captured'];
    expect($captured['city'])->toBe('Manila')->and($captured['source'])->toBe('location')
        ->and(json_encode($captured))->not->toContain('14.5')->not->toContain('121.0');
    Http::assertSentCount(1);
});
