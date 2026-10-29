<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use Illuminate\Support\Facades\Crypt;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Models\VoucherClaim;
use Throwable;

final class ClaimDestinationReceipt
{
    private const KEY = 'x-change.claim-destination-receipt';

    /** @param array<string, mixed> $payload */
    public function remember(Voucher $voucher, mixed $result, array $payload, string $idempotencyKey): void
    {
        $account = data_get($payload, 'account_number');
        if (! $result instanceof SubmitPayCodeClaimResultData || ! $result->claimed
            || $result->voucher_code !== (string) $voucher->code
            || ! in_array($result->status, ['succeeded', 'redeemed', 'withdrawn', 'pending_review'], true)
            || ! is_string($account) || trim($account) === '') {
            return;
        }

        $claim = VoucherClaim::query()->where('voucher_id', $voucher->getKey())
            ->where('idempotency_key', $idempotencyKey)->whereNotNull('completed_at')
            ->whereIn('status', ['succeeded', 'redeemed', 'withdrawn', 'pending_review'])->first();
        if (! $claim || $idempotencyKey === '') {
            return;
        }

        $account = trim($account);
        $maskedAccount = str_repeat('*', max(strlen($account) - 4, 0)).substr($account, -4);
        $bankCode = data_get($payload, 'bank_code');
        if (! is_string($claim->account_number_masked)
            || ! hash_equals($claim->account_number_masked, $maskedAccount)
            || ! is_string($bankCode) || ! is_string($claim->bank_code)
            || strcasecmp(trim($bankCode), trim($claim->bank_code)) !== 0) {
            return;
        }

        try {
            $encryptedAccount = Crypt::encryptString($account);
        } catch (Throwable) {
            return;
        }

        session()->put(self::KEY, [
            'voucher_id' => $voucher->getKey(),
            'claim_id' => $claim->getKey(),
            'session_id' => session()->getId(),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'account' => $encryptedAccount,
        ]);
    }

    /** @return array{account: string, amount_minor: int|null, currency: string|null}|null */
    public function forClaim(Voucher $voucher, ?VoucherClaim $claim): ?array
    {
        $receipt = session()->get(self::KEY);
        if (is_array($receipt) && (int) ($receipt['expires_at'] ?? 0) <= now()->timestamp) {
            session()->forget(self::KEY);

            return null;
        }

        if (! $claim || ! is_array($receipt)
            || (string) ($receipt['voucher_id'] ?? '') !== (string) $voucher->getKey()
            || (string) ($receipt['claim_id'] ?? '') !== (string) $claim->getKey()
            || ! hash_equals((string) ($receipt['session_id'] ?? ''), session()->getId())
            || ! is_string($receipt['account'] ?? null)) {
            return null;
        }

        try {
            $account = Crypt::decryptString($receipt['account']);
        } catch (Throwable) {
            return null;
        }

        return [
            'account' => $account,
            'amount_minor' => $claim->requested_amount_minor,
            'currency' => $claim->currency,
        ];
    }
}
