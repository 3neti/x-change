<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use Illuminate\Validation\ValidationException;

class ClaimFlowStateGuard
{
    /**
     * @param  array<string, mixed>  $state
     */
    public function assertBelongsToVoucher(array $state, string $voucherCode, ?string $referenceId = null): void
    {
        $voucherCode = strtoupper(trim($voucherCode));
        $stateVoucherCode = data_get($state, 'instructions.metadata.voucher_code');

        if (is_string($stateVoucherCode) && ! hash_equals($voucherCode, strtoupper(trim($stateVoucherCode)))) {
            $this->reject();
        }

        if ($referenceId === null || ! str_starts_with($referenceId, 'claim-')) {
            return;
        }

        if (! str_starts_with(strtoupper($referenceId), "CLAIM-{$voucherCode}-")) {
            $this->reject();
        }
    }

    protected function reject(): never
    {
        throw ValidationException::withMessages([
            'error' => 'This claim session does not belong to this Pay Code. Please restart the claim.',
        ]);
    }
}
