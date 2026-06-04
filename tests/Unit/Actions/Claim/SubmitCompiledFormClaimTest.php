<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Claim\SubmitCompiledFormClaim;
use LBHurtado\XChange\Data\PreparedCompiledClaimData;

it('defines the compiled form claim submission contract', function () {
    $voucher = issueVoucher();

    $prepared = new PreparedCompiledClaimData(
        code: $voucher->code,
        voucherId: $voucher->getKey(),
        inputs: [
            'mobile' => '09173011987',
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
    );

    $result = app(SubmitCompiledFormClaim::class)->handle($voucher, $prepared);

    expect($result['voucher']->is($voucher))->toBeTrue()
        ->and($result['prepared'])->toBe($prepared)
        ->and($result['payload'])->toBe([
            'source' => 'compiled_form',
            'code' => $voucher->code,
            'voucher_id' => $voucher->getKey(),
            'inputs' => [
                'mobile' => '09173011987',
                'bank_code' => 'GXCHPHM2XXX',
                'account_number' => '09173011987',
            ],
        ]);
});
