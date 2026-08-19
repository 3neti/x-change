<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Redemption;

use LBHurtado\XChange\Models\VoucherSliceExecution;

final readonly class VoucherSliceReservationData
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public array $payload,
        public VoucherSliceExecution $execution,
        public bool $replayed,
    ) {
    }
}
