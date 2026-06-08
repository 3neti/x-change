<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

final class CompiledClaimResultSession
{
    public const KEY = 'compiled_claim_result';

    public function put(mixed $result): void
    {
        session()->put(self::KEY, $this->normalize($result));
    }

    public function pull(): ?array
    {
        $value = session()->pull(self::KEY);

        return is_array($value) ? $value : null;
    }

    public function forget(): void
    {
        session()->forget(self::KEY);
    }

    public function normalize(mixed $result): array
    {
        return [
            'status' => data_get($result, 'status'),
            'claim_type' => data_get($result, 'claim_type'),
            'voucher_code' => data_get($result, 'voucher_code'),
            'claimed' => data_get($result, 'claimed'),
            'requested_amount' => data_get($result, 'requested_amount'),
            'disbursed_amount' => data_get($result, 'disbursed_amount'),
            'currency' => data_get($result, 'currency'),
            'remaining_balance' => data_get($result, 'remaining_balance'),
            'fully_claimed' => data_get($result, 'fully_claimed'),
            'messages' => data_get($result, 'messages', []),
        ];
    }
}
