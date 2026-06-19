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
        'mobile' => '09173011987',
        'country' => 'PH',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09173011987',
        'amount' => '75',
        'slice_ids' => ['slice_1'],
        'settlement_rail' => 'INSTAPAY',
        'inputs' => [
            'mobile' => '09173011987',
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
    ]);
});
