<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Carbon\CarbonImmutable;
use Illuminate\Support\Number;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\CockpitHeaderReadModelProviderContract;
use LBHurtado\XChange\Contracts\TreasuryVocabularyReadModelContract;
use LBHurtado\XChange\Contracts\VoucherLiabilitySummaryContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardMetricData;
use LBHurtado\XChange\Data\Cockpit\CockpitHeaderReadModelData;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use Throwable;

class WalletCockpitHeaderReadModelProvider implements CockpitHeaderReadModelProviderContract
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $resolvedVocabulary = null;

    public function __construct(
        private readonly WalletAccessContract $wallets,
        private readonly ?BuildBalanceOverview $fundingOverview = null,
        private readonly ?VoucherLiabilitySummaryContract $liabilities = null,
        private readonly ?AccountBalanceReadModelContract $accountBalances = null,
        private readonly ?TreasuryVocabularyReadModelContract $vocabulary = null,
    ) {}

    public function forOperator(mixed $operator = null): CockpitHeaderReadModelData
    {
        $internalBalance = $this->internalBalance($operator);
        $outstandingLiability = $this->outstandingLiability($operator);
        $providerBalance = $this->providerBalance($operator);
        $vocabulary = $this->vocabulary();

        return new CockpitHeaderReadModelData(
            balances: [
                $internalBalance['metric'],
                $outstandingLiability['metric'],
                $this->issuanceCapacity(
                    $internalBalance['minor'],
                    $outstandingLiability['minor'],
                    $providerBalance['minor'],
                    $providerBalance['fresh'],
                ),
                $providerBalance['metric'],
            ],
            vocabulary: $vocabulary,
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

    /**
     * @return array{metric: CockpitDashboardMetricData, minor: ?int}
     */
    private function internalBalance(mixed $operator): array
    {
        if ($operator === null) {
            return [
                'metric' => $this->disconnectedInternalBalance(),
                'minor' => null,
            ];
        }

        try {
            $positionBalanceMinor = ($this->accountBalances
                ?? app(AccountBalanceReadModelContract::class))
                ->balanceMinor($operator, $this->currency());

            if ($positionBalanceMinor !== null) {
                return [
                    'metric' => new CockpitDashboardMetricData(
                        key: 'internal',
                        label: $this->termLabel('internal_balance'),
                        value: $this->formatMoney($positionBalanceMinor),
                        helper: $this->termDescription('internal_balance'),
                        tone: 'healthy',
                    ),
                    'minor' => $positionBalanceMinor,
                ];
            }

            $wallet = $this->wallets->resolveForUser($operator);
            $balanceMinor = $this->normalizeWalletBalanceMinor($this->wallets->getBalance($wallet));

            return [
                'metric' => new CockpitDashboardMetricData(
                    key: 'internal',
                    label: $this->termLabel('internal_balance'),
                    value: $this->formatMoney($balanceMinor),
                    helper: 'Read from the authenticated account balance compatibility bridge.',
                    tone: 'healthy',
                ),
                'minor' => $balanceMinor,
            ];
        } catch (Throwable) {
            return [
                'metric' => $this->disconnectedInternalBalance(),
                'minor' => null,
            ];
        }
    }

    /**
     * @return array{metric: CockpitDashboardMetricData, minor: ?int, fresh: bool}
     */
    private function providerBalance(mixed $operator): array
    {
        if (! (bool) config('x-change.cockpit.header_provider_balance.enabled', true) || $operator === null) {
            return [
                'metric' => $this->disconnectedProviderBalance(),
                'minor' => null,
                'fresh' => false,
            ];
        }

        try {
            $overview = ($this->fundingOverview ?? app(BuildBalanceOverview::class))
                ->handle($operator, null, false);
            $balance = $this->providerBalanceFromOverview($overview);

            if ($balance === null) {
                return [
                    'metric' => $this->disconnectedProviderBalance(),
                    'minor' => null,
                    'fresh' => false,
                ];
            }

            $value = $this->providerBalanceMinor($balance);

            if ($value === null) {
                return [
                    'metric' => $this->disconnectedProviderBalance(
                        $this->stringValue($balance['sync_message'] ?? null),
                        $this->providerBalanceLabel($balance),
                    ),
                    'minor' => null,
                    'fresh' => false,
                ];
            }

            $isFresh = ! (bool) ($balance['is_stale'] ?? true);

            return [
                'metric' => new CockpitDashboardMetricData(
                    key: 'live',
                    label: $this->providerBalanceLabel($balance),
                    value: $this->formatMoney($value),
                    helper: $this->providerBalanceHelper($balance),
                    tone: $isFresh ? 'healthy' : 'warning',
                ),
                'minor' => $value,
                'fresh' => $isFresh,
            ];
        } catch (Throwable) {
            return [
                'metric' => $this->disconnectedProviderBalance(),
                'minor' => null,
                'fresh' => false,
            ];
        }
    }

    /**
     * @return array{metric: CockpitDashboardMetricData, minor: ?int}
     */
    private function outstandingLiability(mixed $operator): array
    {
        if ($operator === null) {
            return [
                'metric' => new CockpitDashboardMetricData(
                    key: 'outstanding',
                    label: $this->termLabel('outstanding_pay_codes'),
                    value: 'Not connected',
                    helper: 'No operator is available for liability summary.',
                    tone: 'neutral',
                ),
                'minor' => null,
            ];
        }

        try {
            $summary = ($this->liabilities ?? app(VoucherLiabilitySummaryContract::class))
                ->forIssuer($operator);

            if ($summary->status !== 'available') {
                return $this->disconnectedOutstandingLiability();
            }

            return [
                'metric' => new CockpitDashboardMetricData(
                    key: 'outstanding',
                    label: $this->termLabel('outstanding_pay_codes'),
                    value: $this->formatMoney($summary->outstanding_liability_minor),
                    helper: $this->termDescription('outstanding_pay_codes'),
                    tone: $summary->outstanding_liability_minor > 0 ? 'warning' : 'healthy',
                ),
                'minor' => max(0, $summary->outstanding_liability_minor),
            ];
        } catch (Throwable) {
            return $this->disconnectedOutstandingLiability();
        }
    }

    private function issuanceCapacity(
        ?int $internalBalanceMinor,
        ?int $outstandingLiabilityMinor,
        ?int $providerLiquidityMinor,
        bool $providerLiquidityIsFresh,
    ): CockpitDashboardMetricData {
        if (
            $internalBalanceMinor === null
            || $outstandingLiabilityMinor === null
            || $providerLiquidityMinor === null
            || ! $providerLiquidityIsFresh
        ) {
            return new CockpitDashboardMetricData(
                key: 'issuance',
                label: $this->termLabel('issuance_capacity'),
                value: 'Not available',
                helper: 'Requires Internal Balance, Outstanding Pay Codes, and a fresh cached provider liquidity snapshot.',
                tone: 'neutral',
            );
        }

        $issuanceCapacityMinor = max(
            0,
            min($internalBalanceMinor, $providerLiquidityMinor) - $outstandingLiabilityMinor,
        );

        return new CockpitDashboardMetricData(
            key: 'issuance',
            label: $this->termLabel('issuance_capacity'),
            value: $this->formatMoney($issuanceCapacityMinor),
            helper: $this->termDescription('issuance_capacity'),
            tone: $issuanceCapacityMinor > 0 ? 'healthy' : 'warning',
        );
    }

    private function disconnectedProviderBalance(
        ?string $helper = null,
        ?string $label = null,
    ): CockpitDashboardMetricData {
        return new CockpitDashboardMetricData(
            key: 'live',
            label: $label ?? $this->termLabel('provider_liquidity'),
            value: 'Not available',
            helper: $helper ?? 'No cached provider liquidity snapshot is available.',
            tone: 'neutral',
        );
    }

    private function disconnectedInternalBalance(): CockpitDashboardMetricData
    {
        return new CockpitDashboardMetricData(
            key: 'internal',
            label: $this->termLabel('internal_balance'),
            value: 'Internal balance not connected',
            helper: 'No recognized client-funds position is available for this view.',
            tone: 'neutral',
        );
    }

    /**
     * @return array{metric: CockpitDashboardMetricData, minor: null}
     */
    private function disconnectedOutstandingLiability(): array
    {
        return [
            'metric' => new CockpitDashboardMetricData(
                key: 'outstanding',
                label: $this->termLabel('outstanding_pay_codes'),
                value: 'Not connected',
                helper: 'Voucher liability summary is unavailable.',
                tone: 'neutral',
            ),
            'minor' => null,
        ];
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
            'provider_wallet' => 'Provider Account Liquidity',
            default => $this->termLabel('provider_liquidity'),
        };
    }

    /**
     * @param  array<string, mixed>  $balance
     */
    private function providerBalanceMinor(array $balance): ?int
    {
        $value = $this->stringValue($balance['key'] ?? null) === 'netbank_source_account'
            ? ($balance['available_balance_minor'] ?? $balance['balance_minor'] ?? null)
            : ($balance['balance_minor'] ?? $balance['available_balance_minor'] ?? null);

        return is_numeric($value) ? (int) round((float) $value) : null;
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

    private function normalizeWalletBalanceMinor(int|float|string $balance): int
    {
        if (is_int($balance)) {
            return $balance;
        }

        if (is_string($balance) && preg_match('/^-?\d+$/', trim($balance)) === 1) {
            return (int) trim($balance);
        }

        return (int) round(((float) $balance) * 100);
    }

    private function currency(): string
    {
        return (string) config(
            'x-change.pricing.currency',
            config('x-change.product.default_currency', 'PHP'),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function vocabulary(): array
    {
        return $this->resolvedVocabulary ??= ($this->vocabulary
            ?? app(TreasuryVocabularyReadModelContract::class))->terms([
                'internal_balance',
                'outstanding_pay_codes',
                'issuance_capacity',
                'provider_liquidity',
                'account_funding',
                'reusable_funding_address',
                'provider_account',
                'exact_funding_intent',
            ]);
    }

    private function termLabel(string $key): string
    {
        return (string) ($this->vocabulary()[$key]['label'] ?? $key);
    }

    private function termDescription(string $key): string
    {
        return (string) ($this->vocabulary()[$key]['description'] ?? '');
    }
}
