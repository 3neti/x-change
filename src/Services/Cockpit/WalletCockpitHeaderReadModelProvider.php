<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Carbon\CarbonImmutable;
use Illuminate\Support\Number;
use LBHurtado\XChange\Contracts\CockpitHeaderReadModelProviderContract;
use LBHurtado\XChange\Contracts\VoucherLiabilitySummaryContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardMetricData;
use LBHurtado\XChange\Data\Cockpit\CockpitHeaderReadModelData;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use Throwable;

class WalletCockpitHeaderReadModelProvider implements CockpitHeaderReadModelProviderContract
{
    public function __construct(
        private readonly WalletAccessContract $wallets,
        private readonly ?BuildBalanceOverview $fundingOverview = null,
        private readonly ?VoucherLiabilitySummaryContract $liabilities = null,
    ) {}

    public function forOperator(mixed $operator = null): CockpitHeaderReadModelData
    {
        return new CockpitHeaderReadModelData(
            balances: [
                $this->internalBalance($operator),
                $this->outstandingLiability($operator),
                $this->usableBalance($operator),
                $this->providerBalance($operator),
            ],
            redactions: [
                'payloads' => 'balance-summary-only',
                'wallet_payloads_exposed' => false,
                'provider_payloads_exposed' => false,
                'voucher_payloads_exposed' => false,
                'raw_payloads_exposed' => false,
                'mutates_wallets' => false,
                'releases_funds' => false,
                'calls_providers' => false,
            ],
        );
    }

    private function internalBalance(mixed $operator): CockpitDashboardMetricData
    {
        if ($operator === null) {
            return $this->disconnectedInternalBalance();
        }

        try {
            $wallet = $this->wallets->resolveForUser($operator);
            $balance = $this->wallets->getBalance($wallet);

            return new CockpitDashboardMetricData(
                key: 'internal',
                label: 'Internal Balance',
                value: $this->formatMoney($balance),
                helper: 'Read from the authenticated operator wallet.',
                tone: 'healthy',
            );
        } catch (Throwable) {
            return $this->disconnectedInternalBalance();
        }
    }

    private function providerBalance(mixed $operator): CockpitDashboardMetricData
    {
        if (! (bool) config('x-change.cockpit.header_provider_balance.enabled', true) || $operator === null) {
            return $this->disconnectedProviderBalance();
        }

        try {
            $overview = ($this->fundingOverview ?? app(BuildBalanceOverview::class))
                ->handle($operator, null, false);
            $balance = $this->providerBalanceFromOverview($overview);

            if ($balance === null) {
                return $this->disconnectedProviderBalance();
            }

            $value = $this->providerBalanceMinor($balance);

            if ($value === null) {
                return $this->disconnectedProviderBalance(
                    $this->stringValue($balance['sync_message'] ?? null),
                    $this->providerBalanceLabel($balance),
                );
            }

            return new CockpitDashboardMetricData(
                key: 'live',
                label: $this->providerBalanceLabel($balance),
                value: $this->formatMoney($value),
                helper: $this->providerBalanceHelper($balance),
                tone: ((bool) ($balance['is_stale'] ?? false)) ? 'warning' : 'healthy',
            );
        } catch (Throwable) {
            return $this->disconnectedProviderBalance();
        }
    }

    private function outstandingLiability(mixed $operator): CockpitDashboardMetricData
    {
        if ($operator === null) {
            return new CockpitDashboardMetricData(
                key: 'outstanding',
                label: 'Outstanding Pay Codes',
                value: 'Not connected',
                helper: 'No operator is available for liability summary.',
                tone: 'neutral',
            );
        }

        try {
            $summary = ($this->liabilities ?? app(VoucherLiabilitySummaryContract::class))
                ->forIssuer($operator);

            return new CockpitDashboardMetricData(
                key: 'outstanding',
                label: 'Outstanding Pay Codes',
                value: $this->formatMoney($summary->outstanding_liability_minor),
                helper: 'Read-only active Pay Code liability estimate.',
                tone: $summary->outstanding_liability_minor > 0 ? 'warning' : 'healthy',
            );
        } catch (Throwable) {
            return new CockpitDashboardMetricData(
                key: 'outstanding',
                label: 'Outstanding Pay Codes',
                value: 'Not connected',
                helper: 'Voucher liability summary is unavailable.',
                tone: 'neutral',
            );
        }
    }

    private function usableBalance(mixed $operator): CockpitDashboardMetricData
    {
        if ($operator === null) {
            return new CockpitDashboardMetricData(
                key: 'usable',
                label: 'Usable Balance',
                value: 'Not connected',
                helper: 'No operator is available for usable balance estimate.',
                tone: 'neutral',
            );
        }

        try {
            $summary = ($this->liabilities ?? app(VoucherLiabilitySummaryContract::class))
                ->forIssuer($operator);

            return new CockpitDashboardMetricData(
                key: 'usable',
                label: 'Usable Balance',
                value: $this->formatMoney($summary->usable_balance_estimate_minor),
                helper: 'Wallet balance minus outstanding active Pay Code liability.',
                tone: 'healthy',
            );
        } catch (Throwable) {
            return new CockpitDashboardMetricData(
                key: 'usable',
                label: 'Usable Balance',
                value: 'Not connected',
                helper: 'Usable balance estimate is unavailable.',
                tone: 'neutral',
            );
        }
    }

    private function disconnectedProviderBalance(
        ?string $helper = null,
        string $label = 'Provider Liquidity',
    ): CockpitDashboardMetricData {
        return new CockpitDashboardMetricData(
            key: 'live',
            label: $label,
            value: 'Not available',
            helper: $helper ?? 'No cached provider liquidity snapshot is available.',
            tone: 'neutral',
        );
    }

    private function disconnectedInternalBalance(): CockpitDashboardMetricData
    {
        return new CockpitDashboardMetricData(
            key: 'internal',
            label: 'Internal Balance',
            value: 'Internal balance not connected',
            helper: 'No operator wallet balance is available for this view.',
            tone: 'neutral',
        );
    }

    /**
     * @param  array<string, mixed>  $overview
     * @return array<string, mixed>|null
     */
    private function providerBalanceFromOverview(array $overview): ?array
    {
        $balances = $overview['balances'] ?? [];

        if (! is_array($balances)) {
            return null;
        }

        foreach ($balances as $balance) {
            if (! is_array($balance)) {
                continue;
            }

            $key = $this->stringValue($balance['key'] ?? null);
            $authority = $this->stringValue($balance['authority'] ?? null);

            if ($key === 'provider_wallet' || $key === 'netbank_source_account' || str_starts_with((string) $authority, 'provider_')) {
                return $balance;
            }
        }

        return null;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<string, mixed>  $balance
     */
    private function providerBalanceLabel(array $balance): string
    {
        return match ($this->stringValue($balance['key'] ?? null)) {
            'netbank_source_account' => 'NetBank Liquidity',
            'provider_wallet' => 'Provider Wallet',
            default => 'Provider Liquidity',
        };
    }

    /**
     * @param  array<string, mixed>  $balance
     */
    private function providerBalanceMinor(array $balance): mixed
    {
        return $this->stringValue($balance['key'] ?? null) === 'netbank_source_account'
            ? ($balance['available_balance_minor'] ?? $balance['balance_minor'] ?? null)
            : ($balance['balance_minor'] ?? $balance['available_balance_minor'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $balance
     */
    private function providerBalanceHelper(array $balance): string
    {
        $description = $this->stringValue($balance['description'] ?? null)
            ?? 'Read-only cached provider balance summary.';
        $checkedAt = $this->stringValue($balance['checked_at'] ?? null);

        if ($checkedAt === null) {
            return $description;
        }

        try {
            $relativeTime = CarbonImmutable::parse($checkedAt)->diffForHumans();
        } catch (Throwable) {
            return $description;
        }

        $freshness = ((bool) ($balance['is_stale'] ?? false))
            ? "Cached snapshot is stale; last refreshed {$relativeTime}."
            : "Cached snapshot refreshed {$relativeTime}; this page did not call the provider.";

        return "{$description} {$freshness}";
    }

    private function formatMoney(int|float|string|null $balance): string
    {
        $currency = (string) config('x-change.pricing.currency', config('x-change.product.default_currency', 'PHP'));

        if ($balance === null) {
            return 'Internal balance not connected';
        }

        if (is_int($balance)) {
            return Number::currency($balance / 100, in: $currency);
        }

        if (is_string($balance) && preg_match('/^-?\d+$/', trim($balance)) === 1) {
            return Number::currency(((int) trim($balance)) / 100, in: $currency);
        }

        return Number::currency((float) $balance, in: $currency);
    }
}
