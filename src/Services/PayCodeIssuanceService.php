<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;

class PayCodeIssuanceService implements PayCodeIssuanceContract
{
    public function issue(mixed $issuer, array $input): array
    {
        $instructions = VoucherInstructionsData::from($input);

        $issued = GenerateVouchers::run($instructions)->first();

        if (! $issued) {
            throw new PayCodeIssuanceFailed('Pay Code issuance did not return a voucher.');
        }

        return [
            'voucher_id' => $issued->id,
            'code' => $issued->code,
            'amount' => data_get($instructions, 'cash.amount'),
            'currency' => data_get($instructions, 'cash.currency'),
        ];
    }
}
