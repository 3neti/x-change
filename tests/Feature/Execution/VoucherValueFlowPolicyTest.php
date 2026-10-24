<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\Execution\VoucherValueFlowPolicy;

it('uses existing voucher behavior when value flow is absent', function () {
    $voucher = new Voucher;
    $voucher->metadata = ['instructions' => ['execution' => ['metadata' => []]]];
    $policy = app(VoucherValueFlowPolicy::class);

    expect($policy->source($voucher))->toBeNull()
        ->and($policy->destination($voucher))->toBeNull();
});

it('rejects value flow fields that execution does not support', function () {
    $voucher = new Voucher;
    $voucher->metadata = [
        'instructions' => [
            'execution' => [
                'metadata' => [
                    'value_flow' => [
                        'destination' => 'account.mobile:09173011987.client_funds',
                        'amount_basis' => 'collection_amount',
                    ],
                ],
            ],
        ],
    ];

    expect(fn () => app(VoucherValueFlowPolicy::class)->destination($voucher))
        ->toThrow(InvalidArgumentException::class, 'supports only source and destination');
});
