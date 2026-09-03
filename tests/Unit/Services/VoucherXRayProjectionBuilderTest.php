<?php

declare(strict_types=1);

use LBHurtado\Voucher\Enums\VoucherInputField;
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

it('projects enum-backed input fields as visible x-ray requirements', function (): void {
    $projection = app(VoucherXRayProjectionBuilder::class)->build((object) [
        'code' => 'XRAY-ONBD',
        'amount' => 0,
        'currency' => 'PHP',
        'status' => 'issued',
        'claimed' => false,
        'fully_claimed' => false,
        'instructions' => [
            'inputs' => [
                'fields' => [
                    VoucherInputField::NAME,
                    VoucherInputField::EMAIL,
                    VoucherInputField::MOBILE,
                ],
            ],
        ],
    ]);

    expect(collect($projection['requirements'])->pluck('key')->all())
        ->toBe(['name', 'email', 'mobile']);
});

it('projects onboarding vouchers with accept invitation claim presentation', function (): void {
    $projection = app(VoucherXRayProjectionBuilder::class)->build((object) [
        'code' => 'XRAY-ONBD',
        'amount' => 0,
        'currency' => 'PHP',
        'status' => 'issued',
        'claimed' => false,
        'fully_claimed' => false,
        'instructions' => [
            'onboarding' => true,
            'execution' => [
                'driver' => 'onboarding_account_provisioning',
            ],
        ],
    ]);

    expect($projection['presentation'])->toMatchArray([
        'title' => 'Accept Invitation',
        'primary_action_label' => 'Continue',
        'eyebrow' => 'Invitation code',
        'subject_label' => 'Invitation code',
        'confirmation_title' => 'Review your details',
        'confirmation_label' => 'Create my account',
        'intent' => 'commissioning_invitation',
        'source' => 'flow_default',
        'success' => [
            'schema' => 'x-change.onboarding-success-presentation.v1',
            'eyebrow' => 'Welcome',
            'title_template' => 'Welcome to {app_name}',
            'account_label' => 'account',
            'account_message' => 'Your account is ready.',
            'receipt_label' => 'Invitation accepted',
            'receipt_code' => 'XRAY-ONBD',
            'body' => 'You can now use your account workspace and manage account funding activity.',
            'primary_action_intent' => 'enter_workspace',
        ],
    ]);
});

it('projects maker onboarding success with client funds vocabulary when funded', function (): void {
    $projection = app(VoucherXRayProjectionBuilder::class)->build((object) [
        'code' => 'MAKE-1000',
        'amount' => 1000,
        'currency' => 'PHP',
        'status' => 'issued',
        'claimed' => false,
        'fully_claimed' => false,
        'instructions' => [
            'onboarding' => true,
            'cash' => [
                'amount' => 1000,
                'currency' => 'PHP',
            ],
            'execution' => [
                'driver' => 'onboarding_account_provisioning',
            ],
            'metadata' => [
                'custom' => [
                    'x_payout_commissioning' => [
                        'role' => 'maker',
                    ],
                ],
            ],
        ],
    ]);

    expect($projection['presentation']['success'])->toMatchArray([
        'account_label' => 'Maker account',
        'account_message' => 'Your Maker account is ready.',
        'body' => 'You can now prepare Pay Codes and submit payout work for checker approval.',
        'funds' => [
            'label' => 'Client Funds',
            'amount' => '₱1,000.00',
            'text' => '₱1,000.00 available for instructions',
        ],
        'primary_action_role' => 'Maker',
    ]);
});

it('projects checker onboarding success without funded copy when unfunded', function (): void {
    $projection = app(VoucherXRayProjectionBuilder::class)->build((object) [
        'code' => 'CHKR-0000',
        'amount' => 0,
        'currency' => 'PHP',
        'status' => 'issued',
        'claimed' => false,
        'fully_claimed' => false,
        'instructions' => [
            'onboarding' => true,
            'cash' => [
                'amount' => 0,
                'currency' => 'PHP',
            ],
            'execution' => [
                'driver' => 'onboarding_account_provisioning',
            ],
            'metadata' => [
                'custom' => [
                    'x_payout_commissioning' => [
                        'role' => 'checker',
                    ],
                ],
            ],
        ],
    ]);

    expect($projection['presentation']['success'])->toMatchArray([
        'account_label' => 'Checker account',
        'account_message' => 'Your Checker account is ready.',
        'body' => 'You can now review payout work and monitor completed instructions.',
        'primary_action_role' => 'Checker',
    ]);
    expect($projection['presentation']['success'])->not->toHaveKey('funds');
});

it('lets voucher instruction presentation override x-ray claim copy', function (): void {
    $projection = app(VoucherXRayProjectionBuilder::class)->build((object) [
        'code' => 'XRAY-COPY',
        'amount' => 25,
        'currency' => 'PHP',
        'status' => 'issued',
        'claimed' => false,
        'fully_claimed' => false,
        'instructions' => [
            'metadata' => [
                'presentation' => [
                    'claim' => [
                        'title' => 'Join Payroll',
                        'primary_action_label' => 'Accept Payroll Invite',
                        'intent' => 'payroll_invitation',
                    ],
                ],
            ],
        ],
    ]);

    expect($projection['presentation'])->toMatchArray([
        'title' => 'Join Payroll',
        'primary_action_label' => 'Accept Payroll Invite',
        'intent' => 'payroll_invitation',
        'source' => 'instructions',
    ]);
});

it('keeps ordinary x-ray claim presentation backward compatible', function (): void {
    $projection = app(VoucherXRayProjectionBuilder::class)->build((object) [
        'code' => 'XRAY-CASH',
        'amount' => 200,
        'currency' => 'PHP',
        'status' => 'issued',
        'claimed' => false,
        'fully_claimed' => false,
        'instructions' => [],
    ]);

    expect($projection['presentation'])->toMatchArray([
        'title' => 'Claim Pay Code',
        'primary_action_label' => 'Start Claim',
        'intent' => 'claim',
        'source' => 'fallback',
    ]);
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

it('projects payable vouchers with collection progress and pay action', function (): void {
    $user = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'voucher_type' => 'payable',
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) $user->id,
                'collection_wallet_id' => $user->wallet->id,
            ],
        ],
    ));

    $projection = app(VoucherXRayProjectionBuilder::class)->build($voucher);

    expect($projection['status'])->toBe('payable')
        ->and(data_get($projection, 'collection_progress.target_amount_minor'))->toBe(10000)
        ->and(data_get($projection, 'collection_progress.remaining_to_collect_minor'))->toBe(10000)
        ->and($projection['next_actions'][0])->toMatchArray([
            'key' => 'pay',
            'label' => 'Pay now',
        ])
        ->and($projection['next_actions'][0]['url'])->toContain($voucher->code);
});
