<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\LoadPayCodeRedemptionCompletionContext;
use LBHurtado\XChange\Contracts\RedemptionCompletionContextContract;
use LBHurtado\XChange\Data\Redemption\LoadRedemptionCompletionContextResultData;

it('delegates completion context loading to the configured service', function () {
    $voucher = Mockery::mock(Voucher::class);

    $expected = new LoadRedemptionCompletionContextResultData(
        voucher_code: 'TEST-1234',
        can_confirm: true,
        reference_id: 'ref-123',
        flow_id: 'flow-123',
        collected_data: ['wallet_info' => ['mobile' => '09171234567']],
        flat_data: ['mobile' => '09171234567'],
        wallet: ['mobile' => '09171234567'],
        inputs: [],
        messages: [],
    );

    $service = Mockery::mock(RedemptionCompletionContextContract::class);
    $service->shouldReceive('load')
        ->once()
        ->with($voucher, 'ref-123', 'flow-123')
        ->andReturn($expected);

    $action = new LoadPayCodeRedemptionCompletionContext($service);

    $result = $action->handle($voucher, 'ref-123', 'flow-123');

    expect($result)->toBeInstanceOf(LoadRedemptionCompletionContextResultData::class);
    expect($result->toArray())->toBe($expected->toArray());
});
