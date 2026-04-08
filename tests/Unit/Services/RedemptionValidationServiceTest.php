<?php

declare(strict_types=1);

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Exceptions\RedemptionException;
use LBHurtado\XChange\Services\DefaultRedemptionValidationService;

it('passes validation for a basic voucher context', function () {
    $voucher = issueVoucher(validVoucherInstructions());

    $context = new RedemptionContext(
        mobile: '09171234567',
        secret: null,
        vendorAlias: null,
        inputs: [],
        bankAccount: [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    );

    $service = new DefaultRedemptionValidationService;

    expect(fn () => $service->validate($voucher, $context))->not->toThrow(RedemptionException::class);
});
