<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Tests\Fakes;

use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeData;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeRequestData;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationProofData;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationResultData;

final class FakeOtpChallengeGateway implements OtpChallengeGateway
{
    public ?OtpChallengeRequestData $request = null;

    public string $expectedCode = '000000';

    public ?string $proofReference = null;

    public ?string $proofPurpose = null;

    public function create(OtpChallengeRequestData $request): OtpChallengeData
    {
        $this->request = $request;

        return new OtpChallengeData(
            reference: 'otp-'.$request->client_reference,
            status: 'pending',
            expires_in: 600,
        );
    }

    public function status(string $challengeReference): OtpChallengeData
    {
        return new OtpChallengeData(
            reference: $challengeReference,
            status: 'pending',
            expires_in: 600,
        );
    }

    public function resend(string $challengeReference): OtpChallengeData
    {
        return $this->status($challengeReference);
    }

    public function verify(string $challengeReference, string $code): OtpVerificationResultData
    {
        if (! hash_equals($this->expectedCode, $code)) {
            return new OtpVerificationResultData(
                ok: false,
                reason: 'invalid_code',
                attempts: 1,
                status: 'pending',
            );
        }

        return new OtpVerificationResultData(
            ok: true,
            reason: 'verified',
            proof: new OtpVerificationProofData(
                reference: $this->proofReference ?? $challengeReference,
                purpose: $this->proofPurpose ?? 'onboarding.account',
                verified_at: now()->toIso8601String(),
            ),
            attempts: 1,
            status: 'verified',
        );
    }
}
