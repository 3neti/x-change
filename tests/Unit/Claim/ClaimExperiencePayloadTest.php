<?php

declare(strict_types=1);

use LBHurtado\XChange\Support\Claim\ClaimExperiencePayload;

it('reads claim experience from supported state locations', function () {
    $experience = ['version' => 1];

    expect(ClaimExperiencePayload::fromState([
        'claim_experience' => $experience,
    ]))->toBe($experience)
        ->and(ClaimExperiencePayload::fromState([
            'metadata' => [
                'claim_experience' => $experience,
            ],
        ]))->toBe($experience)
        ->and(ClaimExperiencePayload::fromState([
            'instructions' => [
                'metadata' => [
                    'claim_experience' => $experience,
                ],
            ],
        ]))->toBe($experience);

});

it('writes claim experience into form flow instruction metadata', function () {
    $instructions = ClaimExperiencePayload::putIntoInstructions([
        'metadata' => [
            'voucher_code' => 'TEST123',
        ],
    ], [
        'version' => 1,
    ]);

    expect(data_get($instructions, 'metadata.voucher_code'))->toBe('TEST123')
        ->and(data_get($instructions, 'metadata.claim_experience.version'))->toBe(1);
});

it('derives redirect props from claim experience contract', function () {
    $redirect = ClaimExperiencePayload::redirect([
        'options' => [
            'show_redirect_countdown' => true,
        ],
        'diagnostics' => [
            'redirect_owner' => 'claim-widget',
        ],
        'phases' => [
            [
                'key' => 'redirect',
                'delay_seconds' => 5,
            ],
        ],
    ]);

    expect($redirect)->toBe([
        'show_countdown' => true,
        'owner' => 'claim-widget',
        'delay_seconds' => 5,
    ]);
});

it('derives disabled redirect props when countdown is not enabled', function () {
    $redirect = ClaimExperiencePayload::redirect([
        'options' => [
            'show_redirect_countdown' => false,
        ],
        'diagnostics' => [
            'redirect_owner' => null,
        ],
        'phases' => [],
    ]);

    expect($redirect)->toBe([
        'show_countdown' => false,
        'owner' => null,
        'delay_seconds' => null,
    ]);
});

it('only enables success countdown when claim widget owns redirect', function () {
    $redirect = ClaimExperiencePayload::redirect([
        'options' => [
            'show_redirect_countdown' => true,
        ],
        'diagnostics' => [
            'redirect_owner' => 'x-rider',
        ],
        'phases' => [
            [
                'key' => 'redirect',
                'delay_seconds' => 5,
            ],
        ],
    ]);

    expect($redirect)->toBe([
        'show_countdown' => false,
        'owner' => 'x-rider',
        'delay_seconds' => 5,
    ]);
});

it('identifies claim widget owned redirects', function () {
    expect(ClaimExperiencePayload::isClaimWidgetRedirect([
        'diagnostics' => [
            'redirect_owner' => 'claim-widget',
        ],
    ]))->toBeTrue()
        ->and(ClaimExperiencePayload::isClaimWidgetRedirect([
            'diagnostics' => [
                'redirect_owner' => 'x-rider',
            ],
        ]))->toBeFalse()
        ->and(ClaimExperiencePayload::isClaimWidgetRedirect([
            'diagnostics' => [
                'redirect_owner' => null,
            ],
        ]))->toBeFalse();

});

it('identifies splash ownership', function () {
    expect(ClaimExperiencePayload::isXRiderSplash([
        'diagnostics' => [
            'splash_owner' => 'x-rider',
        ],
    ]))->toBeTrue()
        ->and(ClaimExperiencePayload::isFormFlowSplash([
            'diagnostics' => [
                'splash_owner' => 'form-flow',
            ],
        ]))->toBeTrue()
        ->and(ClaimExperiencePayload::isXRiderSplash([
            'diagnostics' => [
                'splash_owner' => 'form-flow',
            ],
        ]))->toBeFalse();

});

it('knows when consumed splash should be skipped', function () {
    expect(ClaimExperiencePayload::shouldSkipConsumedSplash([
        'diagnostics' => [
            'splash_owner' => 'x-rider',
        ],
        'options' => [
            'skip_consumed_splash' => true,
        ],
        'consumed' => [
            'splash' => true,
        ],
    ]))->toBeTrue()
        ->and(ClaimExperiencePayload::shouldSkipConsumedSplash([
            'diagnostics' => [
                'splash_owner' => 'form-flow',
            ],
            'options' => [
                'skip_consumed_splash' => true,
            ],
            'consumed' => [
                'splash' => true,
            ],
        ]))->toBeFalse()
        ->and(ClaimExperiencePayload::shouldSkipConsumedSplash([
            'diagnostics' => [
                'splash_owner' => 'x-rider',
            ],
            'options' => [
                'skip_consumed_splash' => false,
            ],
            'consumed' => [
                'splash' => true,
            ],
        ]))->toBeFalse();

});

it('builds instructions with claim experience and applies consumed splash skip policy', function () {
    $instructions = [
        'steps' => [
            [
                'handler' => 'splash',
                'config' => [
                    'title' => 'Welcome',
                ],
            ],
            [
                'handler' => 'form',
                'config' => [
                    'title' => 'Claim Details',
                ],
            ],
        ],
        'metadata' => [
            'voucher_code' => 'TEST123',
        ],
    ];

    $experience = [
        'version' => 1,
        'consumed' => [
            'splash' => true,
        ],
        'options' => [
            'skip_consumed_splash' => true,
        ],
        'diagnostics' => [
            'splash_owner' => ClaimExperiencePayload::SPLASH_OWNER_X_RIDER,
            'form_flow_splash_policy' => 'skip_consumed',
        ],
    ];

    $payload = app(ClaimExperiencePayload::class)->build(
        $instructions,
        $experience,
    );

    expect(data_get($payload, 'metadata.claim_experience'))->toBe($experience)
        ->and(collect(data_get($payload, 'steps', []))->pluck('handler')->all())
        ->toBe(['form']);
});

it('builds instructions with claim experience and keeps splash when skip policy is disabled', function () {
    $instructions = [
        'steps' => [
            [
                'handler' => 'splash',
            ],
            [
                'handler' => 'form',
            ],
        ],
        'metadata' => [
            'voucher_code' => 'TEST123',
        ],
    ];

    $experience = [
        'version' => 1,
        'consumed' => [
            'splash' => false,
        ],
        'options' => [
            'skip_consumed_splash' => false,
        ],
        'diagnostics' => [
            'splash_owner' => ClaimExperiencePayload::SPLASH_OWNER_FORM_FLOW,
            'form_flow_splash_policy' => 'allow',
        ],
    ];

    $payload = app(ClaimExperiencePayload::class)->build(
        $instructions,
        $experience,
    );

    expect(data_get($payload, 'metadata.claim_experience'))->toBe($experience)
        ->and(collect(data_get($payload, 'steps', []))->pluck('handler')->all())
        ->toBe(['splash', 'form']);
});
