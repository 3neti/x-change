<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use LBHurtado\XChange\Data\PayCode\PayCodeOperationalStatusData;
use Spatie\LaravelData\Data;

class ClaimSurfaceStateData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $can_claim,
        public readonly bool $terminal,
    ) {}

    /**
     * Maps the existing voucher operational status resolver output onto the
     * claim-surface state shape. This intentionally does not add any new
     * state logic -- see `DefaultVoucherOperationalStatusResolver`.
     */
    public static function fromOperationalStatus(PayCodeOperationalStatusData $status): self
    {
        return new self(
            key: $status->key,
            label: $status->label,
            can_claim: $status->can_claim,
            terminal: $status->is_terminal,
        );
    }
}
