<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Contracts\MinimumWithdrawalPolicyResolverContract;
use LBHurtado\XChange\Data\MinimumWithdrawalPolicyData;

class ConfigMinimumWithdrawalPolicyResolver implements MinimumWithdrawalPolicyResolverContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolve(array $payload = []): MinimumWithdrawalPolicyData
    {
        $currency = $this->currency($payload);
        $provider = $this->provider($payload);
        $settlementRail = $this->settlementRail($payload);
        $issuerDefaultMinimum = $this->configuredAmount('x-change.minimum_withdrawal.default', $currency, 25.00) ?? 25.00;
        $providerMinimum = $provider !== null
            ? $this->configuredAmount("x-change.minimum_withdrawal.providers.{$provider}", $currency)
            : null;
        $railMinimum = $settlementRail !== null
            ? $this->configuredAmount("x-change.minimum_withdrawal.rails.{$settlementRail}", $currency)
            : null;
        $operatorMinimum = $this->numeric(data_get($payload, 'cash.min_withdrawal'));
        $effectiveMinimum = max(array_filter([
            $issuerDefaultMinimum,
            $providerMinimum,
            $railMinimum,
            $operatorMinimum,
        ], static fn (?float $value): bool => $value !== null));

        return new MinimumWithdrawalPolicyData(
            currency: $currency,
            issuer_default_minimum: $issuerDefaultMinimum,
            provider_minimum: $providerMinimum,
            rail_minimum: $railMinimum,
            operator_minimum: $operatorMinimum,
            effective_minimum: $effectiveMinimum,
            source: $this->source(
                effectiveMinimum: $effectiveMinimum,
                issuerDefaultMinimum: $issuerDefaultMinimum,
                providerMinimum: $providerMinimum,
                railMinimum: $railMinimum,
                operatorMinimum: $operatorMinimum,
            ),
            provider: $provider,
            settlement_rail: $settlementRail,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function assertIssuancePayload(array $payload): void
    {
        $policy = $this->resolve($payload);
        $floor = $policy->floorWithoutOperator();
        $operatorMinimum = $policy->operator_minimum;

        if ($operatorMinimum !== null && $operatorMinimum + 0.0001 < $floor) {
            throw ValidationException::withMessages([
                'cash.min_withdrawal' => sprintf(
                    'Minimum withdrawal must be at least %s %s.',
                    $policy->currency,
                    $this->formatAmount($floor),
                ),
            ]);
        }

        $sliceMode = strtolower(trim((string) data_get($payload, 'cash.slice_mode', '')));
        $amount = $this->numeric(data_get($payload, 'cash.amount')) ?? 0.0;
        $slices = (int) (data_get($payload, 'cash.slices') ?? 0);

        if ($sliceMode === 'fixed' && $amount > 0 && $slices > 0) {
            $computedSliceAmount = floor(($amount / $slices) * 100) / 100;

            if ($computedSliceAmount + 0.0001 < $policy->effective_minimum) {
                throw ValidationException::withMessages([
                    'cash.slices' => sprintf(
                        '%d slices would create %s %s claims, below the %s %s minimum withdrawal.',
                        $slices,
                        $policy->currency,
                        $this->formatAmount($computedSliceAmount),
                        $policy->currency,
                        $this->formatAmount($policy->effective_minimum),
                    ),
                ]);
            }
        }

        $namedSlices = data_get($payload, 'metadata.slices');

        if (is_array($namedSlices)) {
            foreach (array_values($namedSlices) as $index => $slice) {
                $sliceAmount = is_array($slice) ? $this->numeric($slice['amount'] ?? null) : null;

                if ($sliceAmount !== null && $sliceAmount + 0.0001 < $policy->effective_minimum) {
                    throw ValidationException::withMessages([
                        "metadata.slices.{$index}.amount" => sprintf(
                            'Named slice amount must be at least %s %s.',
                            $policy->currency,
                            $this->formatAmount($policy->effective_minimum),
                        ),
                    ]);
                }
            }
        }
    }

    protected function currency(array $payload): string
    {
        $currency = strtoupper(trim((string) data_get(
            $payload,
            'cash.currency',
            config('x-change.pricing.currency', 'PHP'),
        )));

        return $currency === '' ? 'PHP' : $currency;
    }

    protected function provider(array $payload): ?string
    {
        $provider = data_get($payload, 'provider')
            ?? config('x-change.provider_runtime.default_provider')
            ?? config('x-change.provider_topologies.default');

        $provider = strtolower(trim((string) $provider));

        return $provider === '' ? null : $provider;
    }

    protected function settlementRail(array $payload): ?string
    {
        $rail = strtoupper(trim((string) data_get($payload, 'cash.settlement_rail', '')));

        return $rail === '' ? null : $rail;
    }

    protected function configuredAmount(string $key, string $currency, ?float $default = null): ?float
    {
        $configured = config($key);

        if (is_array($configured)) {
            return $this->numeric($configured[$currency] ?? $configured[strtolower($currency)] ?? null) ?? $default;
        }

        return $this->numeric($configured) ?? $default;
    }

    protected function numeric(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return $number >= 0 ? $number : null;
    }

    protected function source(
        float $effectiveMinimum,
        float $issuerDefaultMinimum,
        ?float $providerMinimum,
        ?float $railMinimum,
        ?float $operatorMinimum,
    ): string {
        if ($operatorMinimum !== null && abs($operatorMinimum - $effectiveMinimum) < 0.0001 && $operatorMinimum > max($issuerDefaultMinimum, $providerMinimum ?? 0, $railMinimum ?? 0)) {
            return 'operator';
        }

        if ($providerMinimum !== null && abs($providerMinimum - $effectiveMinimum) < 0.0001) {
            return 'provider';
        }

        if ($railMinimum !== null && abs($railMinimum - $effectiveMinimum) < 0.0001) {
            return 'settlement_rail';
        }

        return 'issuer_default';
    }

    protected function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
