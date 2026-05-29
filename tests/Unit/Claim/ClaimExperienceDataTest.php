<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Claim\ClaimExperienceData;

it('normalizes claim experience as a first class claim ux contract', function () {
    $experience = ClaimExperienceData::from([
        'version' => 1,
        'entry' => [
            'mode' => 'rider_first',
            'initial_phase' => 'rider_intro',
        ],
        'phases' => [
            [
                'key' => 'rider_intro',
                'owner' => 'x-rider',
                'source' => 'voucher.instructions.rider.splash',
            ],
            [
                'key' => 'redirect',
                'owner' => 'claim-widget',
                'source' => 'voucher.instructions.rider.redirect_url',
                'delay_seconds' => 5,
            ],
        ],
        'consumed' => [
            'splash' => true,
        ],
        'options' => [
            'skip_consumed_splash' => true,
            'show_redirect_countdown' => true,
        ],
        'diagnostics' => [
            'splash_owner' => 'x-rider',
            'redirect_owner' => 'claim-widget',
            'duplicate_splash_prevented' => true,
        ],
    ]);

    $array = $experience->toArray();

    expect($array)
        ->toMatchArray([
            'version' => 1,
            'entry' => [
                'mode' => 'rider_first',
                'initial_phase' => 'rider_intro',
            ],
            'consumed' => [
                'splash' => true,
            ],
            'options' => [
                'skip_consumed_splash' => true,
                'show_redirect_countdown' => true,
            ],
        ])
        ->and(data_get($array, 'diagnostics.splash_owner'))->toBe('x-rider')
        ->and(data_get($array, 'diagnostics.redirect_owner'))->toBe('claim-widget')
        ->and(data_get($array, 'diagnostics.duplicate_splash_prevented'))->toBeTrue()
        ->and(data_get($array, 'diagnostics.form_flow_splash_policy'))->toBeNull()
        ->and(data_get($array, 'diagnostics.consumed'))->toBe([])
        ->and(data_get($array, 'diagnostics.warnings'))->toBe([]);

});
