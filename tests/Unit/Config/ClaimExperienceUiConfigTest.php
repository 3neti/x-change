<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('defines the public claim experience ui profile without cockpit coupling', function () {
    $profile = config('x-change.claim.experience_ui');

    expect($profile)
        ->toHaveKeys([
            'variant',
            'show_progress',
            'support_label',
            'layout',
            'copy',
            'permissions',
        ])
        ->and($profile['variant'])->toBeIn(['default', 'compact', 'immersive'])
        ->and($profile['copy'])->toHaveKeys([
            'entry_title',
            'wallet_title',
            'confirmation_title',
            'success_title',
            'approval_waiting_title',
            'issuer_otp_title',
        ])
        ->and($profile['layout'])->toHaveKeys([
            'density',
            'capture_surface',
            'minimize_scroll',
        ])
        ->and($profile['layout']['density'])->toBeIn(['default', 'compact', 'immersive'])
        ->and($profile['layout']['capture_surface'])->toBeIn(['default', 'edge_to_edge'])
        ->and($profile['layout']['minimize_scroll'])->toBeBool()
        ->and($profile['permissions'])->toHaveKeys([
            'location',
            'camera',
            'signature',
            'kyc',
        ])
        ->and(array_key_exists('cockpit', $profile))->toBeFalse();
});
