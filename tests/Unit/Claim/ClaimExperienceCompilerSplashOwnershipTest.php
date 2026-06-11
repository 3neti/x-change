<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\ResolveClaimExperience;

function splashOwnershipVoucherWithRiderSplash(): Voucher
{
    return issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'splash' => '<h1>Welcome</h1>',
                'message' => 'Thank you for claiming.',
                'url' => 'https://example.com/success',
            ],
        ],
    ));
}

function splashOwnershipVoucherWithoutRiderSplash(): Voucher
{
    return issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'message' => 'Thank you for claiming.',
                'url' => 'https://example.com/success',
            ],
        ],
    ));
}

function resolveSplashOwnershipExperience(Voucher $voucher): array
{
    return ResolveClaimExperience::run($voucher)->toArray();
}

it('marks rider splash as consumed when voucher owns rider intro splash', function () {
    $experience = resolveSplashOwnershipExperience(
        splashOwnershipVoucherWithRiderSplash(),
    );

    expect(data_get($experience, 'consumed.splash'))->toBeTrue()
        ->and(data_get($experience, 'entry.mode'))->toBe('rider_first')
        ->and(collect(data_get($experience, 'phases', []))->pluck('key')->all())
        ->toContain('rider_intro');
});

it('emits skip consumed splash option for rider first journey', function () {
    $experience = resolveSplashOwnershipExperience(
        splashOwnershipVoucherWithRiderSplash(),
    );

    expect(data_get($experience, 'options.skip_consumed_splash'))->toBeTrue()
        ->and(data_get($experience, 'diagnostics.form_flow_splash_policy'))->toBe('skip_consumed');
});

it('does not skip form flow splash when voucher has no rider intro splash', function () {
    $experience = resolveSplashOwnershipExperience(
        splashOwnershipVoucherWithoutRiderSplash(),
    );

    expect(data_get($experience, 'consumed.splash'))->toBeFalse()
        ->and(data_get($experience, 'options.skip_consumed_splash'))->toBeFalse()
        ->and(data_get($experience, 'diagnostics.form_flow_splash_policy'))->toBe('allow');
});

it('reports duplicate splash prevention in diagnostics', function () {
    $experience = resolveSplashOwnershipExperience(
        splashOwnershipVoucherWithRiderSplash(),
    );

    expect(data_get($experience, 'diagnostics.duplicate_splash_prevented'))->toBeTrue();
});
