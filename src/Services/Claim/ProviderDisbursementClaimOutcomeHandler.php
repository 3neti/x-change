<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Contracts\Auth\Authenticatable;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\VoucherClaimOutcomeHandlerContract;

final readonly class ProviderDisbursementClaimOutcomeHandler implements VoucherClaimOutcomeHandlerContract
{
    public function __construct(
        private SubmitPayCodeClaim $claims,
    ) {}

    public function key(): string
    {
        return 'provider_disbursement';
    }

    public function execute(
        Voucher $voucher,
        array $payload,
        ?Authenticatable $claimant = null,
    ): mixed {
        return $this->claims->handle($voucher, $payload);
    }
}
