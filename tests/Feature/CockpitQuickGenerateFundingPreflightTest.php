<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use LBHurtado\XChange\Services\Funding\OnDemandIssuanceFundingPolicy;
use LBHurtado\XChange\Services\Funding\OnDemandIssuanceFundingRequirement;

beforeEach(function (): void {
    config()->set('x-change.legal.eula.enabled', false);
});

function quickGeneratePreflightPayload(): array
{
    return [
        'cash' => ['amount' => 25, 'currency' => 'PHP', 'validation' => []],
        'inputs' => ['fields' => [], 'requirements' => []],
        'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null, 'splash' => null],
        'metadata' => ['custom' => ['cockpit' => ['source' => 'cockpit.quick-generate', 'payee' => ['kind' => 'open', 'explicit_secret' => false]]]],
    ];
}

it('returns a read-only payment-required funding decision for quick generate', function (): void {
    $issuer = actingAsTestUser(0);
    config()->set('x-change.issuance_funding.on_demand.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.basis', 'full_amount');

    $this->app->instance(EstimatePayCodeCost::class, Mockery::mock(EstimatePayCodeCost::class, function ($mock): void {
        $mock->shouldReceive('handle')->once()->andReturn(new PricingEstimateData(
            currency: 'PHP',
            total: 20.0,
            pay_code_value: 100.0,
            account_debit: 120.0,
            components: ['base_fee' => 20.0],
        ));
    }));
    $this->app->instance(BuildBalanceOverview::class, Mockery::mock(BuildBalanceOverview::class, function ($mock) use ($issuer): void {
        $mock->shouldReceive('handle')->once()->with($issuer, null, false)->andReturn([
            'balances' => [[
                'key' => 'local_ledger',
                'balance_minor' => 10_000,
            ]],
        ]);
    }));
    $this->app->instance(OnDemandIssuanceFundingRequirement::class, new OnDemandIssuanceFundingRequirement(
        app(OnDemandIssuanceFundingPolicy::class),
        app(BuildBalanceOverview::class),
    ));

    $response = $this->postJson(
        route('x-change.cockpit.quick-generate.funding-preflight'),
        quickGeneratePreflightPayload(),
    );

    $response->assertSuccessful()
        ->assertJsonPath('schema', 'x-change.cockpit.issuance-funding-preflight.v1')
        ->assertJsonPath('funding.execution', 'payment_required')
        ->assertJsonPath('funding.external_amount_minor', 12000)
        ->assertJsonPath('funding.available_client_funds_minor', 10000);

    expect($response->json('funding.reason'))->toBe('external_funding_required');
});

it('returns a direct decision when on-demand funding is disabled', function (): void {
    $issuer = actingAsTestUser(10_000);
    config()->set('x-change.issuance_funding.on_demand.enabled', false);

    $this->app->instance(EstimatePayCodeCost::class, Mockery::mock(EstimatePayCodeCost::class, function ($mock): void {
        $mock->shouldReceive('handle')->once()->andReturn(new PricingEstimateData(
            currency: 'PHP',
            total: 1.0,
            pay_code_value: 25.0,
            account_debit: 26.0,
        ));
    }));
    $this->app->instance(BuildBalanceOverview::class, Mockery::mock(BuildBalanceOverview::class, function ($mock) use ($issuer): void {
        $mock->shouldReceive('handle')->twice()->with($issuer, null, false)->andReturn([
            'balances' => [[
                'key' => 'local_ledger',
                'balance_minor' => 10_000,
            ]],
        ]);
    }));
    $this->app->instance(OnDemandIssuanceFundingRequirement::class, new OnDemandIssuanceFundingRequirement(
        app(OnDemandIssuanceFundingPolicy::class),
        app(BuildBalanceOverview::class),
    ));

    $this->postJson(
        route('x-change.cockpit.quick-generate.funding-preflight'),
        quickGeneratePreflightPayload(),
    )->assertSuccessful()
        ->assertJsonPath('funding.execution', 'direct')
        ->assertJsonPath('funding.external_amount_minor', 0)
        ->assertJsonPath('funding.reason', 'client_funds_cover_required_debit');
});

it('returns a direct decision when the payer bears the issuance fee', function (): void {
    $issuer = actingAsTestUser(0);
    config()->set('x-change.issuance_funding.on_demand.enabled', true);
    config()->set('x-change.issuance_funding.on_demand.basis', 'full_amount');
    $this->app->instance(EstimatePayCodeCost::class, Mockery::mock(EstimatePayCodeCost::class, function ($mock): void {
        $mock->shouldReceive('handle')->once()->andReturn(new PricingEstimateData(currency: 'PHP', total: 15, pay_code_value: 0, account_debit: 0));
    }));
    $this->app->instance(BuildBalanceOverview::class, Mockery::mock(BuildBalanceOverview::class, function ($mock) use ($issuer): void {
        $mock->shouldReceive('handle')->once()->with($issuer, null, false)->andReturn(['balances' => []]);
    }));
    $this->app->instance(OnDemandIssuanceFundingRequirement::class, new OnDemandIssuanceFundingRequirement(
        app(OnDemandIssuanceFundingPolicy::class), app(BuildBalanceOverview::class),
    ));

    $payload = quickGeneratePreflightPayload();
    $payload['voucher_type'] = 'payable';
    $payload['target_amount'] = 100;
    $payload['fee_bearer'] = 'payer';
    $payload['cash']['amount'] = 0;
    $this->postJson(route('x-change.cockpit.quick-generate.funding-preflight'), $payload)
        ->assertSuccessful()
        ->assertJsonPath('funding.execution', 'direct')
        ->assertJsonPath('funding.required_amount_minor', 0)
        ->assertJsonPath('funding.external_amount_minor', 0);
});
