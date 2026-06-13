<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use LBHurtado\Voucher\Models\Voucher;

final class ClaimApprovalResumePayloadSession
{
    public const KEY = 'claim_approval_resume_payload';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function put(Voucher $voucher, array $payload): void
    {
        session()->put($this->key($voucher), $payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(Voucher $voucher): ?array
    {
        $payload = session()->get($this->key($voucher));

        return is_array($payload) ? $payload : null;
    }

    public function forget(Voucher $voucher): void
    {
        session()->forget($this->key($voucher));
    }

    private function key(Voucher $voucher): string
    {
        return self::KEY.':'.(string) $voucher->code;
    }
}
