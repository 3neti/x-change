<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Contracts\Container\Container;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RedeemPayCode;
use LBHurtado\XChange\Actions\Redemption\WithdrawPayCode;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Contracts\ClaimExecutorContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Data\VoucherFlow\VoucherFlowCapabilitiesData;
use Mockery\Exception\BadMethodCallException;
use RuntimeException;

class DefaultClaimExecutionFactory implements ClaimExecutionFactoryContract
{
    public function __construct(
        protected Container $container,
        protected RedeemPayCode $redeemExecutor,
        protected VoucherFlowCapabilityResolverContract $flowResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function make(Voucher $voucher, array $payload): ClaimExecutorContract
    {
        $capabilities = $this->flowResolver->resolve($voucher);

        if (! $capabilities->can_disburse) {
            throw new RuntimeException(
                "Voucher flow [{$capabilities->type->value}] cannot execute outward claims."
            );
        }

        if ($this->shouldWithdraw($voucher, $payload, $capabilities)) {
            /** @var ClaimExecutorContract $executor */
            $executor = $this->container->make(WithdrawPayCode::class);

            return $executor;
        }

        return $this->redeemExecutor;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function shouldWithdraw(
        Voucher $voucher,
        array $payload,
        VoucherFlowCapabilitiesData $capabilities,
    ): bool {
        if (! $capabilities->can_disburse) {
            return false;
        }

        if ($this->isOpenSliceVoucher($voucher)) {
            return true;
        }

        $isRedeemed = method_exists($voucher, 'isRedeemed')
            ? (bool) $this->safeCall($voucher, 'isRedeemed', false)
            : $voucher->redeemed_at !== null;

        if (! $isRedeemed) {
            return false;
        }

        if (method_exists($voucher, 'canWithdraw')) {
            return (bool) $this->safeCall($voucher, 'canWithdraw', false);
        }

        return false;
    }

    protected function isOpenSliceVoucher(Voucher $voucher): bool
    {
        return $this->safeBoolMethod($voucher, 'isDivisible')
            && $this->safeCall($voucher, 'getSliceMode') === 'open';
    }

    protected function safeBoolMethod(object $target, string $method, bool $default = false): bool
    {
        return (bool) $this->safeCall($target, $method, $default);
    }

    protected function safeCall(object $target, string $method, mixed $default = null): mixed
    {
        if (! method_exists($target, $method)) {
            return $default;
        }

        try {
            return $target->{$method}();
        } catch (BadMethodCallException) {
            return $default;
        }
    }
}
