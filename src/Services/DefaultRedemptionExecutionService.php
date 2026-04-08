<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RedemptionContextResolverContract;
use LBHurtado\XChange\Contracts\RedemptionExecutionContract;
use LBHurtado\XChange\Contracts\RedemptionProcessorContract;
use LBHurtado\XChange\Contracts\RedemptionValidationContract;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;
use RuntimeException;

class DefaultRedemptionExecutionService implements RedemptionExecutionContract
{
    public function __construct(
        protected RedemptionContextResolverContract $contextResolver,
        protected RedemptionValidationContract $validator,
        protected RedemptionProcessorContract $processor,
    ) {}

    public function redeem(Voucher $voucher, array $payload): RedeemPayCodeResultData
    {
        $mobile = data_get($payload, 'mobile');
        $country = (string) data_get($payload, 'recipient_country', 'PH');

        if (! is_string($mobile) || trim($mobile) === '') {
            throw new RuntimeException('Mobile number is required.');
        }

        /** @var array<string, mixed> $inputs */
        $inputs = (array) data_get($payload, 'inputs', []);

        /** @var array<string, mixed> $bankAccount */
        $bankAccount = (array) data_get($payload, 'bank_account', []);

        $context = $this->contextResolver->resolve($payload);

        $this->validator->validate($voucher, $context);

        $redeemed = $this->processor->process($voucher, $context);

        if (! $redeemed) {
            throw new RuntimeException('Failed to redeem voucher.');
        }

        return new RedeemPayCodeResultData(
            voucher_code: (string) $voucher->code,
            redeemed: true,
            status: 'redeemed',
            redeemer: [
                'mobile' => $mobile,
                'country' => $country,
            ],
            bank_account: $bankAccount,
            inputs: $inputs,
            disbursement: [
                'status' => 'requested',
            ],
            messages: ['Voucher redeemed successfully.'],
        );
    }
}
