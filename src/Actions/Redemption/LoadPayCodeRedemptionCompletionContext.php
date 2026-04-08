<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RedemptionCompletionContextContract;
use LBHurtado\XChange\Data\Redemption\LoadRedemptionCompletionContextResultData;
use Lorisleiva\Actions\Concerns\AsAction;

class LoadPayCodeRedemptionCompletionContext
{
    use AsAction;

    public function __construct(
        protected RedemptionCompletionContextContract $service,
    ) {}

    public function handle(
        Voucher $voucher,
        ?string $referenceId = null,
        ?string $flowId = null,
    ): LoadRedemptionCompletionContextResultData {
        return $this->service->load($voucher, $referenceId, $flowId);
    }
}
