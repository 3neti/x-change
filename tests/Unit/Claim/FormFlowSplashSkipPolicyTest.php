<?php

declare(strict_types=1);

use LBHurtado\XChange\Support\Claim\FormFlowSplashSkipPolicy;

it('removes splash steps when consumed splash should be skipped', function () {
    $payload = [
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
            'claim_experience' => [
                'options' => [
                    'skip_consumed_splash' => true,
                ],
            ],
        ],
    ];

    expect(app(FormFlowSplashSkipPolicy::class)->apply($payload))->toBe([
        'steps' => [
            [
                'handler' => 'form',
                'config' => [
                    'title' => 'Claim Details',
                ],
            ],
        ],
        'metadata' => [
            'claim_experience' => [
                'options' => [
                    'skip_consumed_splash' => true,
                ],
            ],
        ],
    ]);
});

it('keeps splash steps when consumed splash should not be skipped', function () {
    $payload = [
        'steps' => [
            [
                'handler' => 'splash',
            ],
            [
                'handler' => 'form',
            ],
        ],
        'metadata' => [
            'claim_experience' => [
                'options' => [
                    'skip_consumed_splash' => false,
                ],
            ],
        ],
    ];

    expect(app(FormFlowSplashSkipPolicy::class)->apply($payload))->toBe($payload);
});

it('keeps payload unchanged when claim experience is missing', function () {
    $payload = [
        'steps' => [
            [
                'handler' => 'splash',
            ],
        ],
    ];

    expect(app(FormFlowSplashSkipPolicy::class)->apply($payload))->toBe($payload);
});

it('keeps payload unchanged when steps are missing', function () {
    $payload = [
        'metadata' => [
            'claim_experience' => [
                'options' => [
                    'skip_consumed_splash' => true,
                ],
            ],
        ],
    ];

    expect(app(FormFlowSplashSkipPolicy::class)->apply($payload))->toBe($payload);
});

it('keeps non-splash steps and preserves order', function () {
    $payload = [
        'steps' => [
            [
                'handler' => 'form',
                'config' => [
                    'key' => 'first',
                ],
            ],
            [
                'handler' => 'splash',
            ],
            [
                'handler' => 'confirmation',
                'config' => [
                    'key' => 'last',
                ],
            ],
        ],
        'metadata' => [
            'claim_experience' => [
                'options' => [
                    'skip_consumed_splash' => true,
                ],
            ],
        ],
    ];

    expect(app(FormFlowSplashSkipPolicy::class)->apply($payload)['steps'])->toBe([
        [
            'handler' => 'form',
            'config' => [
                'key' => 'first',
            ],
        ],
        [
            'handler' => 'confirmation',
            'config' => [
                'key' => 'last',
            ],
        ],
    ]);
});
