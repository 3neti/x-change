<?php

declare(strict_types=1);

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Contracts\PricingServiceContract;
use LBHurtado\XChange\Services\InstructionBackedPricingService;

it('uses the immutable x-commerce catalog for Pay Code pricing and projections', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100.00,
            'currency' => 'PHP',
            'validation' => [
                'secret' => 'secret',
                'mobile' => null,
                'payable' => null,
            ],
        ],
        'inputs' => [
            'fields' => ['selfie', 'signature'],
        ],
        'feedback' => [
            'email' => 'recipient@example.test',
            'mobile' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => 'Thank you',
            'url' => null,
            'splash' => null,
        ],
        'count' => 1,
    ]);

    $service = app(PricingServiceContract::class);
    $first = $service->estimate($instructions);
    $second = $service->estimate($instructions);

    expect($service)->toBeInstanceOf(InstructionBackedPricingService::class)
        ->and($first)->toBe($second)
        ->and($first['currency'])->toBe('PHP')
        ->and($first['total_minor'])->toBe(4_150)
        ->and($first['total'])->toBe(41.5)
        ->and(collect($first['charges'])->pluck('index')->all())->toBe([
            'cash.amount',
            'inputs.fields.selfie',
            'inputs.fields.signature',
            'inputs.fields.kyc',
            'feedback.email',
            'cash.validation.secret',
            'rider.message',
        ])
        ->and($first['catalog_reference'])->toBe('pay-code')
        ->and($first['catalog_version'])->toBe(2)
        ->and($first['waterfall_policy_reference'])->toBe('pay-code-commercial-waterfall')
        ->and($first['waterfall_policy_version'])->toBe(1)
        ->and($first['commercial_quote_reference'])->toStartWith('commercial-quote:');
});

it('prices a collectible instruction without treating its target as outbound cash', function () {
    $instructions = validVoucherInstructions(0, 'INSTAPAY', [
        'metadata' => [
            'flow_type' => 'collectible',
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
    ]);

    $estimate = app(PricingServiceContract::class)->estimate($instructions);

    expect($estimate['total_minor'])->toBe(1_500)
        ->and($estimate['charges'])->toHaveCount(1)
        ->and($estimate['charges'][0]['index'])->toBe('cash.amount')
        ->and($estimate['charges'][0]['catalog_item_reference'])
        ->toBe('flow_type.collectible');
});

it('prices every Pay Code in a batch with the same catalog quantities', function () {
    $instructions = validVoucherInstructions(100.00, 'INSTAPAY', [
        'count' => 3,
        'inputs' => ['fields' => []],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
    ]);

    $estimate = app(PricingServiceContract::class)->estimate($instructions);

    expect($estimate['total_minor'])->toBe(4_500)
        ->and($estimate['charges'])->toHaveCount(1)
        ->and($estimate['charges'][0]['quantity'])->toBe(3)
        ->and($estimate['charges'][0]['price_minor'])->toBe(4_500);
});
