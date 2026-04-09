<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimExecutorContract;
use LBHurtado\XChange\Contracts\WithdrawalExecutionContract;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use Lorisleiva\Actions\Concerns\AsAction;

class WithdrawPayCode implements ClaimExecutorContract
{
    use AsAction;

    public function __construct(
        protected WithdrawalExecutionContract $service,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Voucher $voucher, array $payload): WithdrawPayCodeResultData
    {
        return $this->service->withdraw($voucher, $payload);
    }
}
