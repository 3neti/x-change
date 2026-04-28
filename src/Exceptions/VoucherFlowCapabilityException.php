<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\VoucherFlow\VoucherFlowCapabilitiesData;
use RuntimeException;

class VoucherFlowCapabilityException extends RuntimeException
{
    public function __construct(
        public readonly ?Voucher $voucher = null,
        public readonly ?VoucherFlowCapabilitiesData $capabilities = null,
        string $message = 'Voucher flow capability violation.',
    ) {
        parent::__construct($message);
    }
}
