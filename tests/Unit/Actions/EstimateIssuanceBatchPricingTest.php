<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\IssuanceBatch\EstimateIssuanceBatchPricing;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Data\PricingEstimateData;

it('includes each batch row payload when estimating the payment total', function () {
    $estimator = Mockery::mock(EstimatePayCodeCost::class);
    $estimator->shouldReceive('handle')
        ->once()
        ->with(Mockery::on(function (array $payload): bool {
            expect($payload['cash'])->toBe([
                'amount' => 100,
                'currency' => 'PHP',
            ])
                ->and($payload['inputs']['fields'])->toBe(['mobile', 'otp'])
                ->and($payload['rider']['message'])->toBe('Transfer')
                ->and($payload['voucher_type'])->toBe('redeemable')
                ->and($payload['metadata']['flow_type'])->toBe('disbursable');

            return true;
        }))
        ->andReturn(new PricingEstimateData(
            currency: 'PHP',
            total: 19.5,
            pay_code_value: 100,
            account_debit: 119.75,
        ));

    $pricing = new EstimateIssuanceBatchPricing($estimator);
    $result = $pricing->handle(collect([
        (object) [
            'amount_minor' => 10_000,
            'payload' => [
                'inputs' => ['fields' => ['mobile', 'otp']],
                'rider' => ['message' => 'Transfer'],
            ],
        ],
    ]));

    expect($result->total)->toBe(19.5)
        ->and($result->account_debit)->toBe(119.75)
        ->and($result->customer_charge_minor)->toBe(1950);
});
