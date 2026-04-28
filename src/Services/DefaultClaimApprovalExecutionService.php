<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\ClaimApprovalExecutionContract;
use LBHurtado\XChange\Contracts\ClaimApprovalWorkflowStoreContract;
use LBHurtado\XChange\Contracts\ClaimOtpVerificationContract;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use RuntimeException;

class DefaultClaimApprovalExecutionService implements ClaimApprovalExecutionContract
{
    public function __construct(
        protected ClaimApprovalWorkflowStoreContract $store,
        protected SubmitPayCodeClaim $submitClaim,
        protected ClaimOtpVerificationContract $otpVerification,
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

        $replayPayload = array_replace_recursive(
            (array) ($workflow['payload'] ?? []),
            $payload,
            [
                'approval' => [
                    'resume' => true,
                    'approved' => true,
                    'approved_at' => now()->toIso8601String(),
                ],
            ],
        );

        $result = $this->submitClaim->handle($voucher, $replayPayload);

        $this->store->forget($voucher);

        return $result;
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

        if (! $this->otpVerification->verify($voucher, $otp, $workflow)) {
            throw new RuntimeException('OTP verification failed.');
        }

        $replayPayload = array_replace_recursive(
            (array) ($workflow['payload'] ?? []),
            $payload,
            [
                'approval' => [
                    'resume' => true,
                ],
                'otp' => [
                    'otp_code' => $otp,
                    'verified' => true,
                    'verified_at' => now()->toIso8601String(),
                ],
            ],
        );

        $result = $this->submitClaim->handle($voucher, $replayPayload);

        $this->store->forget($voucher);

        return $result;
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
