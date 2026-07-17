<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Support\Number;
use LBHurtado\XChange\Contracts\CockpitHeaderReadModelProviderContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardMetricData;
use LBHurtado\XChange\Data\Cockpit\CockpitHeaderReadModelData;
use Throwable;

class WalletCockpitHeaderReadModelProvider implements CockpitHeaderReadModelProviderContract
{
    public function __construct(private readonly WalletAccessContract $wallets) {}

    public function forOperator(mixed $operator = null): CockpitHeaderReadModelData
    {
        return new CockpitHeaderReadModelData(
            balances: [
                $this->internalBalance($operator),
                $this->providerBalance(),
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

    private function providerBalance(): CockpitDashboardMetricData
    {
        return new CockpitDashboardMetricData(
            key: 'live',
            label: 'Live Balance',
            value: 'Provider balance not connected',
            helper: 'Provider balance adapters are not wired to Cockpit yet.',
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
