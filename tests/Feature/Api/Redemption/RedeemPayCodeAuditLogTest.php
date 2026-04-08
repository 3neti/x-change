<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RedeemPayCode;

it('logs redeem requested and succeeded events', function () {
    $voucher = issueVoucher(validVoucherInstructions());
    $logger = fakeAuditLogger();

    $payload = [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'secret' => '1234',
        'inputs' => [
            'name' => 'Juan Dela Cruz',
        ],
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ];

    $result = new \LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData(
        voucher_code: $voucher->code,
        redeemed: true,
        status: 'redeemed',
        redeemer: [
            'mobile' => '09171234567',
            'country' => 'PH',
        ],
        bank_account: [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
        inputs: [
            'name' => 'Juan Dela Cruz',
        ],
        disbursement: [
            'status' => 'requested',
        ],
        messages: ['Voucher redeemed successfully.'],
    );

    $action = Mockery::mock(RedeemPayCode::class);
    $action->shouldReceive('handle')
        ->once()
        ->andReturn($result);

    $this->app->instance(RedeemPayCode::class, $action);

    $this->postJson(
        xchangeApi('pay-codes/'.$voucher->code.'/redeem'),
        $payload,
    )->assertOk();

    expect($logger->hasEvent('pay_code.redeem.requested'))->toBeTrue();
    expect($logger->hasEvent('pay_code.redeem.succeeded'))->toBeTrue();
});

it('logs redeem failed event when redemption throws', function () {
    $voucher = issueVoucher(validVoucherInstructions());
    $logger = fakeAuditLogger();

    $payload = [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'secret' => '1234',
        'inputs' => [
            'name' => 'Juan Dela Cruz',
        ],
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ];

    $action = Mockery::mock(RedeemPayCode::class);
    $action->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Redemption exploded.'));

    $this->app->instance(RedeemPayCode::class, $action);

    $this->postJson(
        xchangeApi('pay-codes/'.$voucher->code.'/redeem'),
        $payload,
    )->assertStatus(500);

    expect($logger->hasEvent('pay_code.redeem.requested'))->toBeTrue();
    expect($logger->hasEvent('pay_code.redeem.failed'))->toBeTrue();
});
