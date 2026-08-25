<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;

it('does not silently supply a collection wallet for payable vouchers', function (): void {
    $issuer = actingAsTestUser();

    app(PayCodeIssuanceContract::class)->issue($issuer, [
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
})->throws(ValidationException::class);

