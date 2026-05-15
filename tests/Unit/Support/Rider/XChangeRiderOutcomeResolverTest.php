<?php

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Support\Rider\XChangeRiderOutcomeResolver;
use LBHurtado\XRider\Enums\RiderOutcomeState;

function riderOutcomeVoucherWithMetadata(array $metadata = []): Voucher
{
    $voucher = new Voucher();

    $voucher->forceFill([
        'metadata' => $metadata,
    ]);

    return $voucher;
}

it('defaults ordinary vouchers to accepted success', function () {
    $resolver = new XChangeRiderOutcomeResolver();

    $outcome = $resolver->forVoucher(
        riderOutcomeVoucherWithMetadata()
    );

    expect($outcome)->toBe(RiderOutcomeState::AcceptedSuccess);
});

it('treats pending disbursement status as accepted pending', function () {
    $resolver = new XChangeRiderOutcomeResolver();

    $outcome = $resolver->forVoucher(
        riderOutcomeVoucherWithMetadata([
            'disbursement' => [
                'status' => 'pending',
            ],
        ])
    );

    expect($outcome)->toBe(RiderOutcomeState::AcceptedPending);
});

it('treats processing payout status as accepted pending', function () {
    $resolver = new XChangeRiderOutcomeResolver();

    $outcome = $resolver->forVoucher(
        riderOutcomeVoucherWithMetadata([
            'payout' => [
                'status' => 'processing',
            ],
        ])
    );

    expect($outcome)->toBe(RiderOutcomeState::AcceptedPending);
});

it('does not treat successful disbursement status as pending', function () {
    $resolver = new XChangeRiderOutcomeResolver();

    $outcome = $resolver->forVoucher(
        riderOutcomeVoucherWithMetadata([
            'disbursement' => [
                'status' => 'success',
            ],
        ])
    );

    expect($outcome)->toBe(RiderOutcomeState::AcceptedSuccess);
});
