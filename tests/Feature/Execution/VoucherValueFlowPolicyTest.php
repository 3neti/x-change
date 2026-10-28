<?php

declare(strict_types=1);

use LBHurtado\Voucher\Enums\VoucherType;
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
    $voucher->voucher_type = VoucherType::PAYABLE;
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

it('ignores value flow entirely for ordinary disbursable vouchers', function (mixed $flow): void {
    $voucher = new Voucher;
    $voucher->voucher_type = VoucherType::REDEEMABLE;
    $voucher->metadata = ['instructions' => ['execution' => ['metadata' => ['value_flow' => $flow]]]];
    expect(app(VoucherValueFlowPolicy::class)->source($voucher))->toBeNull()
        ->and(app(VoucherValueFlowPolicy::class)->destination($voucher))->toBeNull();
})->with(['malformed' => ['bad-flow'], 'explicit' => [['destination' => 'instapay.gcash.mobile:09179999999']]]);

it('honors stored value instructions despite the redeemable voucher type', function (): void {
    $voucher = new Voucher;
    $voucher->voucher_type = VoucherType::REDEEMABLE;
    $voucher->metadata = ['instructions' => ['execution' => ['driver' => 'stored_value', 'metadata' => [
        'value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999'],
    ]]]];
    expect(app(VoucherValueFlowPolicy::class)->destination($voucher))->toBe('instapay.gcash.mobile:09179999999');
});
