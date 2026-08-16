<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Contracts\CockpitHeaderReadModelProviderContract;
use LBHurtado\XChange\Contracts\MinimumWithdrawalPolicyResolverContract;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardMetricData;
use LBHurtado\XChange\Data\Cockpit\CockpitHeaderReadModelData;
use LBHurtado\XChange\Data\MinimumWithdrawalPolicyData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Enums\CockpitEntryDestination;
use LBHurtado\XChange\Services\Cockpit\CockpitEntryDestinationResolver;

function cockpitEntryHeader(?int $clientFundsMinor, ?int $issuanceCapacityMinor): CockpitHeaderReadModelData
{
    return new CockpitHeaderReadModelData(
        balances: [
            new CockpitDashboardMetricData('internal', 'Client Funds', '—', amount_minor: $clientFundsMinor),
            new CockpitDashboardMetricData('issuance', 'Issuance Capacity', '—', amount_minor: $issuanceCapacityMinor),
        ],
    );
}

function cockpitEntryPolicy(float $minimum = 25.00): MinimumWithdrawalPolicyData
{
    return new MinimumWithdrawalPolicyData(
        currency: 'PHP',
        issuer_default_minimum: $minimum,
        provider_minimum: null,
        rail_minimum: null,
        operator_minimum: null,
        effective_minimum: $minimum,
        source: 'issuer_default',
        provider: 'netbank',
        settlement_rail: null,
    );
}

function cockpitEntryResolver(
    CockpitHeaderReadModelData $header,
    ?PricingEstimateData $estimate = null,
): CockpitEntryDestinationResolver {
    $headers = Mockery::mock(CockpitHeaderReadModelProviderContract::class);
    $headers->shouldReceive('forOperator')->once()->andReturn($header);

    $minimums = Mockery::mock(MinimumWithdrawalPolicyResolverContract::class);
    $minimums->shouldReceive('resolve')->once()->withNoArgs()->andReturn(cockpitEntryPolicy());

    $estimator = Mockery::mock(EstimatePayCodeCost::class);
    $estimator->shouldReceive('handle')
        ->once()
        ->with(Mockery::on(fn (array $payload): bool => data_get($payload, 'cash.amount') === 25.0))
        ->andReturn($estimate ?? new PricingEstimateData(
            currency: 'PHP',
            pay_code_value: 25.00,
            account_debit: 30.00,
        ));

    return new CockpitEntryDestinationResolver($headers, $minimums, $estimator);
}

it('opens Issuance when capacity covers principal and client funds cover fees', function (): void {
    $resolver = cockpitEntryResolver(cockpitEntryHeader(3_000, 2_500));

    expect($resolver->resolve(new stdClass))->toBe(CockpitEntryDestination::Issuance);
});

it('routes to Funding when client funds are one minor unit short of fees', function (): void {
    $resolver = cockpitEntryResolver(cockpitEntryHeader(2_999, 2_500));

    expect($resolver->resolve(new stdClass))->toBe(CockpitEntryDestination::Funding);
});

it('routes to Funding before pricing when capacity is one minor unit short of principal', function (): void {
    $headers = Mockery::mock(CockpitHeaderReadModelProviderContract::class);
    $headers->shouldReceive('forOperator')->once()->andReturn(cockpitEntryHeader(3_000, 2_499));

    $minimums = Mockery::mock(MinimumWithdrawalPolicyResolverContract::class);
    $minimums->shouldReceive('resolve')->once()->withNoArgs()->andReturn(cockpitEntryPolicy());

    $estimator = Mockery::mock(EstimatePayCodeCost::class);
    $estimator->shouldNotReceive('handle');

    $resolver = new CockpitEntryDestinationResolver($headers, $minimums, $estimator);

    expect($resolver->resolve(new stdClass))->toBe(CockpitEntryDestination::Funding);
});

it('fails closed to Funding when a required balance is unavailable', function (): void {
    $headers = Mockery::mock(CockpitHeaderReadModelProviderContract::class);
    $headers->shouldReceive('forOperator')->once()->andReturn(cockpitEntryHeader(3_000, null));

    $minimums = Mockery::mock(MinimumWithdrawalPolicyResolverContract::class);
    $minimums->shouldNotReceive('resolve');

    $estimator = Mockery::mock(EstimatePayCodeCost::class);
    $estimator->shouldNotReceive('handle');

    $resolver = new CockpitEntryDestinationResolver($headers, $minimums, $estimator);

    expect($resolver->resolve(new stdClass))->toBe(CockpitEntryDestination::Funding);
});

it('fails closed to Funding when pricing cannot be established safely', function (): void {
    $headers = Mockery::mock(CockpitHeaderReadModelProviderContract::class);
    $headers->shouldReceive('forOperator')->once()->andReturn(cockpitEntryHeader(3_000, 2_500));

    $minimums = Mockery::mock(MinimumWithdrawalPolicyResolverContract::class);
    $minimums->shouldReceive('resolve')->once()->withNoArgs()->andReturn(cockpitEntryPolicy());

    $estimator = Mockery::mock(EstimatePayCodeCost::class);
    $estimator->shouldReceive('handle')->once()->andThrow(new RuntimeException('pricing unavailable'));

    $resolver = new CockpitEntryDestinationResolver($headers, $minimums, $estimator);

    expect($resolver->resolve(new stdClass))->toBe(CockpitEntryDestination::Funding);
});
