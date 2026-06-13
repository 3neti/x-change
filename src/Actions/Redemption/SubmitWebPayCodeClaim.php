<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Support\Claim\UseDeferredPaynamicsOtpResolver;

final class SubmitWebPayCodeClaim
{
    public function __construct(
        private readonly SubmitPayCodeClaim $submitPayCodeClaim,
        private readonly UseDeferredPaynamicsOtpResolver $deferredOtpResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Voucher $voucher, array $payload): SubmitPayCodeClaimResultData|ClaimApprovalInitiationResultData
    {
        return $this->deferredOtpResolver->run(
            fn () => $this->submitPayCodeClaim->handle($voucher, $payload)
        );
    }
}
