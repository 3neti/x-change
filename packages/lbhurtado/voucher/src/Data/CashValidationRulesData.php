<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;
class CashValidationRulesData extends Data
{
    public function __construct(
        public string $secret,
        public string $mobile,
        public string $country,
        public string $location,
        public string $radius, // future: consider DistanceValueObject
    ) {}

    public static function rules(): array
    {
        return [
            'secret' => ['required', 'string', 'min:4', 'max:255'],
            'mobile' => ['required', 'regex:/^09\d{9}$/'],
            'country' => ['required', 'string', 'size:2'], // ISO 3166-1 alpha-2
            'location' => ['required', 'string', 'max:255'],
            'radius' => ['required', 'string', 'regex:/^\d+(m|km)$/'], // e.g. "1000m", "2km"
        ];
    }
}
