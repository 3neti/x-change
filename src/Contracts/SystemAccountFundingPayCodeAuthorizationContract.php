<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Funding\SystemAccountFundingPayCodeAuthorizationData;

interface SystemAccountFundingPayCodeAuthorizationContract
{
    public function authorize(
        SystemAccountFundingPayCodeAuthorizationData $request,
    ): void;
}
