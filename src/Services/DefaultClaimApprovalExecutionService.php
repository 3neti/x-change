<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use RuntimeException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\ClaimApprovalExecutionContract;
use LBHurtado\XChange\Contracts\ClaimApprovalWorkflowStoreContract;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;

class DefaultClaimApprovalExecutionService implements ClaimApprovalExecutionContract
{
    public function __construct(
        protected ClaimApprovalWorkflowStoreContract $store,
        protected SubmitPayCodeClaim $submitClaim,
    ) {}

    public function approve(Voucher $voucher, array $payload): SubmitPayCodeClaimResultData
    {
        $workflow = $this->requireWorkflow($voucher);

        if (($workflow['status'] ?? null) !== 'pending') {
            throw new RuntimeException('Claim approval workflow is not pending.');
        }

        if (! in_array('manual_approval', (array) ($workflow['requirements'] ?? []), true)) {
            throw new RuntimeException('Manual approval is not required for this claim.');
        }

        $this->store->forget($voucher);

        return $this->submitClaim->handle($voucher, array_replace_recursive(
            (array) ($workflow['payload'] ?? []),
            $payload,
            [
                'approval' => [
                    'approved' => true,
                    'approved_at' => now()->toIso8601String(),
                ],
            ],
        ));
    }

    public function verifyOtp(Voucher $voucher, array $payload): SubmitPayCodeClaimResultData
    {
        $workflow = $this->requireWorkflow($voucher);

        if (($workflow['status'] ?? null) !== 'pending') {
            throw new RuntimeException('Claim approval workflow is not pending.');
        }

        if (! in_array('otp', (array) ($workflow['requirements'] ?? []), true)) {
            throw new RuntimeException('OTP approval is not required for this claim.');
        }

        $otp = (string) data_get($payload, 'otp');

        if ($otp === '') {
            throw new RuntimeException('OTP is required.');
        }

        $this->store->forget($voucher);

        return $this->submitClaim->handle($voucher, array_replace_recursive(
            (array) ($workflow['payload'] ?? []),
            $payload,
            [
                'otp' => [
                    'otp_code' => $otp,
                    'verified' => true,
                    'verified_at' => now()->toIso8601String(),
                ],
            ],
        ));
    }

    protected function requireWorkflow(Voucher $voucher): array
    {
        $workflow = $this->store->get($voucher);

        if (! $workflow) {
            throw new RuntimeException('No pending claim approval workflow found.');
        }

        return $workflow;
    }
}
