<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts\Execution;

use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\XChange\Data\Execution\StoredValueHolderAuthorityData;

interface StoredValueHolderAuthorityContract
{
    public function isReady(): bool;

    public function authorize(ExecutionContextData $context): StoredValueHolderAuthorityData;
}
