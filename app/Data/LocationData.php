<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use App\Data\GeocodedAddressData;

class LocationData extends Data
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public string $timestamp,
        public ?float $accuracy = null,
        public ?string $source = null,
        public ?GeocodedAddressData $address = null,
    ) {}

    public static function rules(): array
    {
        return [
            'latitude'   => ['required', 'numeric'],
            'longitude'  => ['required', 'numeric'],
            'timestamp'  => ['required', 'string'],
            'accuracy'   => ['nullable', 'numeric'],
            'source'     => ['nullable', 'string'],
            'address'    => ['nullable', 'array'],
            'address.name'        => ['nullable', 'string'],
            'address.street'      => ['nullable', 'string'],
            'address.city'        => ['nullable', 'string'],
            'address.region'      => ['nullable', 'string'],
            'address.postal_code' => ['nullable', 'string'],
            'address.country'     => ['nullable', 'string'],
        ];
    }
}
