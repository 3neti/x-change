<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\Execution\ExecutionAwarePostRedemptionGate;
use LBHurtado\XChange\Services\OnboardingVoucherInstructionPolicy;

it('stops the ordinary payout pipeline for execution-only Vouchers', function (): void {
    $voucher = (new Voucher)->forceFill([
        'code' => 'ONBD-SAFE',
        'metadata' => [
            'instructions' => [
                'execution' => [
                    'driver' => OnboardingVoucherInstructionPolicy::ExecutionDriver,
                    'metadata' => [
                        'post_redemption' => [
                            'mode' => OnboardingVoucherInstructionPolicy::PostRedemptionMode,
                        ],
                    ],
                ],
            ],
        ],
    ]);
    $continued = false;

    $result = app(ExecutionAwarePostRedemptionGate::class)->handle(
        $voucher,
        function () use (&$continued): void {
            $continued = true;
        },
    );

    expect($result)->toBe($voucher)
        ->and($continued)->toBeFalse();
});

it('preserves the ordinary payout pipeline for ordinary Vouchers', function (): void {
    $voucher = (new Voucher)->forceFill([
        'code' => 'CASH-NORMAL',
        'metadata' => [
            'instructions' => [
                'execution' => [
                    'driver' => 'default',
                ],
            ],
        ],
    ]);

    $result = app(ExecutionAwarePostRedemptionGate::class)->handle(
        $voucher,
        static fn (Voucher $continued): string => 'continued:'.$continued->code,
    );

    expect($result)->toBe('continued:CASH-NORMAL');
});
