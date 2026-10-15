<?php

declare(strict_types=1);

use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceVoucherActionPolicy;

it('hides terminal actions once the Pay Code expiration has passed', function (): void {
    $voucher = new Voucher;
    $voucher->forceFill([
        'state' => VoucherState::ACTIVE,
        'expires_at' => now()->subMinute(),
    ]);

    expect(app(PublicIssuanceVoucherActionPolicy::class)->permissions($voucher))
        ->toMatchArray([
            'can_extend' => false,
            'can_cancel' => false,
            'can_expire' => false,
            'can_request_refund' => false,
        ]);
});

it('exposes lifecycle actions for an active unclaimed Pay Code', function (): void {
    $voucher = new Voucher;
    $voucher->forceFill([
        'state' => VoucherState::ACTIVE,
        'expires_at' => now()->addHour(),
    ]);

    expect(app(PublicIssuanceVoucherActionPolicy::class)->permissions($voucher))
        ->toMatchArray([
            'can_extend' => true,
            'can_cancel' => true,
            'can_expire' => true,
            'can_request_refund' => false,
        ]);
});
