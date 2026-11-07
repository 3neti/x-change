<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Http\Request;

final readonly class ClaimPayoutLocalityEvidence
{
    public function __construct(
        private GeoLiteLocalityLookup $ipLookup,
        private CoordinateLocalityLookup $coordinateLookup,
    ) {}

    /**
     * @param  array<string, mixed>  $location
     * @return array{ip_locality: array{city: string, region: ?string}|null, captured: array<string, mixed>|null}
     */
    public function capture(Request $request, array $location, bool $coordinatesPermitted): array
    {
        $captured = null;
        if ($coordinatesPermitted && is_numeric($location['latitude'] ?? null) && is_numeric($location['longitude'] ?? null)) {
            $captured = $this->coordinateLookup->find((float) $location['latitude'], (float) $location['longitude'])?->toArray();
        }

        return ['ip_locality' => $this->ipLookup->find($request->ip()), 'captured' => $captured];
    }
}
