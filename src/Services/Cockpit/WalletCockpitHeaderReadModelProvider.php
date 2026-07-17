<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Support\Number;
use LBHurtado\XChange\Contracts\CockpitHeaderReadModelProviderContract;
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
    ) {}

    public function forOperator(mixed $operator = null): CockpitHeaderReadModelData
    {
        return new CockpitHeaderReadModelData(
            balances: [
                $this->internalBalance($operator),
                $this->providerBalance($operator),
            ],
            redactions: [
                'payloads' => 'balance-summary-only',
                'wallet_payloads_exposed' => false,
                'provider_payloads_exposed' => false,
                'raw_payloads_exposed' => false,
                'mutates_wallets' => false,
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
        if (! (bool) config('x-change.cockpit.header_provider_balance.enabled', false) || $operator === null) {
            return $this->disconnectedProviderBalance();
        }

        try {
            $overview = ($this->fundingOverview ?? app(BuildBalanceOverview::class))
                ->handle($operator, null, false);
            $balance = $this->providerBalanceFromOverview($overview);

            if ($balance === null) {
                return $this->disconnectedProviderBalance();
            }

            $value = $balance['balance_minor'] ?? $balance['available_balance_minor'] ?? null;

            if ($value === null) {
                return $this->disconnectedProviderBalance($this->stringValue($balance['sync_message'] ?? null));
            }

            return new CockpitDashboardMetricData(
                key: 'live',
                label: 'Live Balance',
                value: $this->formatMoney($value),
                helper: $this->stringValue($balance['description'] ?? null)
                    ?? 'Read-only provider balance summary.',
                tone: ((bool) ($balance['is_stale'] ?? false)) ? 'warning' : 'healthy',
            );
        } catch (Throwable) {
            return $this->disconnectedProviderBalance();
        }
    }

    private function disconnectedProviderBalance(?string $helper = null): CockpitDashboardMetricData
    {
        return new CockpitDashboardMetricData(
            key: 'live',
            label: 'Live Balance',
            value: 'Provider balance not connected',
            helper: $helper ?? 'Provider balance adapters are not wired to Cockpit yet.',
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
