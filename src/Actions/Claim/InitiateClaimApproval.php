<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimApprovalInitiationContract;
use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;

final class InitiateClaimApproval
{
    public function __construct(
        private readonly ClaimApprovalInitiationContract $approval,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $approval
     */
    public function handle(Voucher $voucher, array $payload, array $approval): ClaimApprovalInitiationResultData
    {
        return $this->approval->initiate($voucher, $payload, $approval);
    }
}
