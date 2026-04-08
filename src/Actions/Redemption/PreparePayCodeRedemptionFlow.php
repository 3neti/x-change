<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RedemptionFlowPreparationContract;
use LBHurtado\XChange\Data\Redemption\PrepareRedemptionResultData;
use Lorisleiva\Actions\Concerns\AsAction;

class PreparePayCodeRedemptionFlow
{
    use AsAction;

    public function __construct(
        protected RedemptionFlowPreparationContract $service,
    ) {}

    public function handle(Voucher $voucher): PrepareRedemptionResultData
    {
        return $this->service->prepare($voucher);
    }
}
