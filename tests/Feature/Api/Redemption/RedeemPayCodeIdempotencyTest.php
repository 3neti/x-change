<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RedeemPayCode;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;

it('replays the same redemption response for the same idempotency key and payload', function () {
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
            Mockery::on(function (array $actual) use ($payload, $voucher) {
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
                expect(data_get($actual, '_meta.idempotency_key'))->toBe('redeem-key-1');
                expect(data_get($actual, '_meta.voucher_code'))->toBe($voucher->code);

                return true;
            }),
        )
        ->andReturn($result);

    $this->app->instance(RedeemPayCode::class, $action);

    $headers = [
        'Idempotency-Key' => 'redeem-key-1',
    ];

    $first = $this->postJson(
        xchangeApi('pay-codes/'.$voucher->code.'/redeem'),
        $payload,
        $headers,
    );

    $first
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => $result->toArray(),
            'meta' => [
                'idempotency' => [
                    'key' => 'redeem-key-1',
                    'replayed' => false,
                ],
            ],
        ]);

    $second = $this->postJson(
        xchangeApi('pay-codes/'.$voucher->code.'/redeem'),
        $payload,
        $headers,
    );

    $second
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => $result->toArray(),
            'meta' => [
                'idempotency' => [
                    'key' => 'redeem-key-1',
                    'replayed' => true,
                ],
            ],
        ]);
});

it('returns conflict when the same idempotency key is reused with a different redemption payload', function () {
    $voucher = issueVoucher(validVoucherInstructions());

    $firstPayload = [
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

    $secondPayload = [
        'mobile' => '09999888777',
        'recipient_country' => 'PH',
        'secret' => '9999',
        'inputs' => [
            'name' => 'Maria Clara',
        ],
        'bank_account' => [
            'bank_code' => 'BNORPHMMXXX',
            'account_number' => '1234567890',
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
        ->andReturn($result);

    $this->app->instance(RedeemPayCode::class, $action);

    $headers = [
        'Idempotency-Key' => 'redeem-key-2',
    ];

    $this->postJson(
        xchangeApi('pay-codes/'.$voucher->code.'/redeem'),
        $firstPayload,
        $headers,
    )->assertOk();

    $this->postJson(
        xchangeApi('pay-codes/'.$voucher->code.'/redeem'),
        $secondPayload,
        $headers,
    )
        ->assertStatus(409)
        ->assertJson([
            'success' => false,
            'code' => 'IDEMPOTENCY_CONFLICT',
        ]);
});
