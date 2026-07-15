<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffResultData;

interface ExecutionResultCockpitActivityHandoffContract
{
    public function handoff(ExecutionResultData $result, ExecutionContextData $context): ExecutionResultHandoffResultData;
}
