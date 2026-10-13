<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\IssuanceBatch;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeRequestData;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Models\MobileVerificationChallenge;
use LBHurtado\XChange\Support\Auth\MobileNumber;

final readonly class StartIssuanceBatchRecovery
{
    public function __construct(private OtpChallengeGateway $otp) {}

    public function handle(IssuanceBatch $batch, string $mobile): MobileVerificationChallenge
    {
        $normalized = MobileNumber::normalize($mobile);
        $storedHash = (string) $batch->uploader_mobile_hash;

        if ($normalized === null || ! $this->matches($storedHash, $mobile, $normalized)) {
            throw ValidationException::withMessages([
                'mobile' => 'We could not verify this recovery request.',
            ]);
        }

        $reference = (string) Str::ulid();
        $challenge = DB::transaction(function () use ($batch, $reference, $normalized): MobileVerificationChallenge {
            MobileVerificationChallenge::query()
                ->where('user_type', IssuanceBatch::class)
                ->where('user_id', (string) $batch->getKey())
                ->whereIn('status', ['delivery_pending', 'pending'])
                ->update(['status' => 'superseded']);

            return MobileVerificationChallenge::query()->create([
                'reference' => $reference,
                'user_type' => IssuanceBatch::class,
                'user_id' => (string) $batch->getKey(),
                'mobile_hash' => hash('sha256', $normalized),
                'provider' => (string) config('x-change.onboarding.identity_otp.driver', 'unavailable'),
                'purpose' => 'public.batch-recovery',
                'status' => 'delivery_pending',
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
            ]);
        });

        try {
            $providerChallenge = $this->otp->create(new OtpChallengeRequestData(
                mobile: '+'.$normalized,
                purpose: 'public.batch-recovery',
                client_reference: $reference,
            ));
            $challenge->forceFill([
                'provider_challenge_reference' => $providerChallenge->reference,
                'status' => 'pending',
                'expires_at' => now()->addSeconds(max(1, $providerChallenge->expires_in)),
            ])->save();
        } catch (\Throwable $exception) {
            $challenge->forceFill(['status' => 'delivery_failed'])->save();
            throw $exception;
        }

        return $challenge;
    }

    private function matches(string $storedHash, string $raw, string $normalized): bool
    {
        return $storedHash !== '' && (
            hash_equals($storedHash, hash('sha256', trim($raw)))
            || hash_equals($storedHash, hash('sha256', $normalized))
        );
    }
}
