<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use Illuminate\Support\Carbon;
use LBHurtado\XChange\Support\Auth\MobileNumber;

final class VerifiedStoredValueClaim
{
    private bool $publicClaim = false;

    private ?string $mobile = null;

    public function establish(array $state, array $payload): void
    {
        $this->publicClaim = true;
        $this->mobile = null;

        $otp = data_get($payload, 'inputs.otp');
        $mobile = MobileNumber::normalize($payload['mobile'] ?? null);
        $otpMobile = MobileNumber::normalize(data_get($otp, 'mobile'));
        $reference = data_get($otp, 'reference_id');
        $verifiedAt = data_get($otp, 'verified_at');

        $flowId = data_get($state, 'flow_id');

        if (data_get($otp, 'verified') !== true
            || ! is_string($reference)
            || ! is_string($flowId)
            || ! hash_equals($flowId, $reference)
            || ! is_string(data_get($otp, 'verification_reference'))
            || trim(data_get($otp, 'verification_reference')) === ''
            || data_get($otp, 'verification_purpose') !== 'form_flow.otp'
            || ! is_string($verifiedAt)
            || $mobile === null
            || $otpMobile === null
            || ! hash_equals($mobile, $otpMobile)) {
            return;
        }

        try {
            $age = Carbon::parse($verifiedAt)->diffInSeconds(now(), false);
        } catch (\Throwable) {
            return;
        }

        if ($age < -30 || $age > 900) {
            return;
        }

        $this->mobile = $mobile;
    }

    public function isPublicClaim(): bool
    {
        return $this->publicClaim;
    }

    public function verifiedMobile(): ?string
    {
        return $this->mobile;
    }

    public function clear(): void
    {
        $this->publicClaim = false;
        $this->mobile = null;
    }
}
