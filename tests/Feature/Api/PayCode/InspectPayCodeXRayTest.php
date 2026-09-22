<?php

declare(strict_types=1);

use LBHurtado\Voucher\Services\VoucherSlicePlanFactory;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Exceptions\VoucherNotFound;
use LBHurtado\XChange\Models\VoucherCollection;

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
        ->assertJsonPath('data.xray.presentation.title', 'Claim Pay Code')
        ->assertJsonPath('data.xray.presentation.primary_action_label', 'Start Claim')
        ->assertJsonPath('data.xray.disclosures.0.key', 'status')
        ->assertJsonPath('data.xray.requirements.0.key', 'mobile')
        ->assertJsonPath('data.xray.requirements.1.key', 'bank_account');

    expect(collect($response->json('data.xray.redactions'))->pluck('key')->all())
        ->toContain('amount', 'issuer', 'redirect_url');
});

it('keeps confirmed collection paid in x-ray after the voucher expires', function (): void {
    $issuer = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'voucher_type' => 'payable',
        'target_amount' => 100,
        'metadata' => [
            'flow_type' => 'collectible',
            'collection_wallet_id' => (string) $issuer->wallet()->where('slug', 'platform')->sole()->getKey(),
        ],
    ]));
    VoucherCollection::query()->create([
        'voucher_id' => $voucher->getKey(),
        'collection_number' => 1,
        'status' => 'collected',
        'requested_amount_minor' => 10000,
        'collected_amount_minor' => 10000,
        'currency' => 'PHP',
        'provider' => 'netbank',
        'provider_reference' => 'xray-paid-expired',
        'provider_transaction_id' => 'xray-paid-expired',
        'idempotency_key' => 'xray-paid-expired',
        'completed_at' => now()->subDay(),
    ]);
    $voucher->forceFill(['expires_at' => now()->subHour()])->save();
    auth()->logout();

    $this->postJson(xchangeApi('pay-codes/x-ray'), ['code' => $voucher->code])
        ->assertOk()
        ->assertJsonPath('data.xray.status', 'paid');
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

it('discloses canonical slice labels without retired cash slice fields', function (): void {
    config()->set('x-ray.disclosure.guest.show_remaining_slices', 'if_allowed_by_voucher');
    actingAsTestUser();
    $plan = app(VoucherSlicePlanFactory::class)->equal(7_500, 'PHP', 3);
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 75,
        overrides: ['slice_plan' => $plan->canonicalArray()],
    ));
    auth()->logout();

    $response = $this->postJson(xchangeApi('pay-codes/x-ray'), [
        'code' => $voucher->code,
        'channel' => 'claim',
    ])->assertOk();

    $sliceDisclosure = collect($response->json('data.xray.disclosures'))
        ->firstWhere('key', 'remaining_slices');

    expect($sliceDisclosure)->toBeArray()
        ->and($sliceDisclosure['value'])->toHaveCount(3)
        ->and(collect($sliceDisclosure['value'])->pluck('label')->all())
        ->toBe(['Slice 1', 'Slice 2', 'Slice 3'])
        ->and(data_get($sliceDisclosure, 'value.0.amount_minor'))->toBe(2_500);
});

it('validates x-ray inspection requests', function (): void {
    $this->postJson(xchangeApi('pay-codes/x-ray'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});
