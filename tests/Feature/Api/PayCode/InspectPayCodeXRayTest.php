<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Exceptions\VoucherNotFound;

it('returns guest-safe x-ray disclosure for a pay code', function (): void {
    $service = Mockery::mock(VoucherLifecycleServiceContract::class);
    $service->shouldReceive('showByCode')
        ->once()
        ->with('XRAY-1234')
        ->andReturn((object) [
            'code' => 'XRAY-1234',
            'amount' => 1500.00,
            'currency' => 'PHP',
            'status' => 'issued',
            'issuer_id' => 7,
            'claimed' => false,
            'fully_claimed' => false,
            'instructions' => [
                'cash' => [
                    'currency' => 'PHP',
                ],
                'inputs' => [
                    'fields' => ['mobile', 'bank_account'],
                ],
                'rider' => [
                    'message' => 'Check the details before claiming.',
                ],
            ],
        ]);

    $this->app->instance(VoucherLifecycleServiceContract::class, $service);

    $response = $this->postJson(xchangeApi('pay-codes/x-ray'), [
        'code' => 'xray-1234',
        'channel' => 'claim',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.xray.visible', true)
        ->assertJsonPath('data.xray.status', 'claimable')
        ->assertJsonPath('data.xray.disclosures.0.key', 'status')
        ->assertJsonPath('data.xray.requirements.0.key', 'mobile')
        ->assertJsonPath('data.xray.requirements.1.key', 'bank_account');

    expect(collect($response->json('data.xray.redactions'))->pluck('key')->all())
        ->toContain('amount', 'issuer', 'redirect_url');
});

it('returns a safe x-ray not found response', function (): void {
    $service = Mockery::mock(VoucherLifecycleServiceContract::class);
    $service->shouldReceive('showByCode')
        ->once()
        ->with('MISSING')
        ->andThrow(new VoucherNotFound('Voucher not found.'));

    $this->app->instance(VoucherLifecycleServiceContract::class, $service);

    $this->postJson(xchangeApi('pay-codes/x-ray'), [
        'code' => 'MISSING',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.xray.visible', false)
        ->assertJsonPath('data.xray.status', 'not_found')
        ->assertJsonPath('data.xray.requirements', []);
});

it('validates x-ray inspection requests', function (): void {
    $this->postJson(xchangeApi('pay-codes/x-ray'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});
