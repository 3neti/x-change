<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Contracts\PricingServiceContract;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Data\PricingEstimateData;

it('returns pricing estimate from voucher instructions input', function () {
    $input = [
        'cash' => [
            'amount' => 100.0,
            'currency' => 'PHP',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => [
            'fields' => [
                'selfie',
            ],
        ],
        'feedback' => [
            'email' => 'example@example.com',
            'mobile' => '09171234567',
            'webhook' => 'https://example.com/webhook',
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
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
        'metadata' => [],
    ];

    $estimate = [
        'currency' => 'PHP',
        'base_fee' => 0.0,
        'components' => [
            'selfie' => 5.0,
            'email_feedback' => 0.0,
            'sms_feedback' => 0.0,
            'webhook' => 0.0,
            'cash' => 0.0,
            'kyc' => 0.0,
            'otp' => 0.0,
            'signature' => 0.0,
            'location' => 0.0,
        ],
        'total' => 5.0,
    ];

    $pricing = Mockery::mock(PricingServiceContract::class);
    $pricing->shouldReceive('estimate')
        ->once()
        ->with(Mockery::on(function ($instructions) {
            expect($instructions)->toBeInstanceOf(VoucherInstructionsData::class);
            expect((float) data_get($instructions, 'cash.amount'))->toBe(100.0);

            return true;
        }))
        ->andReturn($estimate);

    $action = new EstimatePayCodeCost($pricing);

    $result = $action->handle($input);

    expect($result)->toBeInstanceOf(PricingEstimateData::class);
    expect($result->currency)->toBe('PHP');
    expect($result->base_fee)->toBe(0.0);
    expect($result->components['selfie'])->toBe(5.0);
    expect($result->total)->toBe(5.0);
});
