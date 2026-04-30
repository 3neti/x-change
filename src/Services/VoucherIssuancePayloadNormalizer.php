<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;

class VoucherIssuancePayloadNormalizer
{
    public function normalize(array $input): array
    {
        $flowType = Arr::get($input, 'metadata.flow_type');

        if ($flowType !== 'collectible') {
            return $input;
        }

        $targetAmount = Arr::get($input, 'target_amount')
            ?? Arr::get($input, 'cash.target_amount')
            ?? Arr::get($input, 'cash.amount')
            ?? Arr::get($input, 'amount');

        if ($targetAmount !== null) {
            Arr::set($input, 'target_amount', $targetAmount);
        }

        Arr::set($input, 'cash.amount', 0);

        return $input;
    }
}
