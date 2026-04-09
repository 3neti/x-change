<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Contracts\Container\Container;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RedeemPayCode;
use LBHurtado\XChange\Actions\Redemption\WithdrawPayCode;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Contracts\ClaimExecutorContract;

class DefaultClaimExecutionFactory implements ClaimExecutionFactoryContract
{
    public function __construct(
        protected Container $container,
        protected RedeemPayCode $redeemExecutor,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function make(Voucher $voucher, array $payload): ClaimExecutorContract
    {
        if ($this->shouldWithdraw($voucher, $payload)) {
            /** @var ClaimExecutorContract $executor */
            $executor = $this->container->make(WithdrawPayCode::class);

            return $executor;
        }

        return $this->redeemExecutor;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function shouldWithdraw(Voucher $voucher, array $payload): bool
    {
        if (method_exists($voucher, 'canWithdraw') && $voucher->canWithdraw()) {
            return true;
        }

        if (method_exists($voucher, 'getSliceMode') && $voucher->getSliceMode() !== null) {
            return true;
        }

        return false;
    }
}
