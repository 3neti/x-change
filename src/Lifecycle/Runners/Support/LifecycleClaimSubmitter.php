<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners\Support;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Lifecycle\Runners\ScenarioRunContext;
use LBHurtado\XChange\Support\Claim\UseDeferredPaynamicsOtpResolver;

final class LifecycleClaimSubmitter
{
    public function __construct(
        private readonly SubmitPayCodeClaim $submitPayCodeClaim,
        private readonly UseDeferredPaynamicsOtpResolver $deferredOtpResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(
        ScenarioRunContext $context,
        Voucher $voucher,
        array $payload,
    ): SubmitPayCodeClaimResultData|ClaimApprovalInitiationResultData {
        if ($this->shouldDeferApproval($context)) {
            return $this->deferredOtpResolver->run(
                fn () => $this->submitPayCodeClaim->handle($voucher, $payload)
            );
        }

        return $this->submitPayCodeClaim->handle($voucher, $payload);
    }

    private function shouldDeferApproval(ScenarioRunContext $context): bool
    {
        return $context->wantsJson();
    }
}
