<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;
use LBHurtado\XChange\Models\MobileVerificationChallenge;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use LogicException;
use Throwable;

final class StartMobileVerification
{
    public function __construct(
        private readonly WithdrawalOtpApprovalServiceContract $otp,
    ) {}

    public function handle(Model $user): MobileVerificationChallenge
    {
        $mobile = MobileNumber::normalize(
            is_string($user->getRawOriginal('mobile'))
                ? $user->getRawOriginal('mobile')
                : null,
        );

        if ($mobile === null) {
            throw ValidationException::withMessages([
                'mobile' => 'A valid mobile number is required.',
            ]);
        }

        if ($user->getAttribute('mobile_verified_at') !== null) {
            throw ValidationException::withMessages([
                'mobile' => 'This mobile number is already verified.',
            ]);
        }

        $this->guardDriver();
        $reference = (string) Str::ulid();
        $challenge = DB::transaction(function () use ($user, $mobile, $reference): MobileVerificationChallenge {
            MobileVerificationChallenge::query()
                ->where('user_type', $user::class)
                ->where('user_id', (string) $user->getKey())
                ->where('status', 'pending')
                ->update(['status' => 'superseded']);

            return MobileVerificationChallenge::query()->create([
                'reference' => $reference,
                'user_type' => $user::class,
                'user_id' => (string) $user->getKey(),
                'mobile_hash' => $this->mobileHash($mobile),
                'provider' => (string) config('x-change.withdrawal.otp.driver', 'null'),
                'status' => 'pending',
                'attempts' => 0,
                'expires_at' => now()->addMinutes(
                    (int) config('x-change.onboarding.mobile_verification.ttl_minutes', 10),
                ),
            ]);
        }, attempts: 3);

        try {
            $this->otp->request($mobile, $reference, [
                'purpose' => 'mobile_onboarding',
                'user_type' => $user::class,
                'user_id' => (string) $user->getKey(),
            ]);
        } catch (Throwable $exception) {
            $challenge->forceFill(['status' => 'delivery_failed'])->save();

            throw $exception;
        }

        return $challenge;
    }

    private function guardDriver(): void
    {
        if (config('x-change.withdrawal.otp.driver', 'null') !== 'null') {
            return;
        }

        $allowed = (array) config(
            'x-change.onboarding.mobile_verification.allow_null_driver_environments',
            ['local', 'testing'],
        );

        if (! app()->environment($allowed)) {
            throw ValidationException::withMessages([
                'mobile' => 'Mobile verification delivery is not configured.',
            ]);
        }
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
