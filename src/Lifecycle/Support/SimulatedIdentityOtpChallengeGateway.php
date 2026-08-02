<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Support;

use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeData;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeRequestData;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationProofData;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationResultData;

final class SimulatedIdentityOtpChallengeGateway implements OtpChallengeGateway
{
    /** @var array<string, string> */
    private array $purposes = [];

    public function create(OtpChallengeRequestData $request): OtpChallengeData
    {
        $reference = 'simulation-'.$request->client_reference;
        $this->purposes[$reference] = $request->purpose;

        return new OtpChallengeData(
            reference: $reference,
            status: 'pending',
            expires_in: 600,
        );
    }

    public function status(string $challengeReference): OtpChallengeData
    {
        return new OtpChallengeData(
            reference: $challengeReference,
            status: isset($this->purposes[$challengeReference]) ? 'pending' : 'missing',
            expires_in: 600,
        );
    }

    public function resend(string $challengeReference): OtpChallengeData
    {
        return $this->status($challengeReference);
    }

    public function verify(string $challengeReference, string $code): OtpVerificationResultData
    {
        $purpose = $this->purposes[$challengeReference] ?? null;

        if ($purpose === null || ! hash_equals('000000', $code)) {
            return new OtpVerificationResultData(
                ok: false,
                reason: 'invalid_code',
                status: 'pending',
            );
        }

        return new OtpVerificationResultData(
            ok: true,
            reason: 'verified',
            proof: new OtpVerificationProofData(
                reference: $challengeReference,
                purpose: $purpose,
                verified_at: now()->toIso8601String(),
            ),
            status: 'verified',
        );
    }
}
