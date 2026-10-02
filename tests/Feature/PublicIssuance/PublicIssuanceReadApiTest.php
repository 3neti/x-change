<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;

beforeEach(function (): void {
    config()->set('app.url', 'https://x-change.example.test');
    config()->set('x-change.public_auto_generate.enabled', true);
    config()->set('x-change.public_auto_generate.minimum_principal_minor', 100);
    config()->set('x-change.public_auto_generate.maximum_principal_minor', 100_000);
    config()->set('x-change.public_auto_generate.currencies', ['PHP']);
});

it('publishes channel-neutral public issuance discovery', function (): void {
    $this->getJson(route('x-change.api.public-issuance.discovery'))
        ->assertOk()
        ->assertJsonPath('schema', 'x-change.public-issuance-discovery.v1')
        ->assertJsonPath('available', true)
        ->assertJsonPath('issuer_model', 'commissioned_commercial_principal')
        ->assertJsonPath('registration_required', false)
        ->assertJsonPath('creates_order', false)
        ->assertJsonPath('supported.voucher_type', 'redeemable');

    $this->getJson(route('x-change.public-issuance.discovery'))
        ->assertOk()
        ->assertJsonPath('service', 'on_demand_pay_code_issuance');
});

it('returns an authoritative estimate without creating financial state', function (): void {
    $action = Mockery::mock(EstimatePayCodeCost::class);
    $action->shouldReceive('handle')->once()->with(Mockery::on(function (array $payload): bool {
        expect((float) data_get($payload, 'cash.amount'))->toBe(25.0)
            ->and(data_get($payload, 'cash.currency'))->toBe('PHP')
            ->and(data_get($payload, 'voucher_type'))->toBe('redeemable')
            ->and(data_get($payload, 'count'))->toBe(1);

        return true;
    }))->andReturn(new PricingEstimateData(
        currency: 'PHP',
        components: ['transaction' => 15.0],
        total: 15.0,
        pay_code_value: 25.0,
        account_debit: 40.0,
        billing_mode: 'live',
        customer_charge_minor: 1500,
        customer_charge: 15.0,
    ));
    $this->app->instance(EstimatePayCodeCost::class, $action);

    $this->postJson(route('x-change.api.public-issuance.estimate'), [
        'amount_minor' => 2500,
        'currency' => 'PHP',
    ])->assertOk()
        ->assertJsonPath('principal_minor', 2500)
        ->assertJsonPath('service_fees_minor', 1500)
        ->assertJsonPath('total_required_minor', 4000)
        ->assertJsonPath('creates_order', false);

    expect(Voucher::query()->count())->toBe(0)
        ->and(PayCodeIssuanceFundingOrder::query()->count())->toBe(0);
});

it('prepares a browser handoff without creating an order', function (): void {
    $action = Mockery::mock(EstimatePayCodeCost::class);
    $action->shouldReceive('handle')->once()->andReturn(new PricingEstimateData(
        currency: 'PHP',
        total: 15.0,
        pay_code_value: 25.0,
        account_debit: 40.0,
        billing_mode: 'live',
        customer_charge_minor: 1500,
        customer_charge: 15.0,
    ));
    $this->app->instance(EstimatePayCodeCost::class, $action);

    $this->postJson(route('x-change.api.public-issuance.handoff'), [
        'amount_minor' => 2500,
        'currency' => 'PHP',
    ])->assertOk()
        ->assertJsonPath('method', 'GET')
        ->assertJsonPath('creates_order', false)
        ->assertJsonPath('url', 'http://localhost/x/auto-generate?amount=25.00&currency=PHP');

    expect(Voucher::query()->count())->toBe(0)
        ->and(PayCodeIssuanceFundingOrder::query()->count())->toBe(0);
});

it('rejects unsupported and out-of-range estimate inputs', function (): void {
    $this->postJson(route('x-change.api.public-issuance.estimate'), [
        'amount_minor' => 99,
        'currency' => 'USD',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['amount_minor', 'currency']);
});
