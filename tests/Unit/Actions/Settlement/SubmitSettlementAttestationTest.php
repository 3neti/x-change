<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Actions\Settlement\SubmitSettlementAttestation;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;

it('submits settlement attestation through the pay code claim action', function () {
    $voucher = new Voucher;
    $capturedPayload = null;

    $submitPayCodeClaim = Mockery::mock(SubmitPayCodeClaim::class);
    $submitPayCodeClaim
        ->shouldReceive('handle')
        ->once()
        ->withArgs(function ($actualVoucher, array $payload) use ($voucher, &$capturedPayload) {
            $capturedPayload = $payload;

            return $actualVoucher === $voucher
                && $payload['claim_type'] === 'redeem'
                && $payload['settlement_attestation'] === true;
        })
        ->andReturn(new SubmitPayCodeClaimResultData(
            voucher_code: 'TEST-SETTLE',
            claim_type: 'redeem',
            claimed: true,
            status: 'succeeded',
            messages: [
                'Settlement attestation submitted.',
            ],
        ));

    $result = app()->makeWith(SubmitSettlementAttestation::class, [
        'submitPayCodeClaim' => $submitPayCodeClaim,
    ])->handle($voucher, [
        'mobile' => '09171234567',
        'signature' => 'base64-signature',
    ]);

    expect($result->status)->toBe('succeeded')
        ->and($result->messages)->toContain('Settlement attestation submitted.')
        ->and($capturedPayload)->toMatchArray([
            'mobile' => '09171234567',
            'signature' => 'base64-signature',
            'claim_type' => 'redeem',
            'settlement_attestation' => true,
        ]);
});
