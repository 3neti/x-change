<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Services\Claim\VoucherClaimantReference;
use LBHurtado\XChange\Services\Cockpit\CompileCockpitQuickGenerateClaimPolicy;
use LBHurtado\XChange\Tests\Fakes\User;

it('binds Account Funding to a uniquely resolved verified mobile without trusting a browser reference', function (): void {
    config()->set('x-change.onboarding.issuer_model', User::class);
    $recipient = User::query()->create([
        'name' => 'Verified Funding Recipient',
        'email' => 'verified-funding-recipient@example.test',
        'password' => 'password',
    ]);
    $recipient->forceFill([
        'mobile' => '639173011987',
        'mobile_verified_at' => now(),
    ])->save();

    $compiled = app(CompileCockpitQuickGenerateClaimPolicy::class)->handle([
        'claim' => [
            'outcomes' => [[
                'key' => 'account_funding',
                'pricing_profile' => 'account-funding-v1',
            ]],
            'claimant' => [
                'mode' => 'recipient',
                'reference' => 'browser-supplied-reference',
            ],
        ],
        'metadata' => [
            'custom' => [
                'cockpit' => [
                    'recipient_reference' => '09173011987',
                ],
            ],
        ],
    ]);

    expect(data_get($compiled, 'claim.selection'))->toBe('server')
        ->and(data_get($compiled, 'claim.default_outcome'))->toBe('account_funding')
        ->and(data_get($compiled, 'claim.claimant'))->toBe([
            'mode' => 'recipient',
            'reference' => app(VoucherClaimantReference::class)->for($recipient),
        ])
        ->and(data_get(
            $compiled,
            'metadata.custom.cockpit.recipient_reference',
        ))->toBeNull();
});

it('keeps CASH Account Funding Pay Codes bearer-bound and leaves payout policies unchanged', function (): void {
    $compiler = app(CompileCockpitQuickGenerateClaimPolicy::class);
    $accountFunding = $compiler->handle([
        'claim' => [
            'outcomes' => [['key' => 'account_funding']],
        ],
        'metadata' => [
            'custom' => [
                'cockpit' => ['recipient_reference' => 'CASH'],
            ],
        ],
    ]);
    $payout = [
        'claim' => [
            'outcomes' => [['key' => 'provider_disbursement']],
            'claimant' => ['mode' => 'unbound'],
        ],
    ];

    expect(data_get($accountFunding, 'claim.claimant'))->toBe([
        'mode' => 'unbound',
    ])->and($compiler->handle($payout))->toBe($payout);
});

it('fails closed when an Account Funding recipient cannot be resolved', function (): void {
    config()->set('x-change.onboarding.issuer_model', User::class);

    expect(fn () => app(CompileCockpitQuickGenerateClaimPolicy::class)->handle([
        'claim' => [
            'outcomes' => [['key' => 'account_funding']],
        ],
        'metadata' => [
            'custom' => [
                'cockpit' => ['recipient_reference' => '09179999999'],
            ],
        ],
    ]))->toThrow(ValidationException::class);
});
