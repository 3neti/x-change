<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use Lorisleiva\Actions\Concerns\AsAction;

class SubmitPayCodeClaim
{
    use AsAction;

    public function __construct(
        protected ClaimExecutionFactoryContract $factory,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Voucher $voucher, array $payload): SubmitPayCodeClaimResultData
    {
        $executor = $this->factory->make($voucher, $payload);

        $result = $executor->handle($voucher, $payload);

        return $this->normalizeResult($result, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function normalizeResult(mixed $result, array $payload): SubmitPayCodeClaimResultData
    {
        if ($result instanceof RedeemPayCodeResultData) {
            return new SubmitPayCodeClaimResultData(
                voucher_code: $result->voucher_code,
                claim_type: 'redeem',
                claimed: $result->redeemed,
                status: $result->status,
                requested_amount: $this->toFloatOrNull(data_get($payload, 'amount')),
                disbursed_amount: null,
                currency: null,
                remaining_balance: null,
                fully_claimed: true,
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
}
