<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;
use LBHurtado\XChange\Models\MobileVerificationChallenge;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use LogicException;

final class VerifyMobileVerification
{
    public function __construct(
        private readonly WithdrawalOtpApprovalServiceContract $otp,
    ) {}

    public function handle(Model $user, string $code): MobileVerificationChallenge
    {
        $challenge = MobileVerificationChallenge::query()
            ->where('user_type', $user::class)
            ->where('user_id', (string) $user->getKey())
            ->where('status', 'pending')
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

        if ($challenge->status !== 'pending') {
            throw ValidationException::withMessages([
                'code' => 'This verification challenge is no longer active.',
            ]);
        }

        if ($challenge->expires_at->isPast()) {
            $challenge->forceFill(['status' => 'expired'])->save();

            throw ValidationException::withMessages([
                'code' => 'This verification code has expired.',
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

        $verified = $this->otp->verify($mobile, $challenge->reference, $code, [
            'purpose' => 'mobile_onboarding',
            'user_type' => $user::class,
            'user_id' => (string) $user->getKey(),
        ]);

        if (! $verified) {
            $attempts = $challenge->attempts + 1;
            $challenge->forceFill([
                'attempts' => $attempts,
                'status' => $attempts >= $maxAttempts ? 'locked' : 'pending',
            ])->save();

            throw ValidationException::withMessages([
                'code' => 'The verification code is invalid.',
            ]);
        }

        return DB::transaction(function () use ($user, $challenge): MobileVerificationChallenge {
            $lockedChallenge = MobileVerificationChallenge::query()
                ->lockForUpdate()
                ->findOrFail($challenge->getKey());
            $lockedUser = $user->newQuery()
                ->lockForUpdate()
                ->findOrFail($user->getKey());
            $verifiedAt = now();

            $lockedChallenge->forceFill([
                'status' => 'verified',
                'verified_at' => $verifiedAt,
            ])->save();
            $lockedUser->forceFill([
                'mobile_verified_at' => $verifiedAt,
            ])->save();

            return $lockedChallenge->refresh();
        }, attempts: 3);
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
