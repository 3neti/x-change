<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Redemption;

use Spatie\LaravelData\Data;

class RedemptionRequirementsData extends Data
{
    /**
     * @param  array<int, string>  $required_inputs
     * @param  array<string, mixed>  $required_validation
     */
    public function __construct(
        public array $required_inputs,
        public array $required_validation,
        public bool $has_kyc,
        public bool $has_otp,
        public bool $has_location,
        public bool $has_selfie,
        public bool $has_signature,
        public bool $has_bio_fields,
    ) {}
}
