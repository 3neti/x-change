<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeRequestData;
use LBHurtado\XChange\Models\MobileVerificationChallenge;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use LogicException;
use Throwable;

final class StartMobileVerification
{
    public function __construct(
        private readonly OtpChallengeGateway $otp,
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

        $driver = $this->guardDriver();
        $purpose = $this->purpose();
        $reference = (string) Str::ulid();
        $challenge = DB::transaction(function () use ($user, $mobile, $reference, $driver, $purpose): MobileVerificationChallenge {
            MobileVerificationChallenge::query()
                ->where('user_type', $user::class)
                ->where('user_id', (string) $user->getKey())
                ->whereIn('status', ['delivery_pending', 'pending'])
                ->update(['status' => 'superseded']);

            return MobileVerificationChallenge::query()->create([
                'reference' => $reference,
                'user_type' => $user::class,
                'user_id' => (string) $user->getKey(),
                'mobile_hash' => $this->mobileHash($mobile),
                'provider' => $driver,
                'purpose' => $purpose,
                'status' => 'delivery_pending',
                'attempts' => 0,
                'expires_at' => now()->addMinutes(
                    (int) config('x-change.onboarding.mobile_verification.ttl_minutes', 10),
                ),
            ]);
        }, attempts: 3);

        try {
            $providerChallenge = $this->otp->create(new OtpChallengeRequestData(
                mobile: '+'.$mobile,
                purpose: $purpose,
                client_reference: $reference,
            ));

            $challenge->forceFill([
                'provider_challenge_reference' => $providerChallenge->reference,
                'status' => 'pending',
                'expires_at' => now()->addSeconds(max(1, $providerChallenge->expires_in)),
            ])->save();
        } catch (Throwable $exception) {
            $challenge->forceFill(['status' => 'delivery_failed'])->save();

            throw $exception;
        }

        return $challenge;
    }

    private function guardDriver(): string
    {
        $driver = trim((string) config('x-change.onboarding.identity_otp.driver', 'unavailable'));

        if (! in_array($driver, ['', 'unavailable', 'null'], true)) {
            return $driver;
        }

        throw ValidationException::withMessages([
            'mobile' => 'Mobile verification delivery is not configured.',
        ]);
    }

    private function purpose(): string
    {
        $purpose = trim((string) config(
            'x-change.onboarding.identity_otp.purpose',
            'onboarding.account',
        ));

        if ($purpose === '') {
            throw new LogicException('An onboarding identity OTP purpose is required.');
        }

        return $purpose;
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
