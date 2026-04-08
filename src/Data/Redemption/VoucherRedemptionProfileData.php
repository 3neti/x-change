<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Redemption;

use Spatie\LaravelData\Data;

class VoucherRedemptionProfileData extends Data
{
    public function __construct(
        public string $instrument_kind,
        public string $redemption_mode,
        public bool $requires_form_flow,
        public bool $is_divisible,
        public bool $can_withdraw,
        public ?string $slice_mode,
        public ?string $driver_name,
    ) {}
}
