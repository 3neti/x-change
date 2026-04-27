<?php

declare(strict_types=1);

it('returns settlement preparation metadata via the lifecycle route surface', function () {
    $voucher = issueVoucher(validVoucherInstructions(100.00, 'INSTAPAY', [
        'voucher_type' => 'settlement',
        'target_amount' => 100.00,
    ]));

    $voucher->forceFill([
        'code' => 'SETTLE-1234',
    ])->save();

    $response = $this->postJson(route('api.x.v1.settlements.start', [
        'voucher' => 'SETTLE-1234',
    ]));

    $response->assertOk();

    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.voucher_code', 'SETTLE-1234');
    $response->assertJsonPath('data.can_start', false);
    $response->assertJsonPath('data.entry_route', 'settle');
    $response->assertJsonPath('data.requires_envelope', true);
    $response->assertJsonPath('data.requirements.envelope', true);
    $response->assertJsonPath('data.capabilities.can_disburse', true);
    $response->assertJsonPath('data.capabilities.can_collect', true);
    $response->assertJsonPath('data.capabilities.can_settle', true);
    $response->assertJsonPath('data.envelope.required', true);
    $response->assertJsonPath('data.envelope.exists', false);
    $response->assertJsonPath('data.envelope.ready', false);
    $response->assertJsonPath('data.envelope.missing.0', 'settlement_envelope');
    $response->assertJsonPath('data.requirements.missing.0', 'settlement_envelope');
    $response->assertJsonPath('data.messages.0', 'Settlement envelope is not ready.');
});

it('returns not found when settlement voucher code does not exist', function () {
    $response = $this->postJson(route('api.x.v1.settlements.start', [
        'voucher' => 'MISSING',
    ]));

    $response->assertNotFound();
});
