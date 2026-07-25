<?php

declare(strict_types=1);

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\Claim\VoucherClaimPolicyResolver;

it('resolves explicit typed one-of claim outcomes', function () {
    $voucher = claimPolicyVoucher([
        'instructions' => [
            'cash' => ['amount' => 100, 'currency' => 'PHP'],
            'inputs' => ['fields' => []],
            'feedback' => [],
            'rider' => [],
            'claim' => [
                'outcomes' => [
                    [
                        'key' => 'provider_disbursement',
                        'pricing_profile' => 'instapay-disbursement-v1',
                    ],
                    [
                        'key' => 'account_funding',
                        'pricing_profile' => 'account-funding-v1',
                    ],
                ],
                'selection' => 'claimant',
                'consumption' => 'one_of',
                'default_outcome' => 'provider_disbursement',
                'onboarding' => ['mode' => 'if_required'],
            ],
        ],
    ]);

    $policy = app(VoucherClaimPolicyResolver::class)->resolve($voucher);

    expect($policy->profile)->toBe('voucher.claim.v1')
        ->and($policy->legacy)->toBeFalse()
        ->and($policy->selection)->toBe('claimant')
        ->and($policy->consumption)->toBe('one_of')
        ->and($policy->defaultOutcome)->toBe('provider_disbursement')
        ->and($policy->permits('provider_disbursement'))->toBeTrue()
        ->and($policy->permits('account_funding'))->toBeTrue()
        ->and($policy->permits('payment'))->toBeFalse()
        ->and($policy->onboarding)->toMatchArray(['mode' => 'if_required']);
});

it('maps legacy redeemable Pay Codes without mutating their instructions', function () {
    $voucher = claimPolicyVoucher([
        'instructions' => [
            'cash' => ['amount' => 100, 'currency' => 'PHP'],
            'inputs' => ['fields' => []],
            'feedback' => [],
            'rider' => [],
        ],
    ]);

    $policy = app(VoucherClaimPolicyResolver::class)->resolve($voucher);

    expect($policy->legacy)->toBeTrue()
        ->and($policy->selection)->toBe('server')
        ->and($policy->defaultOutcome)->toBe('provider_disbursement')
        ->and($policy->permits('provider_disbursement'))->toBeTrue()
        ->and($voucher->fresh()->metadata['instructions'])->not->toHaveKey('claim');
});

it('maps current account-funding-also metadata into a legacy dual outcome policy', function () {
    $voucher = claimPolicyVoucher([
        'instructions' => [
            'cash' => ['amount' => 100, 'currency' => 'PHP'],
            'inputs' => ['fields' => []],
            'feedback' => [],
            'rider' => [],
        ],
        'treasury' => [
            'account_funding' => [
                'destinations' => ['provider_payout', 'account_funding'],
            ],
        ],
    ]);

    $policy = app(VoucherClaimPolicyResolver::class)->resolve($voucher);

    expect($policy->legacy)->toBeTrue()
        ->and($policy->selection)->toBe('claimant')
        ->and($policy->permits('provider_disbursement'))->toBeTrue()
        ->and($policy->permits('account_funding'))->toBeTrue();
});

it('resolves typed and legacy issuance instructions before a Voucher exists', function () {
    $typed = app(VoucherClaimPolicyResolver::class)->resolveInstructions(
        VoucherInstructionsData::from([
            'cash' => [
                'amount' => 100,
                'currency' => 'PHP',
                'validation' => ['country' => 'PH'],
            ],
            'inputs' => ['fields' => []],
            'feedback' => [
                'email' => null,
                'mobile' => null,
                'webhook' => null,
            ],
            'rider' => [
                'message' => null,
                'url' => null,
                'splash' => null,
            ],
            'count' => 1,
            'claim' => [
                'outcomes' => [[
                    'key' => 'account_funding',
                    'pricing_profile' => 'account-funding-v1',
                ]],
                'selection' => 'server',
                'default_outcome' => 'account_funding',
            ],
        ]),
    );
    $legacy = app(VoucherClaimPolicyResolver::class)->resolveInstructions(
        VoucherInstructionsData::from([
            'cash' => [
                'amount' => 100,
                'currency' => 'PHP',
                'validation' => ['country' => 'PH'],
            ],
            'inputs' => ['fields' => []],
            'feedback' => [
                'email' => null,
                'mobile' => null,
                'webhook' => null,
            ],
            'rider' => [
                'message' => null,
                'url' => null,
                'splash' => null,
            ],
            'count' => 1,
            'metadata' => [
                'custom' => [
                    'settlement' => [
                        'destinations' => ['account_funding'],
                        'account_funding' => [
                            'pricing_profile' => 'account-funding-v1',
                        ],
                    ],
                ],
            ],
        ]),
    );

    expect($typed->legacy)->toBeFalse()
        ->and($typed->permits('account_funding'))->toBeTrue()
        ->and($typed->outcomes[0]->pricingProfile)->toBe('account-funding-v1')
        ->and($legacy->legacy)->toBeTrue()
        ->and($legacy->permits('account_funding'))->toBeTrue();
});

/**
 * @param  array<string, mixed>  $metadata
 */
function claimPolicyVoucher(array $metadata): Voucher
{
    $metadata = array_replace_recursive([
        'instructions' => [
            'cash' => [
                'amount' => 100,
                'currency' => 'PHP',
                'validation' => ['country' => 'PH'],
            ],
            'inputs' => ['fields' => []],
            'feedback' => [],
            'rider' => [],
            'count' => 1,
            'prefix' => 'POL',
            'mask' => '****',
        ],
    ], $metadata);

    return Voucher::query()->forceCreate([
        'code' => 'POL-'.mb_strtoupper(substr(hash('sha256', uniqid('', true)), 0, 8)),
        'metadata' => $metadata,
        'voucher_type' => 'redeemable',
        'state' => 'active',
        'expires_at' => now()->addHour(),
    ]);
}
