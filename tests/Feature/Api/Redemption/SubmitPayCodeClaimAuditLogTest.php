<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;

it('logs claim submit requested and succeeded events for redeem path', function () {
    $voucher = issueVoucher(validVoucherInstructions());
    $logger = fakeAuditLogger();

    $payload = [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'secret' => '1234',
        'amount' => 100.00,
        'inputs' => [
            'name' => 'Juan Dela Cruz',
        ],
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ];

    $result = new SubmitPayCodeClaimResultData(
        voucher_code: $voucher->code,
        claim_type: 'redeem',
        claimed: true,
        status: 'redeemed',
        requested_amount: 100.00,
        disbursed_amount: null,
        currency: null,
        remaining_balance: null,
        fully_claimed: true,
        disbursement: [
            'status' => 'requested',
        ],
        messages: ['Voucher redeemed successfully.'],
    );

    $action = Mockery::mock(SubmitPayCodeClaim::class);
    $action->shouldReceive('handle')
        ->once()
        ->andReturn($result);

    $this->app->instance(SubmitPayCodeClaim::class, $action);

    $this->postJson(
        xchangeApi('pay-codes/'.$voucher->code.'/claim/submit'),
        $payload,
    )->assertOk();

    expect($logger->hasEvent('pay_code.claim.submit.requested'))->toBeTrue();
    expect($logger->hasEvent('pay_code.claim.submit.succeeded'))->toBeTrue();
});

it('logs claim submit requested and succeeded events for withdraw path', function () {
    $voucher = issueVoucher(validVoucherInstructions());
    $logger = fakeAuditLogger();

    $payload = [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'amount' => 200.00,
        'inputs' => [],
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ];

    $result = new SubmitPayCodeClaimResultData(
        voucher_code: $voucher->code,
        claim_type: 'withdraw',
        claimed: true,
        status: 'withdrawn',
        requested_amount: 200.00,
        disbursed_amount: 200.00,
        currency: 'PHP',
        remaining_balance: 300.00,
        fully_claimed: false,
        disbursement: [
            'status' => 'requested',
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
        messages: ['Voucher withdrawal successful.'],
    );

    $action = Mockery::mock(SubmitPayCodeClaim::class);
    $action->shouldReceive('handle')
        ->once()
        ->andReturn($result);

    $this->app->instance(SubmitPayCodeClaim::class, $action);

    $this->postJson(
        xchangeApi('pay-codes/'.$voucher->code.'/claim/submit'),
        $payload,
    )->assertOk();

    expect($logger->hasEvent('pay_code.claim.submit.requested'))->toBeTrue();
    expect($logger->hasEvent('pay_code.claim.submit.succeeded'))->toBeTrue();
});

it('logs claim submit failed event when claim submission throws', function () {
    $voucher = issueVoucher(validVoucherInstructions());
    $logger = fakeAuditLogger();

    $payload = [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'amount' => 100.00,
        'inputs' => [],
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ];

    $action = Mockery::mock(SubmitPayCodeClaim::class);
    $action->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('Claim submission exploded.'));

    $this->app->instance(SubmitPayCodeClaim::class, $action);

    $this->postJson(
        xchangeApi('pay-codes/'.$voucher->code.'/claim/submit'),
        $payload,
    )->assertStatus(500);

    expect($logger->hasEvent('pay_code.claim.submit.requested'))->toBeTrue();
    expect($logger->hasEvent('pay_code.claim.submit.failed'))->toBeTrue();
});
