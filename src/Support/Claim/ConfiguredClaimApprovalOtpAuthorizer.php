<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer;
use LBHurtado\XChange\Contracts\ClaimOtpVerificationContract;

final class ConfiguredClaimApprovalOtpAuthorizer implements ClaimApprovalOtpAuthorizer
{
    public function authorize(Voucher $voucher, array $payload): array
    {
        $driver = (string) config('x-change.claim_approval.otp.driver', 'null');

        $verifier = $this->resolveVerifier($driver);

        $code = (string) ($payload['otp'] ?? $payload['otp_code'] ?? '');

        $verified = $verifier->verify($voucher, $code, $payload);

        $provider = $payload['provider'] ?? null;
        $referenceId = $payload['reference_id'] ?? null;

        $message = $verified
            ? 'Approval OTP verified.'
            : 'Approval OTP received.';

        return [
            'status' => $verified ? 'completed' : 'received',
            'voucher_code' => (string) $voucher->code,
            'reference_id' => $referenceId,
            'provider' => $provider,
            'messages' => [
                $message,
            ],
            'approval_metadata' => [
                'provider' => $provider,
                'authorization_type' => 'otp',
                'reference_id' => $referenceId,
                'expires_at' => null,
                'otp_required' => ! $verified,
                'polling_required' => false,
                'manual_review' => false,
                'message' => $message,
            ],
        ];
    }

    private function resolveVerifier(string $driver): ClaimOtpVerificationContract
    {
        $verifier = config("x-change.claim_approval.otp.drivers.{$driver}.verify");

        if (! $verifier) {
            $verifier = config('x-change.claim_approval.otp.drivers.null.verify');
        }

        return app($verifier);
    }
}
