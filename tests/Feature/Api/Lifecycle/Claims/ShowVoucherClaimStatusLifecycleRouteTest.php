<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\RedemptionCompletionServiceContract;

it('shows voucher claim status through the lifecycle route surface', function () {
    $result = (object) [
        'voucher_code' => 'TEST-1234',
        'claim_type' => 'redeem',
        'status' => 'completed',
        'claimed' => true,
        'fully_claimed' => true,
        'requested_amount' => 100.00,
        'disbursed_amount' => 100.00,
        'remaining_balance' => 0.00,
        'currency' => 'PHP',
        'messages' => ['Claim completed successfully.'],
    ];

    $service = Mockery::mock(RedemptionCompletionServiceContract::class);
    $service->shouldReceive('status')
        ->once()
        ->with('TEST-1234')
        ->andReturn($result);

    $this->app->instance(RedemptionCompletionServiceContract::class, $service);

    $response = $this->getJson('/api/x/v1/vouchers/code/TEST-1234/claim/status');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.voucher_code', 'TEST-1234')
        ->assertJsonPath('data.claim_type', 'redeem')
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.claimed', true)
        ->assertJsonPath('data.fully_claimed', true)
        ->assertJsonPath('data.requested_amount', 100)
        ->assertJsonPath('data.disbursed_amount', 100)
        ->assertJsonPath('data.remaining_balance', 0)
        ->assertJsonPath('data.currency', 'PHP');
});
