<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use LBHurtado\EmiCore\Contracts\DeferredPayoutApprovalScope;
use LBHurtado\EmiCore\Contracts\PendingPayoutOtpInterpreter;
use LBHurtado\EmiCore\Contracts\PendingPayoutOtpStore;
use RuntimeException;

final class ClaimApprovalPendingOtpStore implements PendingPayoutOtpStore
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
            ...$this->interpreter()->pendingMetadata($otpRequestPayload, $otpRequestResult),
            'expires_at' => $ttl->toIso8601String(),
        ], $ttl);
    }

    public function putSubmittedOtp(string $referenceId, string $otp): void
    {
        $ttl = now()->addMinutes((int) config('x-change.claim_approval.ttl_minutes', 15));

        $this->cache->put($this->submittedKey($referenceId), trim($otp), $ttl);
    }

    public function forget(string $referenceId): void
    {
        $referenceId = trim($referenceId);

        if ($referenceId === '') {
            return;
        }

        $this->cache->forget($this->pendingKey($referenceId));
        $this->cache->forget($this->submittedKey($referenceId));
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
        return $this->interpreter()->reference($otpRequestPayload);
    }

    private function interpreter(): PendingPayoutOtpInterpreter
    {
        $matches = collect(app()->tagged('emi.pending-payout-otp-interpreters'))
            ->filter(fn (mixed $candidate): bool => $candidate instanceof PendingPayoutOtpInterpreter)
            ->uniqueStrict();
        $activeProviders = collect(app()->tagged('emi.deferred-payout-approval-scopes'))
            ->filter(fn (mixed $scope): bool => $scope instanceof DeferredPayoutApprovalScope && $scope->isActiveProvider())
            ->map(fn (DeferredPayoutApprovalScope $scope): string => $scope->providerCode())
            ->unique();
        if ($activeProviders->isNotEmpty()) {
            $matches = $matches->filter(fn (PendingPayoutOtpInterpreter $candidate): bool => $activeProviders->contains($candidate->providerCode()));
        }

        if ($matches->count() !== 1) {
            throw new RuntimeException('A unique pending payout OTP interpreter is required.');
        }

        return $matches->first();
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
