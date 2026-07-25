<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\DispatchVoucherClaimOutcome;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Funding\PayCodeFundingInspectionStore;
use RuntimeException;

final readonly class ClaimPayCodeIntoAccount
{
    public function __construct(
        private PayCodeFundingInspectionStore $inspections,
        private DispatchVoucherClaimOutcome $outcomes,
    ) {}

    public function handle(
        string $inspectionToken,
        Authenticatable&Model $claimant,
    ): VoucherClaim {
        $voucher = $this->inspections->resolve($inspectionToken, $claimant);

        if (! $voucher instanceof Voucher) {
            throw new RuntimeException(
                'The Pay Code inspection expired. Check the Pay Code again.',
            );
        }

        $claim = $this->outcomes->handle(
            voucher: $voucher,
            requestedOutcome: 'account_funding',
            payload: [],
            claimant: $claimant,
        );

        if (! $claim instanceof VoucherClaim) {
            throw new RuntimeException(
                'The Account Funding claim returned an unexpected result.',
            );
        }

        $this->inspections->forget($inspectionToken);

        return $claim;
    }
}
