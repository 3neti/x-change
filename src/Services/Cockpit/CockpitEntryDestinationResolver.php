<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Contracts\CockpitHeaderReadModelProviderContract;
use LBHurtado\XChange\Contracts\MinimumWithdrawalPolicyResolverContract;
use LBHurtado\XChange\Enums\CockpitEntryDestination;
use Throwable;

class CockpitEntryDestinationResolver
{
    public function __construct(
        private readonly CockpitHeaderReadModelProviderContract $headerReadModels,
        private readonly MinimumWithdrawalPolicyResolverContract $minimumWithdrawals,
        private readonly EstimatePayCodeCost $estimatePayCodeCost,
    ) {}

    public function resolve(mixed $operator): CockpitEntryDestination
    {
        if ($operator === null) {
            return CockpitEntryDestination::Funding;
        }

        try {
            $header = $this->headerReadModels->forOperator($operator);

            if ($header->status !== 'available' || ! $header->authorized) {
                return CockpitEntryDestination::Funding;
            }

            $balances = collect($header->balances)->keyBy(
                static fn ($metric): string => $metric->key,
            );
            $clientFundsMinor = $balances->get('internal')?->amount_minor;
            $issuanceCapacityMinor = $balances->get('issuance')?->amount_minor;

            if (! is_int($clientFundsMinor) || ! is_int($issuanceCapacityMinor)) {
                return CockpitEntryDestination::Funding;
            }

            $policy = $this->minimumWithdrawals->resolve();
            $principalMinor = $this->toMinorUnits($policy->effective_minimum);

            if ($principalMinor < 1 || $issuanceCapacityMinor < $principalMinor) {
                return CockpitEntryDestination::Funding;
            }

            $estimate = $this->estimatePayCodeCost->handle(
                $this->minimumPayCodePayload($policy->effective_minimum, $policy->currency),
            );

            if (
                $estimate->account_debit === null
                || strcasecmp($estimate->currency, $policy->currency) !== 0
            ) {
                return CockpitEntryDestination::Funding;
            }

            $requiredDebitMinor = $this->toMinorUnits($estimate->account_debit);

            return $requiredDebitMinor >= $principalMinor
                && $clientFundsMinor >= $requiredDebitMinor
                    ? CockpitEntryDestination::Issuance
                    : CockpitEntryDestination::Funding;
        } catch (Throwable) {
            return CockpitEntryDestination::Funding;
        }
    }

    private function toMinorUnits(float $amount): int
    {
        if (! is_finite($amount) || $amount < 0) {
            return -1;
        }

        return (int) round($amount * 100);
    }

    /**
     * @return array<string, mixed>
     */
    private function minimumPayCodePayload(float $amount, string $currency): array
    {
        return [
            'cash' => [
                'amount' => $amount,
                'currency' => $currency,
                'validation' => [],
            ],
            'inputs' => ['fields' => []],
            'feedback' => [
                'email' => null,
                'mobile' => null,
                'webhook' => null,
            ],
            'rider' => [
                'message' => null,
                'url' => null,
                'redirect_timeout' => null,
                'splash' => null,
                'splash_timeout' => null,
                'og_source' => null,
            ],
            'count' => 1,
            'prefix' => null,
            'mask' => '****',
            'ttl' => null,
            'metadata' => [],
        ];
    }
}
