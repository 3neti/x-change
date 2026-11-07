<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\Voucher\Models\Voucher;

final class ClaimPayoutLocalityContext
{
    /** @param array<string, mixed> $evidence */
    public function remember(Voucher $voucher, string $claimKey, array $evidence): void
    {
        if ($claimKey === '' || array_diff(array_keys($evidence), ['ip_locality', 'captured']) !== []) {
            throw new InvalidArgumentException('Invalid claim locality evidence.');
        }
        foreach (['ip_locality' => ['city', 'region'], 'captured' => ['address1', 'city', 'country', 'postal_code', 'source', 'dataset_version']] as $field => $allowed) {
            if (isset($evidence[$field]) && (! is_array($evidence[$field]) || array_diff(array_keys($evidence[$field]), $allowed) !== [])) {
                throw new InvalidArgumentException('Invalid claim locality evidence.');
            }
        }
        DB::table('x_change_claim_payout_locality_contexts')->insertOrIgnore([
            'voucher_id' => $voucher->getKey(),
            'claim_key_hash' => $this->keyHash($claimKey),
            'evidence' => Crypt::encryptString(json_encode($evidence, JSON_THROW_ON_ERROR)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function find(Voucher $voucher, string $claimKey): ?array
    {
        $encrypted = DB::table('x_change_claim_payout_locality_contexts')
            ->where('voucher_id', $voucher->getKey())
            ->where('claim_key_hash', $this->keyHash($claimKey))->value('evidence');

        return is_string($encrypted) ? json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR) : null;
    }

    private function keyHash(string $key): string
    {
        return hash_hmac('sha256', $key, (string) config('app.key'));
    }
}
