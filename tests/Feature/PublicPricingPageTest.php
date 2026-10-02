<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Contracts\PricelistServiceContract;
use LBHurtado\XChange\Data\PricingEstimateData;

it('renders the public price list from the active commercial offering', function (): void {
    config()->set('x-change.commercial.billing.mode', 'informational');

    $pricelist = Mockery::mock(PricelistServiceContract::class);
    $pricelist->shouldReceive('showPricelist')->once()->andReturn([
        'name' => 'pay-code',
        'currency' => 'PHP',
        'items' => [
            [
                'code' => 'cash.amount',
                'name' => 'Transaction Fee',
                'category' => 'base',
                'amount_minor' => 1_500,
                'amount' => 15.0,
                'currency' => 'PHP',
                'active' => true,
            ],
            [
                'code' => 'legacy.disabled',
                'name' => 'Deprecated Fee',
                'category' => 'base',
                'amount_minor' => 0,
                'amount' => 0.0,
                'currency' => 'PHP',
                'active' => false,
            ],
            [
                'code' => 'rider.url',
                'name' => 'Redirect URL',
                'category' => 'rider',
                'amount_minor' => 5_000,
                'amount' => 50.0,
                'currency' => 'PHP',
                'active' => true,
            ],
        ],
        'commercial_offering' => [
            'reference' => 'pay-code-default',
            'version' => 1,
            'snapshot_hash' => str_repeat('a', 64),
            'effective_at' => '1970-01-01T00:00:00+00:00',
        ],
        'catalog' => ['reference' => 'pay-code', 'version' => 3],
    ]);
    $this->app->instance(PricelistServiceContract::class, $pricelist);

    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->times(3)->andReturn(
        new PricingEstimateData(total: 15.0, pay_code_value: 50.0, account_debit: 50.0),
        new PricingEstimateData(total: 20.0, pay_code_value: 50.0, account_debit: 50.0),
        new PricingEstimateData(total: 65.0, pay_code_value: 50.0, account_debit: 50.0),
    );
    $this->app->instance(EstimatePayCodeCost::class, $estimate);

    $this->withHeaders([
        'X-Inertia' => 'true',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get(route('x-change.pricing.show'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/public/Pricing')
        ->assertJsonPath('props.pricing.available', true)
        ->assertJsonPath('props.pricing.billing.mode', 'informational')
        ->assertJsonPath('props.pricing.billing.customer_charging_active', false)
        ->assertJsonCount(2, 'props.pricing.groups')
        ->assertJsonPath('props.pricing.groups.0.key', 'base')
        ->assertJsonCount(1, 'props.pricing.groups.0.items')
        ->assertJsonPath('props.pricing.groups.0.items.0.name', 'Transaction Fee')
        ->assertJsonPath('props.pricing.examples.1.service_fees_minor', 2_000)
        ->assertJsonPath('props.pricing.examples.1.amount_due_minor', 5_000)
        ->assertJsonPath('props.pricing.provenance.catalog_version', 3)
        ->assertJsonPath('props.public_navigation.pricing_url', '/x/pricing')
        ->assertJsonPath('props.public_navigation.create_url', '/x/auto-generate');
});

it('fails closed when the active commercial offering cannot be resolved', function (): void {
    $pricelist = Mockery::mock(PricelistServiceContract::class);
    $pricelist->shouldReceive('showPricelist')->once()->andThrow(new RuntimeException('private configuration detail'));
    $this->app->instance(PricelistServiceContract::class, $pricelist);

    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldNotReceive('handle');
    $this->app->instance(EstimatePayCodeCost::class, $estimate);

    $this->withHeaders([
        'X-Inertia' => 'true',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get(route('x-change.pricing.show'))
        ->assertOk()
        ->assertJsonPath('props.pricing.available', false)
        ->assertJsonPath('props.pricing.groups', [])
        ->assertJsonPath('props.pricing.examples', [])
        ->assertJsonMissing(['private configuration detail']);
});

it('identifies when live customer charging is active', function (): void {
    config()->set('x-change.commercial.billing.mode', 'billable');

    $pricelist = Mockery::mock(PricelistServiceContract::class);
    $pricelist->shouldReceive('showPricelist')->once()->andReturn([
        'currency' => 'PHP',
        'items' => [],
        'commercial_offering' => ['reference' => 'pay-code-default', 'version' => 2],
        'catalog' => ['reference' => 'pay-code', 'version' => 3],
    ]);
    $this->app->instance(PricelistServiceContract::class, $pricelist);

    $estimate = Mockery::mock(EstimatePayCodeCost::class);
    $estimate->shouldReceive('handle')->times(3)->andReturn(
        new PricingEstimateData(total: 15.0, pay_code_value: 50.0, account_debit: 65.0),
        new PricingEstimateData(total: 20.0, pay_code_value: 50.0, account_debit: 70.0),
        new PricingEstimateData(total: 65.0, pay_code_value: 50.0, account_debit: 115.0),
    );
    $this->app->instance(EstimatePayCodeCost::class, $estimate);

    $this->withHeaders([
        'X-Inertia' => 'true',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get(route('x-change.pricing.show'))
        ->assertOk()
        ->assertJsonPath('props.pricing.billing.mode', 'billable')
        ->assertJsonPath('props.pricing.billing.customer_charging_active', true)
        ->assertJsonPath('props.pricing.examples.1.amount_due_minor', 7_000);
});
