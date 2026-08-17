<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Redemption;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\DispatchVoucherClaimOutcome;
use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\Claim\VoucherClaimPolicyResolver;
use LBHurtado\XChange\Support\Claim\UseDeferredPaynamicsOtpResolver;
use RuntimeException;

class SubmitWebPayCodeClaim
{
    public function __construct(
        private readonly SubmitPayCodeClaim $submitPayCodeClaim,
        private readonly UseDeferredPaynamicsOtpResolver $deferredOtpResolver,
        private readonly DispatchVoucherClaimOutcome $claimOutcomes,
        private readonly VoucherClaimPolicyResolver $claimPolicies,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Voucher $voucher, array $payload): SubmitPayCodeClaimResultData|ClaimApprovalInitiationResultData
    {
        if ($this->claimPolicies->resolve($voucher)->defaultOutcome === 'account_funding') {
            $claim = $this->claimOutcomes->handle(
                voucher: $voucher,
                requestedOutcome: 'account_funding',
                payload: $payload,
                claimant: $this->auth->guard()->user(),
            );

            if (! $claim instanceof VoucherClaim) {
                throw new RuntimeException('The Account Funding claim returned an unexpected result.');
            }

            return new SubmitPayCodeClaimResultData(
                voucher_code: (string) $voucher->code,
                claim_type: $claim->claim_type,
                claimed: $claim->isSuccessful(),
                status: $claim->status,
                requested_amount: $claim->requested_amount,
                disbursed_amount: $claim->disbursed_amount,
                currency: $claim->currency,
                remaining_balance: $claim->remaining_balance,
                fully_claimed: $claim->isSuccessful(),
                disbursement: null,
                messages: ['Funds were added to your Account.'],
            );
        }

        return $this->deferredOtpResolver->run(
            fn () => $this->submitPayCodeClaim->handle($voucher, $payload)
        );
    }
}
