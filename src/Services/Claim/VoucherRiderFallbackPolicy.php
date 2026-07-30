<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

final class VoucherRiderFallbackPolicy
{
    /**
     * @param  array<string, mixed>  $instructions
     */
    public function shouldResolve(array $instructions): bool
    {
        if (data_get($instructions, 'execution.driver') !== 'onboarding_account_provisioning') {
            return true;
        }

        $rider = (array) data_get($instructions, 'rider', []);

        return filled(data_get($rider, 'message'))
            || filled(data_get($rider, 'url'))
            || filled(data_get($rider, 'splash'))
            || (array) data_get($rider, 'stages', []) !== [];
    }
}
