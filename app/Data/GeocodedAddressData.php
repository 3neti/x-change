<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class GeocodedAddressData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $street = null,
        public ?string $city = null,
        public ?string $region = null,
        public ?string $postal_code = null,
        public ?string $country = null,
    ) {}

    public static function rules(): array
    {
        return [
            'name'        => ['nullable', 'string'],
            'street'      => ['nullable', 'string'],
            'city'        => ['nullable', 'string'],
            'region'      => ['nullable', 'string'],
            'postal_code' => ['nullable', 'string'],
            'country'     => ['nullable', 'string'],
        ];
    }
}
