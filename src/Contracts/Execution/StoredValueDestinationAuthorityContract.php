<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts\Execution;

use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\XChange\Data\Execution\StoredValueDestinationAuthorityData;

interface StoredValueDestinationAuthorityContract
{
    public function isReady(): bool;

    public function authorize(
        ExecutionContextData $context,
        int $amountMinor,
    ): StoredValueDestinationAuthorityData;
}
