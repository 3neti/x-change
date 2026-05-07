<?php

namespace LBHurtado\XChange\Lifecycle\Runners\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class WalletTransactionSnapshot
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentFor(
        Model $issuer,
        string $idempotencyKey,
        ?string $voucherCode = null,
        int $limit = 10,
    ): array {
        if (! isset($issuer->wallet) || ! $issuer->wallet) {
            return [];
        }

        $wallet = $issuer->wallet;

        if (! method_exists($wallet, 'transactions')) {
            return [];
        }

        $transactions = $wallet->transactions()
            ->latest('id')
            ->limit(max($limit, 1) * 5)
            ->get();

        return $transactions
            ->filter(function ($transaction) use ($idempotencyKey, $voucherCode) {
                $meta = $this->normalizeTransactionMeta(
                    $transaction->meta ?? $transaction->metadata ?? null
                );

                if (data_get($meta, 'idempotency_key') === $idempotencyKey) {
                    return true;
                }

                if ($voucherCode !== null && $voucherCode !== '') {
                    return data_get($meta, 'voucher_code') === $voucherCode
                        || data_get($meta, 'external_code') === $voucherCode
                        || data_get($meta, 'code') === $voucherCode;
                }

                return false;
            })
            ->take($limit)
            ->map(function ($transaction): array {
                $amountMinor = $this->resolveTransactionAmountMinor($transaction);
                $currency = (string) ($transaction->currency ?? 'PHP');
                $meta = $this->normalizeTransactionMeta(
                    $transaction->meta ?? $transaction->metadata ?? null
                );

                return [
                    'id' => $transaction->id ?? null,
                    'uuid' => $transaction->uuid ?? null,
                    'type' => $transaction->type ?? $transaction->transaction_type ?? null,
                    'confirmed' => isset($transaction->confirmed)
                        ? (bool) $transaction->confirmed
                        : null,
                    'amount_minor' => $amountMinor,
                    'amount' => $amountMinor / 100,
                    'currency' => $currency,
                    'formatted_amount' => Number::currency($amountMinor / 100, in: $currency),
                    'reason' => data_get($meta, 'reason'),
                    'voucher_code' => data_get($meta, 'voucher_code')
                        ?? data_get($meta, 'external_code')
                            ?? data_get($meta, 'code'),
                    'reference' => data_get($meta, 'reference'),
                    'idempotency_key' => data_get($meta, 'idempotency_key'),
                    'created_at' => optional($transaction->created_at)?->toIso8601String(),
                    'meta' => $meta,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeTransactionMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($meta)) {
            return (array) $meta;
        }

        return [];
    }

    protected function resolveTransactionAmountMinor(object $transaction): int
    {
        $candidates = [
            $transaction->amount ?? null,
            $transaction->amount_int ?? null,
            $transaction->amount_minor ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                return (int) $candidate;
            }
        }

        return 0;
    }
}
