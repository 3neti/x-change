<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\XChange\Contracts\RedemptionContextResolverContract;

class DefaultRedemptionContextResolverService implements RedemptionContextResolverContract
{
    public function resolve(array $payload): RedemptionContext
    {
        $mobile = (string) ($payload['mobile'] ?? $payload['phone'] ?? $payload['phone_number'] ?? '');
        $secret = $payload['secret'] ?? null;

        /** @var array<string, mixed> $inputs */
        $inputs = (array) ($payload['inputs'] ?? []);

        /** @var array<string, mixed> $bankAccount */
        $bankAccount = (array) ($payload['bank_account'] ?? []);

        return new RedemptionContext(
            mobile: $mobile,
            secret: $secret,
            vendorAlias: null,
            inputs: $inputs,
            bankAccount: $bankAccount,
        );
    }
}
