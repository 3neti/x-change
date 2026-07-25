<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;

final class PayCodeFundingInspectionStore
{
    public function issue(
        Voucher $voucher,
        Authenticatable $claimant,
    ): string {
        $token = Str::random(64);

        Cache::put(
            $this->key($token),
            [
                'voucher_id' => (int) $voucher->getKey(),
                'claimant_type' => $claimant::class,
                'claimant_id' => (string) $claimant->getAuthIdentifier(),
            ],
            now()->addSeconds(max(
                60,
                (int) config(
                    'x-change.funding.pay_code_claims.inspection_ttl_seconds',
                    600,
                ),
            )),
        );

        return $token;
    }

    public function resolve(
        string $token,
        Authenticatable $claimant,
    ): ?Voucher {
        $payload = Cache::get($this->key($token));

        if (
            ! is_array($payload)
            || ($payload['claimant_type'] ?? null) !== $claimant::class
            || (string) ($payload['claimant_id'] ?? '') !== (string) $claimant->getAuthIdentifier()
        ) {
            return null;
        }

        return Voucher::query()->find($payload['voucher_id'] ?? null);
    }

    public function forget(string $token): void
    {
        Cache::forget($this->key($token));
    }

    private function key(string $token): string
    {
        return 'x-change:funding:pay-code-inspection:'.hash('sha256', $token);
    }
}
