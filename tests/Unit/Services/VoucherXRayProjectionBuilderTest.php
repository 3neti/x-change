<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\XRay\VoucherXRayProjectionBuilder;

it('projects voucher details into an x-ray disclosure payload', function (): void {
    $projection = app(VoucherXRayProjectionBuilder::class)->build((object) [
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
                'validation' => [
                    'secret' => '1234',
                    'mobile' => '09171234567',
                ],
                'slices' => [
                    [
                        'id' => 'slice_1',
                        'amount' => 800,
                        'description' => 'Buy coffee',
                    ],
                ],
            ],
            'inputs' => [
                'fields' => ['mobile', 'bank_account', 'otp'],
            ],
            'rider' => [
                'message' => 'Read before claiming.',
                'url' => 'https://example.com/rider',
            ],
        ],
    ]);

    expect($projection['status'])->toBe('claimable')
        ->and($projection['requirements'])->toHaveCount(5)
        ->and(collect($projection['requirements'])->pluck('key')->all())->toContain('mobile', 'bank_account', 'otp', 'secret', 'assigned_mobile')
        ->and($projection['remaining_slices'])->toHaveCount(1)
        ->and($projection['remaining_slices'][0]['label'])->toBe('Buy coffee')
        ->and($projection['redirect_url'])->toBe('https://example.com/rider')
        ->and($projection['allow']['amount'])->toBeFalse()
        ->and($projection['allow']['rider_preclaim'])->toBeTrue();
});

it('projects partially claimed vouchers as partially claimable', function (): void {
    $projection = app(VoucherXRayProjectionBuilder::class)->build((object) [
        'code' => 'XRAY-SLICE',
        'amount' => 200,
        'currency' => 'PHP',
        'status' => 'issued',
        'claimed' => true,
        'fully_claimed' => false,
        'instructions' => [],
    ]);

    expect($projection['status'])->toBe('partially_claimable');
});
