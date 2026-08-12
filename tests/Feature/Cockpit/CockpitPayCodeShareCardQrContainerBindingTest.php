<?php

declare(strict_types=1);

it('resolves the real claim URL QR renderer through the container for a claimable Pay Code', function (): void {
    $owner = actingAsTestUser();
    $voucher = issueVoucher();

    $response = $this->actingAs($owner)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', $voucher->code))
        ->assertOk()
        ->assertJsonPath('props.read_model.voucher.distribution_links.available', true);

    $redeemUrl = data_get(
        $response->json(),
        'props.read_model.voucher.distribution_links.redeem_url',
    );
    $claimQr = data_get(
        $response->json(),
        'props.read_model.voucher.distribution_links.claim_qr',
    );

    expect($redeemUrl)->toBe(route('x-change.claim.show', ['code' => $voucher->code]))
        ->and($claimQr)->toBeString()
        ->and($claimQr)->toStartWith('data:image/png;base64,');
});
