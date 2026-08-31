<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LBHurtado\FormHandlerOtp\Contracts\OtpChallengeGateway;
use LBHurtado\FormHandlerOtp\Data\OtpChallengeRequestData;
use LBHurtado\FormHandlerOtp\Data\OtpVerificationProofData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Disbursement\RefurbishRejectedPayCodePayout;
use LBHurtado\XChange\Models\CampaignPayoutRecoveryGrant;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use RuntimeException;
use Throwable;

final readonly class CampaignPayoutRecoveryService
{
    public function __construct(
        private OtpChallengeGateway $otp,
        private RefurbishRejectedPayCodePayout $refurbish,
    ) {}

    public function findForCodeOrFail(string $code): CampaignPayoutRecoveryGrant
    {
        if (! config('x-change.campaigns.payout_recovery.enabled')) {
            abort(404);
        }

        $voucher = Voucher::query()
            ->where('code', mb_strtoupper(trim($code)))
            ->firstOrFail();
        $grant = $this->findForVoucher($voucher);

        if (! $grant instanceof CampaignPayoutRecoveryGrant) {
            abort(404);
        }

        return $grant;
    }

    public function findForVoucher(Voucher $voucher): ?CampaignPayoutRecoveryGrant
    {
        if (! config('x-change.campaigns.payout_recovery.enabled')
            || data_get($voucher->metadata, 'treasury.pay_code_reservation.status') !== 'recovery_pending') {
            return null;
        }

        $grants = CampaignPayoutRecoveryGrant::query()
            ->with(['voucher', 'fulfillment.row', 'rejection'])
            ->where('voucher_id', $voucher->getKey())
            ->whereIn('status', [
                'available',
                'otp_pending',
                'verified',
                'submitting',
                'locked',
                'expired',
                'identity_changed',
            ])
            ->latest('id')
            ->limit(2)
            ->get();

        if ($grants->count() !== 1) {
            return null;
        }

        $grant = $grants->first();
        $rejection = $grant?->rejection;
        $fulfillment = $grant?->fulfillment;
        if (! $grant instanceof CampaignPayoutRecoveryGrant
            || $fulfillment?->pay_code !== $voucher->code
            || ! in_array($fulfillment?->status, ['recovery_required', 'recovery_ready'], true)
            || $rejection?->voucher_id !== $voucher->getKey()
            || $rejection?->status !== 'failed'
            || $rejection?->internal_status !== 'recovery_opened'
            || $rejection?->needs_review
            || blank($rejection?->provider_transaction_id)
            || $rejection?->completed_at === null) {
            return null;
        }

        return $grant;
    }

    public function start(CampaignPayoutRecoveryGrant $grant): CampaignPayoutRecoveryGrant
    {
        return Cache::lock('x-change:campaign-payout-recovery:'.$grant->reference, 30)
            ->block(5, function () use ($grant): CampaignPayoutRecoveryGrant {
                $grant = $grant->fresh(['fulfillment.row']);
                $this->assertGrantIsUsable($grant, ['available', 'otp_pending']);

                if ($grant->status === 'otp_pending'
                    && $grant->otp_expires_at?->isFuture()
                    && filled($grant->provider_challenge_reference_ciphertext)) {
                    return $grant;
                }

                $mobile = $this->beneficiaryMobile($grant);
                $challenge = $this->otp->create(new OtpChallengeRequestData(
                    mobile: '+'.$mobile,
                    purpose: (string) $grant->purpose,
                    client_reference: (string) $grant->reference,
                ));

                $grant->forceFill([
                    'provider_challenge_reference_ciphertext' => $challenge->reference,
                    'provider_challenge_reference_hash' => $this->sensitiveHash($challenge->reference),
                    'status' => 'otp_pending',
                    'attempts' => 0,
                    'otp_expires_at' => now()->addSeconds(max(1, $challenge->expires_in)),
                ])->save();

                return $grant->refresh();
            });
    }

    public function verify(CampaignPayoutRecoveryGrant $grant, string $code): CampaignPayoutRecoveryGrant
    {
        return Cache::lock('x-change:campaign-payout-recovery:'.$grant->reference, 30)
            ->block(5, function () use ($grant, $code): CampaignPayoutRecoveryGrant {
                $grant = $grant->fresh(['fulfillment.row']);
                $this->assertGrantIsUsable($grant, ['otp_pending']);
                $maxAttempts = max(1, (int) config(
                    'x-change.campaigns.payout_recovery.max_attempts',
                    5,
                ));

                if ($grant->attempts >= $maxAttempts) {
                    $grant->forceFill(['status' => 'locked'])->save();

                    throw ValidationException::withMessages([
                        'code' => 'Too many verification attempts. Ask the sender to reissue the Pay Code claim notification.',
                    ]);
                }

                $providerReference = trim((string) $grant->provider_challenge_reference_ciphertext);
                if ($providerReference === '' || $grant->otp_expires_at?->isPast()) {
                    $grant->forceFill(['status' => 'available'])->save();

                    throw ValidationException::withMessages([
                        'code' => 'This verification code has expired. Request a new code.',
                    ]);
                }

                $result = $this->otp->verify($providerReference, trim($code));
                if (! $result->ok || ! $result->proof instanceof OtpVerificationProofData) {
                    $attempts = $grant->attempts + 1;
                    $grant->forceFill([
                        'attempts' => $attempts,
                        'status' => $attempts >= $maxAttempts ? 'locked' : 'otp_pending',
                    ])->save();

                    throw ValidationException::withMessages([
                        'code' => $result->reason === 'expired'
                            ? 'This verification code has expired.'
                            : 'The verification code is invalid.',
                    ]);
                }

                $verifiedAt = $this->validatedProof($grant, $result->proof);

                return DB::transaction(function () use ($grant, $verifiedAt): CampaignPayoutRecoveryGrant {
                    $locked = CampaignPayoutRecoveryGrant::query()
                        ->lockForUpdate()
                        ->findOrFail($grant->getKey());
                    $this->assertGrantIsUsable($locked, ['otp_pending']);
                    $locked->forceFill([
                        'status' => 'verified',
                        'provider_verified_at' => $verifiedAt,
                        'verified_at' => $verifiedAt,
                    ])->save();

                    return $locked->refresh();
                }, attempts: 5);
            });
    }

    /** @return array<string, mixed> */
    public function submit(
        CampaignPayoutRecoveryGrant $grant,
        string $bankCode,
        string $accountNumber,
        ?string $mobile,
    ): array {
        $lock = Cache::lock('x-change:campaign-payout-recovery-submit:'.$grant->reference, 180);
        if (! $lock->get()) {
            throw new RuntimeException('Another payout recovery submission is already in progress.');
        }

        try {
            $grant = DB::transaction(function () use ($grant): CampaignPayoutRecoveryGrant {
                $locked = CampaignPayoutRecoveryGrant::query()
                    ->with('voucher')
                    ->lockForUpdate()
                    ->findOrFail($grant->getKey());
                $this->assertGrantIsUsable($locked, ['verified']);
                $locked->forceFill([
                    'status' => 'submitting',
                    'submitting_at' => now(),
                ])->save();

                return $locked;
            }, attempts: 5);

            try {
                $result = $this->refurbish->handle(
                    voucher: $grant->voucher,
                    requestedBy: $grant,
                    bankCode: mb_strtoupper(trim($bankCode)),
                    accountNumber: trim($accountNumber),
                    mobile: $mobile,
                    recoveryGrant: $grant,
                );
            } catch (ValidationException $exception) {
                $grant->forceFill([
                    'status' => 'verified',
                    'submitting_at' => null,
                ])->save();

                throw $exception;
            }

            $providerAccepted = ($result['provider_submission_accepted'] ?? null) === true;
            $grant->forceFill($providerAccepted ? [
                'status' => 'consumed',
                'consumed_at' => now(),
            ] : [
                'status' => 'verified',
                'submitting_at' => null,
            ])->save();

            return $result;
        } finally {
            $lock->release();
        }
    }

    /** @param list<string> $allowedStatuses */
    private function assertGrantIsUsable(
        CampaignPayoutRecoveryGrant $grant,
        array $allowedStatuses,
    ): void {
        if (! in_array($grant->status, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'recovery' => 'This Pay Code claim is no longer available.',
            ]);
        }

        if ($grant->expires_at?->isPast()) {
            $grant->forceFill(['status' => 'expired'])->save();

            throw ValidationException::withMessages([
                'recovery' => 'This Pay Code claim has expired.',
            ]);
        }
    }

    private function beneficiaryMobile(CampaignPayoutRecoveryGrant $grant): string
    {
        $mobile = MobileNumber::normalize(
            data_get($grant->fulfillment?->row?->beneficiary_ciphertext, 'mobile'),
        );
        if ($mobile === null || ! hash_equals((string) $grant->mobile_hash, $this->mobileHash($mobile))) {
            $grant->forceFill(['status' => 'identity_changed'])->save();

            throw ValidationException::withMessages([
                'recovery' => 'The beneficiary identity no longer matches this Pay Code claim.',
            ]);
        }

        return $mobile;
    }

    private function validatedProof(
        CampaignPayoutRecoveryGrant $grant,
        OtpVerificationProofData $proof,
    ): Carbon {
        $providerReference = (string) $grant->provider_challenge_reference_ciphertext;
        if (! hash_equals($providerReference, $proof->reference)
            || ! hash_equals((string) $grant->purpose, $proof->purpose)) {
            throw ValidationException::withMessages([
                'code' => 'The verification provider returned evidence for another recovery.',
            ]);
        }

        try {
            $verifiedAt = Carbon::parse($proof->verified_at)->utc();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'code' => 'The verification provider returned invalid evidence.',
            ]);
        }

        $clockSkew = max(0, (int) config(
            'x-change.campaigns.payout_recovery.clock_skew_seconds',
            30,
        ));
        $proofTtl = max(1, (int) config(
            'x-change.campaigns.payout_recovery.proof_ttl_minutes',
            15,
        ));
        if ($verifiedAt->greaterThan(now()->addSeconds($clockSkew))
            || $verifiedAt->lessThan(now()->subMinutes($proofTtl))) {
            throw ValidationException::withMessages([
                'code' => 'The verification provider returned stale evidence.',
            ]);
        }

        return $verifiedAt;
    }

    private function sensitiveHash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    private function mobileHash(string $mobile): string
    {
        $key = (string) (config('x-change.onboarding.mobile_verification.hash_key') ?: config('app.key'));

        return hash_hmac('sha256', $mobile, $key);
    }
}
