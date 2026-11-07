<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Facades\Http;
use LBHurtado\EmiCore\Data\PayoutAddressData;
use Throwable;

final readonly class CoordinateLocalityLookup
{
    public function __construct(private string $endpoint, private string $apiKey) {}

    public function find(float $latitude, float $longitude): ?PayoutAddressData
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 || $this->endpoint === '' || $this->apiKey === '') {
            return null;
        }
        try {
            $response = Http::acceptJson()->connectTimeout(1)->timeout(2)->get($this->endpoint, [
                'q' => $latitude.','.$longitude, 'key' => $this->apiKey, 'no_annotations' => 1, 'limit' => 1,
            ]);
            if (! $response->successful()) {
                return null;
            }
            $components = $response->json('results.0.components', []);
            $address = $components['suburb'] ?? $components['neighbourhood'] ?? null;
            $city = $components['city'] ?? $components['town'] ?? $components['municipality'] ?? null;
            $zip = $components['postcode'] ?? null;
            if (strtolower((string) ($components['country_code'] ?? '')) !== 'ph'
                || ! is_string($address) || trim($address) === '' || ! is_string($city) || trim($city) === ''
                || ! is_string($zip) || ! preg_match('/\A[0-9]{4}\z/', $zip)) {
                return null;
            }

            return new PayoutAddressData(trim($address), trim($city), 'PH', $zip, 'location');
        } catch (Throwable) {
            return null;
        }
    }
}
