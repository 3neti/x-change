<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\VoucherFlow;

use LBHurtado\XChange\Enums\VoucherFlowType;
use Spatie\LaravelData\Data;

class VoucherFlowCapabilitiesData extends Data
{
    public function __construct(
        public VoucherFlowType $type,
        public string $label,
        public string $direction,
        public bool $can_disburse,
        public bool $can_collect,
        public bool $can_settle,
        public bool $supports_open_slices,
        public bool $supports_delegated_spend,
        public bool $requires_envelope,
        public string $pay_code_route,
        public string $qr_type,
    ) {}
}
