<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use LBHurtado\EmiPaynamicsConstellation\Contracts\PendingOtpStore;

final class ClaimApprovalPendingOtpStore implements PendingOtpStore
{
    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $otpRequestPayload
     */
    public function getSubmittedOtp(array $otpRequestPayload): ?string
    {
        $referenceId = $this->referenceId($otpRequestPayload);

        if ($referenceId === null) {
            return null;
        }

        $otp = $this->cache->get($this->submittedKey($referenceId));

        if (! is_string($otp) || trim($otp) === '') {
            return null;
        }

        return trim($otp);
    }

    /**
     * @param  array<string, mixed>  $otpRequestPayload
     * @param  array<string, mixed>  $otpRequestResult
     */
    public function putPendingOtp(array $otpRequestPayload, array $otpRequestResult): void
    {
        $referenceId = $this->referenceId($otpRequestPayload);

        if ($referenceId === null) {
            return;
        }

        $ttl = now()->addMinutes((int) config('x-change.claim_approval.ttl_minutes', 15));

        $this->cache->put($this->pendingKey($referenceId), [
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => $referenceId,
            'amount' => data_get($otpRequestPayload, 'amount'),
            'bank_account_no' => data_get($otpRequestPayload, 'bank_account_no'),
            'bank_id' => data_get($otpRequestPayload, 'bank_id'),
            'reason' => data_get($otpRequestPayload, 'reason'),
            'target' => is_string(data_get($otpRequestResult, 'data'))
                ? data_get($otpRequestResult, 'data')
                : null,
            'otp_required' => true,
            'polling_required' => false,
            'manual_review' => false,
            'message' => 'Paynamics payout OTP is pending.',
            'payload' => $otpRequestPayload,
            'result' => $otpRequestResult,
            'expires_at' => $ttl->toIso8601String(),
        ], $ttl);
    }

    public function putSubmittedOtp(string $referenceId, string $otp): void
    {
        $ttl = now()->addMinutes((int) config('x-change.claim_approval.ttl_minutes', 15));

        $this->cache->put($this->submittedKey($referenceId), trim($otp), $ttl);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pending(string $referenceId): ?array
    {
        $pending = $this->cache->get($this->pendingKey($referenceId));

        return is_array($pending) ? $pending : null;
    }

    /**
     * @param  array<string, mixed>  $otpRequestPayload
     */
    private function referenceId(array $otpRequestPayload): ?string
    {
        $referenceId = data_get($otpRequestPayload, 'request_id');

        if (! is_string($referenceId) || trim($referenceId) === '') {
            return null;
        }

        return trim($referenceId);
    }

    private function pendingKey(string $referenceId): string
    {
        return "x-change:claim-approval:otp:pending:{$referenceId}";
    }

    private function submittedKey(string $referenceId): string
    {
        return "x-change:claim-approval:otp:submitted:{$referenceId}";
    }
}
