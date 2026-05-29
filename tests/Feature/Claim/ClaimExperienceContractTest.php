<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Claim\ResolveClaimExperience;

it('emits a coherent claim experience contract for rider splash and redirect vouchers', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'rider' => [
                'splash' => '<h1>Welcome</h1>',
                'message' => 'SUCCESS DEMO: Thank you for claiming.',
                'url' => 'https://example.com/after-claim',
            ],
        ],
    ));

    $experience = ResolveClaimExperience::run($voucher)->toArray();

    $phases = collect(data_get($experience, 'phases', []));

    expect(data_get($experience, 'version'))->toBe(1)
        ->and(data_get($experience, 'entry.mode'))->toBe('rider_first')
        ->and(data_get($experience, 'consumed.splash'))->toBeTrue()
        ->and(data_get($experience, 'options.skip_consumed_splash'))->toBeTrue()
        ->and(data_get($experience, 'options.show_redirect_countdown'))->toBeTrue()
        ->and(data_get($experience, 'diagnostics.splash_owner'))->toBe('x-rider')
        ->and(data_get($experience, 'diagnostics.redirect_owner'))->toBe('claim-widget')
        ->and(data_get($experience, 'diagnostics.duplicate_splash_prevented'))->toBeTrue()
        ->and($phases->pluck('key')->all())->toContain('rider_intro', 'form_flow', 'success_rider', 'redirect')
        ->and($phases->where('key', 'redirect'))->toHaveCount(1)
        ->and($phases->where('key', 'rider_intro'))->toHaveCount(1);
});
