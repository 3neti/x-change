<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;

it('freezes the authenticated issuer platform wallet for payable vouchers', function (): void {
    $issuer = actingAsTestUser();
    $wallet = $issuer->wallet()->where('slug', 'platform')->sole();

    $result = app(PayCodeIssuanceContract::class)->issue($issuer, [
        'cash' => [
            'amount' => 0,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'PAY',
        'mask' => '****',
        'voucher_type' => 'payable',
        'target_amount' => 100,
        'metadata' => [
            'flow_type' => 'collectible',
        ],
    ]);

    $voucher = Voucher::query()->findOrFail($result['voucher_id']);

    expect(data_get($voucher->metadata, 'instructions.metadata.issuer_id'))
        ->toBe((string) $issuer->getAuthIdentifier())
        ->and(data_get($voucher->metadata, 'instructions.metadata.collection_wallet_id'))
        ->toBe((string) $wallet->getKey());
});
