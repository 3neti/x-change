<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use LBHurtado\XChange\Data\ApprovalWorkflowResultData;

interface ApprovalWorkflowContract
{
    public function resolve(
        WithdrawPayCodeResultData $result,
        array $context = [],
    ): ApprovalWorkflowResultData;
}
