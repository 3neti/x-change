<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Claim\PayCodeStampIndicatorResolver;

it('resolves outcome-first Stamp indicators from persisted instructions', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'cash' => [
            'slice_mode' => 'fixed',
            'slices' => 2,
            'validation' => [
                'mobile' => '09173011987',
                'mobile_verification' => true,
            ],
        ],
        'inputs' => [
            'fields' => ['mobile', 'name'],
        ],
    ]));

    expect(app(PayCodeStampIndicatorResolver::class)->resolve($voucher))
        ->toBe([
            'outcome.provider_disbursement',
            'input.mobile',
            'input.name',
            'validation.mobile',
            'validation.otp',
            'claim.multiple',
        ]);
});

it('selects the account-funding outcome independently of voucher type', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'voucher_type' => 'settlement',
        'target_amount' => 100,
        'claim' => [
            'outcomes' => [
                [
                    'key' => 'account_funding',
                    'pricing_profile' => 'account-funding-v1',
                ],
            ],
            'selection' => 'server',
            'consumption' => 'one_of',
            'default_outcome' => 'account_funding',
            'profile' => 'voucher.claim.v1',
        ],
    ]));

    expect(app(PayCodeStampIndicatorResolver::class)->resolve($voucher))
        ->toBe(['outcome.account_funding']);
});
