<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\LoadPayCodeRedemptionCompletionContext;
use LBHurtado\XChange\Data\Redemption\LoadRedemptionCompletionContextResultData;

it('returns redemption completion context via api', function () {
    $voucher = issueVoucher(validVoucherInstructions());

    $result = new LoadRedemptionCompletionContextResultData(
        voucher_code: $voucher->code,
        can_confirm: true,
        reference_id: 'ref-123',
        flow_id: 'flow-123',
        collected_data: [
            'wallet_info' => [
                'mobile' => '09171234567',
            ],
        ],
        flat_data: [
            'mobile' => '09171234567',
        ],
        wallet: [
            'mobile' => '09171234567',
        ],
        inputs: [],
        messages: [],
    );

    $action = Mockery::mock(LoadPayCodeRedemptionCompletionContext::class);
    $action->shouldReceive('handle')
        ->once()
        ->with(
            Mockery::on(fn ($actual) => $actual instanceof Voucher && $actual->is($voucher)),
            'ref-123',
            'flow-123',
        )
        ->andReturn($result);

    $this->app->instance(LoadPayCodeRedemptionCompletionContext::class, $action);

    $response = $this->postJson(
        xchangeApi('pay-codes/'.$voucher->code.'/claim/complete'),
        [
            'reference_id' => 'ref-123',
            'flow_id' => 'flow-123',
        ],
    );

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => $result->toArray(),
            'meta' => [],
        ]);
});

it('returns not found for completion context when voucher code does not exist', function () {
    $response = $this->postJson(
        xchangeApi('pay-codes/DOES-NOT-EXIST/claim/complete'),
        [
            'reference_id' => 'ref-123',
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
