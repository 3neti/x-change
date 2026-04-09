<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;

it('submits claim through redeem path via api', function () {
    $voucher = issueVoucher(validVoucherInstructions());

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
        ->with(
            Mockery::on(fn ($actual) => $actual instanceof Voucher && $actual->is($voucher)),
            Mockery::on(function (array $actual) use ($voucher) {
                expect(data_get($actual, 'mobile'))->toBe('09171234567');
                expect(data_get($actual, 'recipient_country'))->toBe('PH');
                expect(data_get($actual, 'secret'))->toBe('1234');
                expect((float) data_get($actual, 'amount'))->toBe(100.0);
                expect(data_get($actual, 'inputs'))->toBe([
                    'name' => 'Juan Dela Cruz',
                ]);
                expect(data_get($actual, 'bank_account'))->toBe([
                    'bank_code' => 'GXCHPHM2XXX',
                    'account_number' => '09171234567',
                ]);
                expect($actual)->toHaveKey('_meta');
                expect(data_get($actual, '_meta.idempotency_key'))->toBeNull();
                expect(data_get($actual, '_meta.correlation_id'))->toBeNull();
                expect(data_get($actual, '_meta.voucher_code'))->toBe($voucher->code);

                return true;
            }),
        )
        ->andReturn($result);

    $this->app->instance(SubmitPayCodeClaim::class, $action);

    $response = $this->postJson(
        xchangeApi('pay-codes/'.$voucher->code.'/claim/submit'),
        $payload,
    );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => $result->toArray(),
            'meta' => [
                'idempotency' => [
                    'key' => null,
                    'replayed' => false,
                ],
            ],
        ]);
});

it('submits claim through withdraw path via api', function () {
    $voucher = issueVoucher(validVoucherInstructions());

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

    $response = $this->postJson(
        xchangeApi('pay-codes/'.$voucher->code.'/claim/submit'),
        $payload,
    );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => $result->toArray(),
            'meta' => [
                'idempotency' => [
                    'key' => null,
                    'replayed' => false,
                ],
            ],
        ]);
});

it('returns not found when submitting claim for unknown voucher code', function () {
    $response = $this->postJson(
        xchangeApi('pay-codes/DOES-NOT-EXIST/claim/submit'),
        [
            'mobile' => '09171234567',
            'inputs' => [],
            'bank_account' => [
                'bank_code' => 'GXCHPHM2XXX',
                'account_number' => '09171234567',
            ],
        ],
    );

    $response
        ->assertNotFound()
        ->assertJson([
            'success' => false,
            'message' => 'Invalid voucher code.',
            'code' => 'PAY_CODE_INVALID',
            'errors' => [
                'code' => ['Invalid voucher code.'],
            ],
        ]);
});

it('validates required claim submit payload fields', function () {
    $voucher = issueVoucher(validVoucherInstructions());

    $response = $this->postJson(
        xchangeApi('pay-codes/'.$voucher->code.'/claim/submit'),
        [],
    );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'code' => 'VALIDATION_ERROR',
        ]);
});
