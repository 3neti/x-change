<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RedemptionFlowPreparationContract;
use LBHurtado\XChange\Contracts\SettlementFlowPreparationContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Data\Redemption\PrepareRedemptionResultData;
use LBHurtado\XChange\Data\Settlement\PrepareSettlementResultData;
use Lorisleiva\Actions\Concerns\AsAction;

class PreparePayCodeRedemptionFlow
{
    use AsAction;

    public function __construct(
        protected RedemptionFlowPreparationContract $service,
        protected SettlementFlowPreparationContract $settlementService,
        protected VoucherFlowCapabilityResolverContract $flowResolver,
    ) {}

    public function handle(Voucher $voucher): PrepareRedemptionResultData|PrepareSettlementResultData
    {
        $capabilities = $this->flowResolver->resolve($voucher);

        if ($capabilities->type->isSettlement()) {
            return $this->settlementService->prepare($voucher);
        }

        return $this->service->prepare($voucher);
    }
}
