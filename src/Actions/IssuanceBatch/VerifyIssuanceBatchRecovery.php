<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\IssuanceBatch;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationProofData;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Models\MobileVerificationChallenge;

final readonly class VerifyIssuanceBatchRecovery
{
    public function __construct(private OtpChallengeGateway $otp) {}

    public function handle(IssuanceBatch $batch, string $reference, string $code): MobileVerificationChallenge
    {
        $challenge = MobileVerificationChallenge::query()
            ->where('reference', $reference)
            ->where('user_type', IssuanceBatch::class)
            ->where('user_id', (string) $batch->getKey())
            ->first();

        if ($challenge === null) {
            throw ValidationException::withMessages(['code' => 'Request a new recovery code first.']);
        }

        return Cache::lock('x-change:batch-recovery:'.$challenge->reference, 30)
            ->block(5, fn (): MobileVerificationChallenge => $this->verify($challenge, $code));
    }

    private function verify(MobileVerificationChallenge $challenge, string $code): MobileVerificationChallenge
    {
        $challenge->refresh();
        if (! in_array($challenge->status, ['pending', 'expired'], true)) {
            throw ValidationException::withMessages(['code' => 'This recovery challenge is no longer active.']);
        }
        $maxAttempts = 5;
        if ($challenge->attempts >= $maxAttempts) {
            $challenge->forceFill(['status' => 'locked'])->save();
            throw ValidationException::withMessages(['code' => 'Too many attempts. Request a new code.']);
        }
        $providerReference = trim((string) $challenge->provider_challenge_reference);
        $result = $providerReference === '' ? null : $this->otp->verify($providerReference, $code);
        if ($result === null || ! $result->ok || ! $result->proof instanceof OtpVerificationProofData) {
            $attempts = $challenge->attempts + 1;
            $challenge->forceFill([
                'attempts' => $attempts,
                'status' => $attempts >= $maxAttempts ? 'locked' : 'pending',
            ])->save();
            throw ValidationException::withMessages(['code' => 'The recovery code is invalid or expired.']);
        }
        $challenge->forceFill([
            'status' => 'verified',
            'verified_at' => now(),
            'provider_verified_at' => now(),
        ])->save();

        return $challenge;
    }
}
