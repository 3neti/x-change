<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RedemptionExecutionContract;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;
use Lorisleiva\Actions\Concerns\AsAction;

class RedeemPayCode
{
    use AsAction;

    public function __construct(
        protected RedemptionExecutionContract $service,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Voucher $voucher, array $payload): RedeemPayCodeResultData
    {
        return $this->service->redeem($voucher, $payload);
    }
}
