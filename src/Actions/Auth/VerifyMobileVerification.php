<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationProofData;
use LBHurtado\XChange\Contracts\AccountProvisioningContract;
use LBHurtado\XChange\Models\MobileVerificationChallenge;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use LogicException;
use Throwable;

final class VerifyMobileVerification
{
    public function __construct(
        private readonly OtpChallengeGateway $otp,
        private readonly AccountProvisioningContract $accounts,
    ) {}

    public function handle(Model $user, string $code): MobileVerificationChallenge
    {
        $challenge = MobileVerificationChallenge::query()
            ->where('user_type', $user::class)
            ->where('user_id', (string) $user->getKey())
            ->whereIn('status', ['pending', 'expired'])
            ->latest('id')
            ->first();

        if ($challenge === null) {
            throw ValidationException::withMessages([
                'code' => 'Request a new verification code first.',
            ]);
        }

        return Cache::lock('x-change:mobile-verification:'.$challenge->reference, 30)
            ->block(5, fn (): MobileVerificationChallenge => $this->verify($user, $challenge, $code));
    }

    private function verify(
        Model $user,
        MobileVerificationChallenge $challenge,
        string $code,
    ): MobileVerificationChallenge {
        $challenge->refresh();

        if (! in_array($challenge->status, ['pending', 'expired'], true)) {
            throw ValidationException::withMessages([
                'code' => 'This verification challenge is no longer active.',
            ]);
        }

        $maxAttempts = (int) config('x-change.onboarding.mobile_verification.max_attempts', 5);

        if ($challenge->attempts >= $maxAttempts) {
            $challenge->forceFill(['status' => 'locked'])->save();

            throw ValidationException::withMessages([
                'code' => 'Too many verification attempts. Request a new code.',
            ]);
        }

        $mobile = MobileNumber::normalize(
            is_string($user->getRawOriginal('mobile'))
                ? $user->getRawOriginal('mobile')
                : null,
        );

        if ($mobile === null || ! hash_equals($challenge->mobile_hash, $this->mobileHash($mobile))) {
            $challenge->forceFill(['status' => 'identity_changed'])->save();

            throw ValidationException::withMessages([
                'code' => 'The mobile identity changed. Request a new code.',
            ]);
        }

        $providerReference = trim((string) $challenge->provider_challenge_reference);

        if ($providerReference === '') {
            $challenge->forceFill(['status' => 'delivery_failed'])->save();

            throw ValidationException::withMessages([
                'code' => 'This verification challenge is incomplete. Request a new code.',
            ]);
        }

        $result = $this->otp->verify($providerReference, $code);

        if (! $result->ok || ! $result->proof instanceof OtpVerificationProofData) {
            $attempts = $challenge->attempts + 1;
            $expired = $result->reason === 'expired';
            $challenge->forceFill([
                'attempts' => $attempts,
                'status' => $expired
                    ? 'expired'
                    : ($attempts >= $maxAttempts ? 'locked' : 'pending'),
            ])->save();

            throw ValidationException::withMessages([
                'code' => $expired
                    ? 'This verification code has expired.'
                    : 'The verification code is invalid.',
            ]);
        }

        $providerVerifiedAt = $this->validatedProof($challenge, $result->proof);

        return DB::transaction(function () use ($user, $challenge, $providerVerifiedAt): MobileVerificationChallenge {
            $lockedChallenge = MobileVerificationChallenge::query()
                ->lockForUpdate()
                ->findOrFail($challenge->getKey());
            $lockedUser = $user->newQuery()
                ->lockForUpdate()
                ->findOrFail($user->getKey());

            if (! in_array($lockedChallenge->status, ['pending', 'expired'], true)) {
                throw ValidationException::withMessages([
                    'code' => 'This verification challenge is no longer active.',
                ]);
            }

            $lockedChallenge->forceFill([
                'status' => 'verified',
                'verified_at' => $providerVerifiedAt,
                'provider_verified_at' => $providerVerifiedAt,
            ])->save();
            $lockedUser->forceFill([
                'mobile_verified_at' => $providerVerifiedAt,
            ])->save();
            $this->accounts->provision($lockedUser);

            return $lockedChallenge->refresh();
        }, attempts: 3);
    }

    private function validatedProof(
        MobileVerificationChallenge $challenge,
        OtpVerificationProofData $proof,
    ): Carbon {
        if (! hash_equals((string) $challenge->provider_challenge_reference, $proof->reference)
            || ! hash_equals($challenge->purpose, $proof->purpose)) {
            throw ValidationException::withMessages([
                'code' => 'The verification provider returned evidence for another challenge.',
            ]);
        }

        try {
            $verifiedAt = Carbon::parse($proof->verified_at);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'code' => 'The verification provider returned invalid evidence.',
            ]);
        }

        $now = now();
        $clockSkewSeconds = max(0, (int) config(
            'x-change.onboarding.identity_otp.clock_skew_seconds',
            30,
        ));
        $proofTtlMinutes = max(1, (int) config(
            'x-change.onboarding.identity_otp.proof_ttl_minutes',
            15,
        ));

        if ($verifiedAt->greaterThan($now->copy()->addSeconds($clockSkewSeconds))
            || $verifiedAt->lessThan($now->copy()->subMinutes($proofTtlMinutes))) {
            throw ValidationException::withMessages([
                'code' => 'The verification provider returned stale evidence.',
            ]);
        }

        return $verifiedAt;
    }

    private function mobileHash(string $mobile): string
    {
        $key = config('x-change.onboarding.mobile_verification.hash_key') ?: config('app.key');

        if (! is_string($key) || trim($key) === '') {
            throw new LogicException('A mobile verification hash key is required.');
        }

        return hash_hmac('sha256', $mobile, $key);
    }
}
