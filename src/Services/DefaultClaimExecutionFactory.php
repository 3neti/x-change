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
        $isRedeemed = method_exists($voucher, 'isRedeemed')
            ? (bool) $voucher->isRedeemed()
            : $voucher->redeemed_at !== null;

        if (! $isRedeemed) {
            return false;
        }

        if (method_exists($voucher, 'canWithdraw')) {
            return (bool) $voucher->canWithdraw();
        }

        return false;
    }
}
