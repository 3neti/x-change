<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Carbon\CarbonImmutable;
use LBHurtado\XChange\Models\ProviderBalanceSnapshot;
use LBHurtado\XChange\Support\Time\UtcInstant;

class ProviderBalanceSnapshotStore
{
    public const string GlobalScope = 'global';

    public function find(
        string $providerCode,
        string $balanceKey,
        string $scopeKey = self::GlobalScope,
    ): ?ProviderBalanceSnapshot {
        return ProviderBalanceSnapshot::query()
            ->where('provider_code', $providerCode)
            ->where('balance_key', $balanceKey)
            ->where('scope_key', $scopeKey)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $balance
     */
    public function recordSuccess(
        string $providerCode,
        string $balanceKey,
        array $balance,
        string $scopeKey = self::GlobalScope,
    ): ProviderBalanceSnapshot {
        return ProviderBalanceSnapshot::query()->updateOrCreate(
            [
                'provider_code' => $providerCode,
                'balance_key' => $balanceKey,
                'scope_key' => $scopeKey,
            ],
            [
                'balance_minor' => $balance['balance_minor'] ?? null,
                'available_balance_minor' => $balance['available_balance_minor'] ?? null,
                'currency' => $balance['currency'] ?? config('x-change.pricing.currency', 'PHP'),
                'account_reference_masked' => $balance['account_number_masked'] ?? null,
                'provider_as_of' => $this->dateTime($balance['as_of'] ?? null),
                'fetched_at' => $this->dateTime($balance['fetched_at'] ?? now()),
                'refresh_status' => 'fresh',
                'failure_reason' => null,
                'last_refresh_failed_at' => null,
            ],
        );
    }

    public function recordFailure(
        string $providerCode,
        string $balanceKey,
        string $reason,
        string $scopeKey = self::GlobalScope,
    ): ?ProviderBalanceSnapshot {
        $snapshot = $this->find($providerCode, $balanceKey, $scopeKey);

        if ($snapshot === null) {
            return null;
        }

        $snapshot->forceFill([
            'refresh_status' => 'refresh_failed',
            'failure_reason' => $reason,
            'last_refresh_failed_at' => now(),
        ])->save();

        return $snapshot->refresh();
    }

    public function recordReviewRequired(
        string $providerCode,
        string $balanceKey,
        string $reason,
        string $scopeKey = self::GlobalScope,
    ): ?ProviderBalanceSnapshot {
        $snapshot = $this->find($providerCode, $balanceKey, $scopeKey);

        if ($snapshot === null) {
            return null;
        }

        $snapshot->forceFill([
            'refresh_status' => 'review_required',
            'failure_reason' => $reason,
            'last_refresh_failed_at' => null,
        ])->save();

        return $snapshot->refresh();
    }

    private function dateTime(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return UtcInstant::parse($value);
    }
}
