<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimExecutorContract;
use LBHurtado\XChange\Contracts\RedemptionExecutionContract;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;
use Lorisleiva\Actions\Concerns\AsAction;
use Mockery\Exception\BadMethodCallException;

class RedeemPayCode implements ClaimExecutorContract
{
    use AsAction;

    public function __construct(
        protected RedemptionExecutionContract $service,
    ) {}

    public function handle(Voucher $voucher, array $payload): RedeemPayCodeResultData
    {
        if ($this->isOpenSliceVoucher($voucher)) {
            throw new \RuntimeException('Open-slice vouchers must be processed through withdrawal execution.');
        }

        return $this->service->redeem($voucher, $payload);
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
