<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use Lorisleiva\Actions\Concerns\AsAction;
use Mockery\Exception\BadMethodCallException;

class SubmitPayCodeClaim
{
    use AsAction;

    public function __construct(
        protected ClaimExecutionFactoryContract $factory,
        protected RecordVoucherClaim $recordVoucherClaim,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Voucher $voucher, array $payload): SubmitPayCodeClaimResultData
    {
        $executor = $this->factory->make($voucher, $payload);

        $result = $executor->handle($voucher, $payload);

        //        $normalized = $this->normalizeResult($result, $payload);
        $normalized = $this->normalizeResult($voucher, $result, $payload);

        $this->recordVoucherClaim->handle($voucher, $normalized, $payload);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function normalizeResult(Voucher $voucher, mixed $result, array $payload): SubmitPayCodeClaimResultData
    {
        if ($result instanceof RedeemPayCodeResultData) {
            $isDivisible = $this->safeBoolMethod($voucher, 'isDivisible');

            $remainingBalance = null;

            if ($isDivisible) {
                $resolvedRemaining = $this->safeCall($voucher, 'getRemainingBalance');

                if ($resolvedRemaining !== null) {
                    $remainingBalance = (float) $resolvedRemaining;
                }
            }

            return new SubmitPayCodeClaimResultData(
                voucher_code: $result->voucher_code,
                claim_type: 'redeem',
                claimed: $result->redeemed,
                status: $result->status,
                requested_amount: null,
                disbursed_amount: null,
                currency: null,
                remaining_balance: $remainingBalance,
                fully_claimed: ! $isDivisible,
                disbursement: $result->disbursement,
                messages: $result->messages,
            );
        }

        if ($result instanceof WithdrawPayCodeResultData) {
            return new SubmitPayCodeClaimResultData(
                voucher_code: $result->voucher_code,
                claim_type: 'withdraw',
                claimed: $result->withdrawn,
                status: $result->status,
                requested_amount: $result->requested_amount,
                disbursed_amount: $result->disbursed_amount,
                currency: $result->currency,
                remaining_balance: $result->remaining_balance,
                fully_claimed: (float) ($result->remaining_balance ?? 0) <= 0,
                disbursement: $result->disbursement,
                messages: $result->messages,
            );
        }

        throw new \RuntimeException('Unsupported claim execution result type: '.get_debug_type($result));
    }

    protected function toFloatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
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
