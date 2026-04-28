<?php

declare(strict_types=1);

it('shows rendered payment qr for collectible voucher through lifecycle route surface', function () {
    config()->set('x-change.payment_qr.renderer', 'png_base64');

    $voucher = issueVoucher(validVoucherInstructions(100.00, 'INSTAPAY', [
        'voucher_type' => 'payable',
    ]));

    $response = $this->getJson(route('api.x.v1.vouchers.payment-qr.show', [
        'code' => $voucher->code,
    ]));

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.voucher_code', $voucher->code);
    $response->assertJsonPath('data.format', 'png_base64');
    $response->assertJsonPath('data.content_type', 'image/png');
    $response->assertJsonPath('data.payload.type', 'x-change.payment_qr');
    $response->assertJsonPath('data.payload.flow_type', 'collectible');
    $response->assertJsonPath('data.payload.route_key', 'pay');

    expect(data_get($response->json(), 'data.rendered'))
        ->toStartWith('data:image/png;base64,');
});

it('returns not found when payment qr voucher code does not exist', function () {
    $response = $this->getJson(route('api.x.v1.vouchers.payment-qr.show', [
        'code' => 'MISSING',
    ]));

    $response->assertNotFound();
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('code', 'VOUCHER_NOT_FOUND');
});

it('blocks disbursable vouchers from payment qr rendering', function () {
    $voucher = issueVoucher(validVoucherInstructions(100.00, 'INSTAPAY', [
        'voucher_type' => 'redeemable',
    ]));

    $response = $this->getJson(route('api.x.v1.vouchers.payment-qr.show', [
        'code' => $voucher->code,
    ]));

    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('code', 'VOUCHER_CANNOT_COLLECT');
});
