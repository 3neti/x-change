<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;
use Carbon\CarbonInterval;

class VoucherInstructionsData extends Data
{
    public function __construct(
        public CashInstructionData $cash,
        public InputFieldsData $inputs,
        public FeedbackInstructionData $feedback,
        public RiderInstructionData $rider,
        public ?int $count = 1,                         // Number of vouchers to generate
        public ?string $prefix = null,                  // Prefix for voucher codes
        public ?string $mask = null,                    // Mask for voucher codes
        public CarbonInterval|string|null $ttl = null,  // Expiry time (TTL)
    ) {}
}
