<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RedeemPayCode;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;

it('redeems pay code via api', function () {
    $voucher = issueVoucher(validVoucherInstructions());

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

    $result = new RedeemPayCodeResultData(
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
        ->with(
            Mockery::on(fn ($actual) => $actual instanceof Voucher && $actual->is($voucher)),
            Mockery::on(function (array $actual) {
                expect(data_get($actual, 'mobile'))->toBe('09171234567');
                expect(data_get($actual, 'recipient_country'))->toBe('PH');
                expect(data_get($actual, 'secret'))->toBe('1234');
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

                return true;
            }),
        )
        ->andReturn($result);

    $this->app->instance(RedeemPayCode::class, $action);

    $response = $this->postJson(
        xchangeApi('pay-codes/'.$voucher->code.'/redeem'),
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

it('returns not found when redeeming unknown voucher code', function () {
    $response = $this->postJson(
        xchangeApi('pay-codes/DOES-NOT-EXIST/redeem'),
        [
            'mobile' => '09171234567',
            'recipient_country' => 'PH',
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

it('validates required redemption payload fields', function () {
    $voucher = issueVoucher(validVoucherInstructions());

    $response = $this->postJson(
        xchangeApi('pay-codes/'.$voucher->code.'/redeem'),
        [],
    );

    $response
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'code' => 'VALIDATION_ERROR',
        ]);
});
