<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Claim\BuildCompiledFormClaimPayload;
use LBHurtado\XChange\Data\PreparedCompiledClaimData;

it('builds compiled form claim payload from prepared compiled claim data', function () {
    $voucher = issueVoucher();

    $prepared = new PreparedCompiledClaimData(
        code: $voucher->code,
        voucherId: $voucher->getKey(),
        inputs: [
            'mobile' => '09173011987',
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
            'amount' => '75',
            'secret' => 'ABC123',
            'slice_ids' => ['slice_1'],
            'settlement_rail' => 'INSTAPAY',
        ],
    );

    $payload = app(BuildCompiledFormClaimPayload::class)->handle(
        voucher: $voucher,
        prepared: $prepared,
    );

    expect($payload)->toBe([
        'source' => 'compiled_form',
        'code' => $voucher->code,
        'voucher_id' => $voucher->getKey(),
        'secret' => 'ABC123',
        'mobile' => '09173011987',
        'country' => 'PH',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09173011987',
        'amount' => '75',
        'slice_ids' => ['slice_1'],
        'settlement_rail' => 'INSTAPAY',
        'destination' => [
            'bank_code' => 'GXCHPHM2XXX',
            'bank_name' => 'GCash',
            'bank_label' => 'GCash',
            'provider_icon_key' => 'wallet.gcash',
            'icon_asset' => '/vendor/x-change/images/payout-destinations/gcash-128.png',
            'settlement_rail' => 'INSTAPAY',
            'account_number_masked' => '*******1987',
            'route' => ['x-change', 'NetBank', 'InstaPay', 'GCash', '*******1987'],
            'route_icons' => [
                '/vendor/x-change/images/payout-destinations/x-change-128.png',
                '/vendor/x-change/images/payout-destinations/netbank-128.png',
                '/vendor/x-change/images/payout-destinations/rail-instapay-128.png',
                '/vendor/x-change/images/payout-destinations/gcash-128.png',
            ],
        ],
        'inputs' => [
            'mobile' => '09173011987',
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
    ]);
});
